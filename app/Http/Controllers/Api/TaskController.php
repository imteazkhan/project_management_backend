<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Task\ContributeTaskRequest;
use App\Http\Requests\Api\Task\StoreTaskRequest;
use App\Http\Requests\Api\Task\UpdateTaskRequest;
use App\Http\Resources\Api\TaskActivityLogResource;
use App\Http\Resources\Api\TaskResource;
use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    private const WITH = ['project', 'assignee', 'creator', 'approver', 'rejecter', 'subtasks.assignee'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Task::with(self::WITH)->whereNull('parent_id');

        // Employees only ever see work assigned to them; admins/managers can
        // see every task, optionally scoped to one project.
        if ($user->isEmployee()) {
            $query->where('assigned_to', $user->id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        // Contributions report (admin/manager): narrow the same task list by
        // employee, status, and a due-date range.
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('due_date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('due_date', '<=', $request->date('date_to'));
        }

        $tasks = $query->latest()->get();

        return response()->json(['tasks' => TaskResource::collection($tasks)]);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorizeView($request, $task);

        return response()->json(['task' => new TaskResource($task->load(self::WITH))]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = Task::create([
            'project_id' => $request->project_id,
            'assigned_to' => $request->assigned_to,
            'created_by' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description ?? '',
            'priority' => $request->priority ?? 'medium',
            'due_date' => $request->due_date,
            'status' => 'not_started',
            'progress' => 0,
        ]);

        foreach ($request->input('subtasks', []) as $index => $title) {
            $task->subtasks()->create([
                'project_id' => $task->project_id,
                'assigned_to' => $task->assigned_to,
                'created_by' => $request->user()->id,
                'title' => $title,
                'description' => '',
                'status' => 'not_started',
                'progress' => 0,
                'position' => $index,
            ]);
        }

        $this->logActivity($task, $request->user()->id, 'created', null, 'not_started');

        return response()->json([
            'message' => 'Task assigned successfully',
            'task' => new TaskResource($task->load(self::WITH)),
        ], 201);
    }

    // Employee (or any role): self-report work that was never pre-assigned
    // by a manager/admin. Goes straight to "submitted" (90%) — the employee
    // already did the work, so there's no start/in-progress step — and
    // awaits the same approve()/reject() review as a normal task.
    public function contribute(ContributeTaskRequest $request): JsonResponse
    {
        $user = $request->user();

        $task = Task::create([
            'project_id' => $request->project_id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'title' => $request->title,
            'description' => $request->description ?? '',
            'priority' => 'medium',
            'status' => 'submitted',
            'progress' => 90,
            'submitted_at' => now(),
        ]);

        $this->logActivity($task, $user->id, 'submitted', null, 'submitted', 'Self-reported contribution');

        $reviewers = User::whereIn('role', ['admin', 'manager'])->get();
        foreach ($reviewers as $reviewer) {
            Notification::create([
                'user_id' => $reviewer->id,
                'type' => 'task_pending_approval',
                'title' => 'New contribution to review',
                'message' => "{$user->name} submitted a contribution \"{$task->title}\" for your review.",
                'task_id' => $task->id,
            ]);
        }

        return response()->json([
            'message' => 'Contribution submitted for review',
            'task' => new TaskResource($task->load(self::WITH)),
        ], 201);
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $previousAssignee = $task->assigned_to;

        $task->update([
            'title' => $request->title,
            'description' => $request->description ?? '',
            'priority' => $request->priority ?? $task->priority,
            'assigned_to' => $request->assigned_to,
            'due_date' => $request->due_date,
        ]);

        if ($previousAssignee !== $task->assigned_to) {
            $this->logActivity($task, $request->user()->id, 'reassigned', null, null, "Reassigned from user #{$previousAssignee} to user #{$task->assigned_to}");
        }

        return response()->json([
            'message' => 'Task updated successfully',
            'task' => new TaskResource($task->load(self::WITH)),
        ]);
    }

    public function destroy(Task $task): JsonResponse
    {
        abort_if($task->parent_id !== null, 422, 'Sub-tasks cannot be deleted. Pause them instead.');

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }

    // Manager/Admin: add a new sub-task to an existing task after the task
    // was already assigned. Flagged is_added_later so the employee can see
    // it wasn't part of the original checklist.
    public function addSubtask(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $position = $task->subtasks()->max('position');

        $subtask = $task->subtasks()->create([
            'project_id' => $task->project_id,
            'assigned_to' => $task->assigned_to,
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'description' => '',
            'status' => 'not_started',
            'progress' => 0,
            'position' => $position === null ? 0 : $position + 1,
            'is_added_later' => true,
        ]);

        $this->logActivity($task, $request->user()->id, 'subtask_added', null, null, "Sub-task \"{$subtask->title}\" added");

        return response()->json([
            'message' => 'Sub-task added successfully',
            'task' => new TaskResource($task->fresh()->load(self::WITH)),
        ], 201);
    }

    // Manager/Admin: rename an existing sub-task. Flagged is_edited so the
    // employee can see the checklist item changed after it was first set.
    public function updateSubtask(Request $request, Task $task, Task $subtask): JsonResponse
    {
        abort_unless($subtask->parent_id === $task->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        if ($data['title'] !== $subtask->title) {
            $subtask->update([
                'title' => $data['title'],
                'is_edited' => true,
            ]);

            $this->logActivity($task, $request->user()->id, 'subtask_edited', null, null, "Sub-task renamed to \"{$data['title']}\"");
        }

        return response()->json([
            'message' => 'Sub-task updated successfully',
            'task' => new TaskResource($task->fresh()->load(self::WITH)),
        ]);
    }

    // Manager/Admin: sub-tasks can't be deleted, only paused/resumed. Pausing
    // preserves the status it was in so resuming puts it right back.
    public function pauseSubtask(Request $request, Task $task, Task $subtask): JsonResponse
    {
        abort_unless($subtask->parent_id === $task->id, 404);

        if ($subtask->status === 'paused') {
            $subtask->update([
                'status' => $subtask->paused_from_status ?? 'not_started',
                'paused_from_status' => null,
            ]);
            $action = 'resumed';
        } else {
            abort_if($subtask->status === 'completed', 422, 'Completed sub-tasks cannot be paused.');

            $subtask->update([
                'paused_from_status' => $subtask->status,
                'status' => 'paused',
            ]);
            $action = 'paused';
        }

        $this->logActivity($task, $request->user()->id, "subtask_{$action}", null, null, "Sub-task \"{$subtask->title}\" {$action}");

        return response()->json([
            'message' => "Sub-task {$action} successfully",
            'task' => new TaskResource($task->fresh()->load(self::WITH)),
        ]);
    }

    // Employee: begin work on an assigned task. not_started (0%) -> in_progress (10%).
    public function start(Request $request, Task $task): JsonResponse
    {
        $this->authorizeOwner($request, $task);
        abort_unless($task->status === 'not_started', 422, 'Task has already been started.');

        $task->update(['status' => 'in_progress', 'progress' => 10, 'started_at' => now()]);

        $this->logActivity($task, $request->user()->id, 'started', 'not_started', 'in_progress');

        return response()->json(['task' => new TaskResource($task->load(self::WITH))]);
    }

    // Employee: toggle one sub-task's completion. The parent task's progress
    // auto-recalculates between 10% and 70% based on how many are done —
    // status stays in_progress throughout.
    public function toggleSubtask(Request $request, Task $task, Task $subtask): JsonResponse
    {
        $this->authorizeOwner($request, $task);
        abort_unless($subtask->parent_id === $task->id, 404);
        abort_unless($task->status === 'in_progress', 422, 'Start the task before updating sub-tasks.');
        abort_if($subtask->status === 'paused', 422, 'This sub-task is paused. Ask your manager to resume it first.');

        $wasCompleted = $subtask->status === 'completed';

        $subtask->update([
            'status' => $wasCompleted ? 'not_started' : 'completed',
            'progress' => $wasCompleted ? 0 : 100,
        ]);

        $this->logActivity(
            $task,
            $request->user()->id,
            'subtask_toggled',
            $wasCompleted ? 'completed' : 'not_started',
            $wasCompleted ? 'not_started' : 'completed',
            "Sub-task \"{$subtask->title}\" marked ".($wasCompleted ? 'incomplete' : 'complete'),
        );

        $total = $task->subtasks()->count();
        $completed = $task->subtasks()->where('status', 'completed')->count();
        $progress = $total > 0 ? 10 + (int) round(($completed / $total) * 60) : 10;

        $task->update(['progress' => $progress]);

        return response()->json(['task' => new TaskResource($task->fresh()->load(self::WITH))]);
    }

    // Employee: hand finished work to the manager for review. -> submitted (90%).
    public function submit(Request $request, Task $task): JsonResponse
    {
        $this->authorizeOwner($request, $task);
        abort_unless($task->status === 'in_progress', 422, 'Task must be in progress before it can be submitted.');

        $task->update([
            'status' => 'submitted',
            'progress' => 90,
            'submitted_at' => now(),
        ]);

        $this->logActivity($task, $request->user()->id, 'submitted', 'in_progress', 'submitted');

        return response()->json(['task' => new TaskResource($task->load(self::WITH))]);
    }

    // Manager/Admin: approve a submitted task. -> completed (100%), notifies
    // the employee. This is the only way a task ever reaches 100% — an
    // employee can never set that themselves.
    public function approve(Request $request, Task $task): JsonResponse
    {
        abort_unless($task->status === 'submitted', 422, 'Task is not pending review.');

        $task->update([
            'status' => 'completed',
            'progress' => 100,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        Notification::create([
            'user_id' => $task->assigned_to,
            'type' => 'task_approved',
            'title' => 'Task approved',
            'message' => "Your task \"{$task->title}\" was approved. Well done!",
            'task_id' => $task->id,
        ]);

        $this->logActivity($task, $request->user()->id, 'approved', 'submitted', 'completed');

        return response()->json(['task' => new TaskResource($task->load(self::WITH))]);
    }

    // Manager/Admin: reject a submitted task, sending it back to the
    // employee with an optional reason instead of marking it complete.
    public function reject(Request $request, Task $task): JsonResponse
    {
        abort_unless($task->status === 'submitted', 422, 'Task is not pending review.');

        $reason = $request->string('reason')->toString() ?: null;

        $task->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $request->user()->id,
            'rejection_reason' => $reason,
        ]);

        Notification::create([
            'user_id' => $task->assigned_to,
            'type' => 'task_rejected',
            'title' => 'Task rejected',
            'message' => $reason
                ? "Your task \"{$task->title}\" was rejected: {$reason}"
                : "Your task \"{$task->title}\" was rejected.",
            'task_id' => $task->id,
        ]);

        $this->logActivity($task, $request->user()->id, 'rejected', 'submitted', 'rejected', $reason);

        return response()->json(['task' => new TaskResource($task->load(self::WITH))]);
    }

    // Full audit trail for a task: who created/started/toggled/submitted/
    // approved it and when. Same visibility rule as show() — employees can
    // only see the log for tasks assigned to them.
    public function activities(Request $request, Task $task): JsonResponse
    {
        $this->authorizeView($request, $task);

        return response()->json([
            'activities' => TaskActivityLogResource::collection($task->activities()->with('user')->get()),
        ]);
    }

    private function authorizeOwner(Request $request, Task $task): void
    {
        abort_unless($task->assigned_to === $request->user()->id, 403, 'This task is not assigned to you.');
    }

    private function authorizeView(Request $request, Task $task): void
    {
        $user = $request->user();
        abort_if($user->isEmployee() && $task->assigned_to !== $user->id, 403);
    }

    private function logActivity(
        Task $task,
        int $userId,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $note = null,
    ): void {
        TaskActivityLog::create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
        ]);
    }
}
