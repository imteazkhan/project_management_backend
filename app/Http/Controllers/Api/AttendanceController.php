<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AttendanceResource;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private const WITH = ['user.employee.department', 'user.teams'];

    // Admin/manager see every record (optionally scoped to one employee);
    // employees only ever see their own attendance history.
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Attendance::with(self::WITH);

        if ($user->isEmployee()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date('date_to'));
        }

        $records = $query->orderByDesc('date')->get();

        return response()->json(['attendances' => AttendanceResource::collection($records)]);
    }

    // The signed-in user's own record for today, or null if they haven't
    // checked in yet — drives the check-in/out button state on the frontend.
    public function today(Request $request): JsonResponse
    {
        $record = Attendance::with(self::WITH)
            ->where('user_id', $request->user()->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        return response()->json(['attendance' => $record ? new AttendanceResource($record) : null]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        abort_if(
            Attendance::where('user_id', $user->id)->whereDate('date', $today)->exists(),
            422,
            'You have already checked in today.',
        );

        $settings = AttendanceSetting::current();
        $now = now();
        $isLate = $now->format('H:i:s') > $settings->office_start_time;

        $record = Attendance::create([
            'user_id' => $user->id,
            'date' => $today,
            'check_in_at' => $now,
            'status' => $isLate ? 'late' : 'present',
        ]);

        return response()->json([
            'message' => 'Checked in successfully',
            'attendance' => new AttendanceResource($record->load(self::WITH)),
        ], 201);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $user = $request->user();

        $record = Attendance::where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        abort_unless($record, 422, 'You have not checked in today.');
        abort_if($record->check_out_at, 422, 'You have already checked out today.');

        $settings = AttendanceSetting::current();
        $now = now();
        $totalMinutes = (int) round($record->check_in_at->diffInMinutes($now));

        $presentThreshold = (float) $settings->present_hours * 60;
        $halfDayThreshold = (float) $settings->half_day_hours * 60;
        $wasLate = $record->status === 'late';

        if ($totalMinutes >= $presentThreshold) {
            $status = $wasLate ? 'late' : 'present';
        } elseif ($totalMinutes >= $halfDayThreshold) {
            $status = 'half-day';
        } else {
            $status = 'absent';
        }

        $record->update([
            'check_out_at' => $now,
            'total_minutes' => $totalMinutes,
            'status' => $status,
        ]);

        return response()->json([
            'message' => 'Checked out successfully',
            'attendance' => new AttendanceResource($record->fresh()->load(self::WITH)),
        ]);
    }
}
