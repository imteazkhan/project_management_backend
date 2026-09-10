<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\LeaveRequestResource;
use App\Models\LeaveQuota;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LeaveController extends Controller
{
    private const WITH = ['user.employee', 'manager', 'decider'];
    private const PAID_TYPES = ['sick', 'casual', 'annual'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = LeaveRequest::with(self::WITH);

        if ($user->isEmployee()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isManager()) {
            $query->where(function ($q) use ($user) {
                $q->where('manager_id', $user->id)->orWhere('user_id', $user->id);
            });
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        $requests = $query->latest()->get();

        return response()->json(['leave_requests' => LeaveRequestResource::collection($requests)]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'type' => ['required', 'in:sick,casual,annual,unpaid'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_half_day' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $isHalfDay = $request->boolean('is_half_day') && $data['start_date'] === $data['end_date'];
        $days = $isHalfDay ? 0.5 : Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;

        $overlaps = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->exists();

        abort_if($overlaps, 422, 'You already have a leave request that overlaps these dates.');

        if (in_array($data['type'], self::PAID_TYPES, true)) {
            $quota = (float) (LeaveQuota::where('type', $data['type'])->value('days') ?? 0);
            $used = (float) LeaveRequest::where('user_id', $user->id)
                ->where('type', $data['type'])
                ->where('status', 'approved')
                ->whereYear('start_date', now()->year)
                ->sum('days');

            abort_if(
                $used + $days > $quota,
                422,
                sprintf('Insufficient %s leave balance — %.1f day(s) remaining.', $data['type'], max($quota - $used, 0)),
            );
        }

        $manager = $user->approvingManager();

        $leave = LeaveRequest::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days' => $days,
            'is_half_day' => $isHalfDay,
            'reason' => $data['reason'],
            'status' => 'pending',
            'manager_id' => $manager?->id,
        ]);

        $applicantName = $user->employee?->full_name ?? $user->name;

        if ($manager) {
            $this->notify($manager->id, 'leave_requested', 'New leave request', "{$applicantName} requested {$days} day(s) of {$data['type']} leave.");
        } else {
            $this->notifyAdmins('leave_requested', 'New leave request', "{$applicantName} requested {$days} day(s) of {$data['type']} leave.");
        }

        return response()->json([
            'message' => 'Leave request submitted',
            'leave_request' => new LeaveRequestResource($leave->load(self::WITH)),
        ], 201);
    }

    public function cancel(Request $request, LeaveRequest $leave): JsonResponse
    {
        $user = $request->user();

        abort_unless($leave->user_id === $user->id, 403);
        abort_unless(
            $leave->status === 'pending' || ($leave->status === 'approved' && $leave->start_date->isFuture()),
            422,
            'This leave request can no longer be cancelled.',
        );

        $leave->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Leave request cancelled',
            'leave_request' => new LeaveRequestResource($leave->fresh()->load(self::WITH)),
        ]);
    }

    // Manager: first-stage approval for a leave request from their team.
    public function managerApprove(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->authorizeManagerStep($request, $leave);

        $leave->update([
            'manager_action' => 'approved',
            'manager_decided_at' => now(),
            'manager_note' => $request->string('note')->toString() ?: null,
        ]);

        $this->notifyAdmins('leave_manager_approved', 'Leave needs final approval', "{$leave->user->employee?->full_name} \u{2192} leave request approved by manager, awaiting admin decision.");

        return response()->json([
            'message' => 'Approved — waiting for admin\'s final decision',
            'leave_request' => new LeaveRequestResource($leave->fresh()->load(self::WITH)),
        ]);
    }

    // Manager rejection is final — it doesn't need to reach the admin.
    public function managerReject(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->authorizeManagerStep($request, $leave);

        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $leave->update([
            'manager_action' => 'rejected',
            'manager_decided_at' => now(),
            'manager_note' => $data['note'],
            'status' => 'rejected',
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $data['note'],
        ]);

        $this->notify($leave->user_id, 'leave_rejected', 'Leave request rejected', "Your manager rejected your leave request: {$data['note']}");

        return response()->json([
            'message' => 'Leave request rejected',
            'leave_request' => new LeaveRequestResource($leave->fresh()->load(self::WITH)),
        ]);
    }

    // Admin: final decision. Requires the manager stage to be cleared first
    // when the applicant actually has a manager.
    public function approve(Request $request, LeaveRequest $leave): JsonResponse
    {
        abort_unless($leave->status === 'pending', 422, 'This request has already been decided.');
        abort_if(
            $leave->manager_id && $leave->manager_action !== 'approved',
            422,
            'Waiting for the manager to approve this request first.',
        );

        $leave->update([
            'status' => 'approved',
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $request->string('note')->toString() ?: null,
        ]);

        $this->notify($leave->user_id, 'leave_approved', 'Leave request approved', "Your {$leave->type} leave for {$leave->start_date->format('Y-m-d')} to {$leave->end_date->format('Y-m-d')} was approved.");

        return response()->json([
            'message' => 'Leave request approved',
            'leave_request' => new LeaveRequestResource($leave->fresh()->load(self::WITH)),
        ]);
    }

    public function reject(Request $request, LeaveRequest $leave): JsonResponse
    {
        abort_unless($leave->status === 'pending', 422, 'This request has already been decided.');

        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $leave->update([
            'status' => 'rejected',
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $data['note'],
        ]);

        $this->notify($leave->user_id, 'leave_rejected', 'Leave request rejected', "Your leave request was rejected: {$data['note']}");

        return response()->json([
            'message' => 'Leave request rejected',
            'leave_request' => new LeaveRequestResource($leave->fresh()->load(self::WITH)),
        ]);
    }

    // Balance breakdown for the signed-in user, or (admin/manager) another
    // user via ?user_id=.
    public function balances(Request $request): JsonResponse
    {
        $user = $request->user();
        $targetId = $user->id;

        if (!$user->isEmployee() && $request->filled('user_id')) {
            $targetId = $request->integer('user_id');
        }

        $breakdown = [];

        foreach (LeaveQuota::TYPES as $type) {
            $quota = (float) (LeaveQuota::where('type', $type)->value('days') ?? 0);
            $used = (float) LeaveRequest::where('user_id', $targetId)
                ->where('type', $type)
                ->where('status', 'approved')
                ->whereYear('start_date', now()->year)
                ->sum('days');

            $breakdown[] = [
                'type' => $type,
                'quota' => $quota,
                'used' => $used,
                'remaining' => max($quota - $used, 0),
            ];
        }

        $unpaidUsed = (float) LeaveRequest::where('user_id', $targetId)
            ->where('type', 'unpaid')
            ->where('status', 'approved')
            ->whereYear('start_date', now()->year)
            ->sum('days');

        $breakdown[] = ['type' => 'unpaid', 'quota' => null, 'used' => $unpaidUsed, 'remaining' => null];

        return response()->json(['balances' => $breakdown]);
    }

    private function authorizeManagerStep(Request $request, LeaveRequest $leave): void
    {
        $user = $request->user();
        abort_unless($user->id === $leave->manager_id || $user->isAdmin(), 403);
        abort_unless($leave->status === 'pending' && $leave->manager_action === null, 422, 'This request has already been decided.');
    }

    private function notify(int $userId, string $type, string $title, string $message): void
    {
        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
    }

    private function notifyAdmins(string $type, string $title, string $message): void
    {
        User::where('role', 'admin')->get()->each(
            fn (User $admin) => $this->notify($admin->id, $type, $title, $message),
        );
    }
}
