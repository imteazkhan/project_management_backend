<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Task\StoreTaskRequest;
use App\Http\Requests\Api\Task\UpdateTaskRequest;
use App\Http\Resources\Api\TaskResource;
use App\Models\Notification;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    private const WITH = ['project', 'assignee', 'creator', 'approver', 'subtasks.assignee'];

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

        return response()->json([
            'message' => 'Task assigned successfully',
            'task' => new TaskResource($task->load(self::WITH)),
        ], 201);
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $task->update([
            'title' => $request->title,
            'description' => $request->description ?? '',
            'priority' => $request->priority ?? $task->priority,
            'assigned_to' => $request->assigned_to,
            'due_date' => $request->due_date,
        ]);

        return response()->json([
            'message' => 'Task updated successfully',
            'task' => new TaskResource($task->load(self::WITH)),
        ]);
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }

    // Employee: begin work on an assigned task. not_started (0%) -> in_progress (10%).
    public function start(Request $request, Task $task): JsonResponse
    {
        $this->authorizeOwner($request, $task);
        abort_unless($task->status === 'not_started', 422, 'Task has already been started.');

        $task->update(['status' => 'in_progress', 'progress' => 10]);

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

        $subtask->update([
            'status' => $subtask->status === 'completed' ? 'not_started' : 'completed',
            'progress' => $subtask->status === 'completed' ? 0 : 100,
        ]);

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

        return response()->json(['task' => new TaskResource($task->load(self::WITH))]);
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
}
