<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Project\StoreProjectRequest;
use App\Http\Requests\Api\Project\UpdateProjectRequest;
use App\Http\Resources\Api\ProjectResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectsController extends Controller
{
    private const WITH = ['team.users', 'owner'];

    // Top-level tasks only, loaded for progressPercent() so it doesn't
    // re-query per project.
    private static function withProgress(): array
    {
        return [...self::WITH, 'tasks' => fn ($q) => $q->whereNull('parent_id')];
    }

    public function index(): JsonResponse
    {
        $projects = Project::with(self::withProgress())->latest()->get();

        return response()->json([
            'projects' => ProjectResource::collection($projects),
        ]);
    }

    // "My Projects": for the signed-in user (an employee, in practice),
    // summarise every project they have at least one task assigned in —
    // their own progress on it, and their share of the project's total
    // task-progress as a contribution percentage. Unlike index(), this
    // never exposes projects the user isn't actually working on.
    public function mySummary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $myStats = Task::query()
            ->whereNull('parent_id')
            ->where('assigned_to', $userId)
            ->selectRaw(
                'project_id, COUNT(*) as total, '
                . "SUM(CASE WHEN status = 'not_started' THEN 1 ELSE 0 END) as not_started, "
                . "SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress, "
                . "SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted, "
                . "SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed, "
                . 'SUM(progress) as progress_sum, MAX(updated_at) as last_activity_at',
            )
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        if ($myStats->isEmpty()) {
            return response()->json(['projects' => []]);
        }

        $projectIds = $myStats->keys();

        $projectTotals = Task::query()
            ->whereNull('parent_id')
            ->whereIn('project_id', $projectIds)
            ->selectRaw('project_id, SUM(progress) as progress_sum')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $projects = Project::with(self::withProgress())->whereIn('id', $projectIds)->get()->keyBy('id');

        $summaries = $projectIds
            ->map(function ($projectId) use ($myStats, $projectTotals, $projects) {
                $project = $projects->get($projectId);
                if (!$project) {
                    return null;
                }

                $mine = $myStats->get($projectId);
                $myProgressSum = (float) $mine->progress_sum;
                $totalProgressSum = (float) ($projectTotals->get($projectId)->progress_sum ?? 0);

                return [
                    'project' => new ProjectResource($project),
                    'my_tasks' => [
                        'total' => (int) $mine->total,
                        'not_started' => (int) $mine->not_started,
                        'in_progress' => (int) $mine->in_progress,
                        'submitted' => (int) $mine->submitted,
                        'completed' => (int) $mine->completed,
                    ],
                    'my_progress' => $mine->total > 0 ? (int) round($myProgressSum / $mine->total) : 0,
                    'contribution_percent' => $totalProgressSum > 0
                        ? round(($myProgressSum / $totalProgressSum) * 100, 1)
                        : 0.0,
                    'last_activity_at' => $mine->last_activity_at,
                ];
            })
            ->filter()
            ->sortByDesc('last_activity_at')
            ->values();

        return response()->json(['projects' => $summaries]);
    }

    public function show(Project $project): JsonResponse
    {
        return response()->json([
            'project' => new ProjectResource($project->load(self::withProgress())),
        ]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::create([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'client' => $request->client,
            'progress' => $request->progress ?? 0,
            'pdf' => $request->hasFile('pdf') ? $request->file('pdf')->store('projects/pdfs', 'public') : null,
            'github_link' => $request->github_link,
            'team_id' => $request->team_id,
            'owner_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Project created successfully',
            'project' => new ProjectResource($project->load(self::withProgress())),
        ], 201);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'client' => $request->client,
            'progress' => $request->progress ?? 0,
            'github_link' => $request->github_link,
            'team_id' => $request->team_id,
        ];

        if ($request->hasFile('pdf')) {
            if ($project->pdf) {
                Storage::disk('public')->delete($project->pdf);
            }
            $data['pdf'] = $request->file('pdf')->store('projects/pdfs', 'public');
        }

        $project->update($data);

        return response()->json([
            'message' => 'Project updated successfully',
            'project' => new ProjectResource($project->load(self::withProgress())),
        ]);
    }

    public function destroy(Project $project): JsonResponse
    {
        if ($project->pdf) {
            Storage::disk('public')->delete($project->pdf);
        }

        $project->delete();

        return response()->json([
            'message' => 'Project deleted successfully',
        ]);
    }
}
