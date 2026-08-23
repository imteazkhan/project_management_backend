<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Project\StoreProjectRequest;
use App\Http\Requests\Api\Project\UpdateProjectRequest;
use App\Http\Resources\Api\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProjectsController extends Controller
{
    private const WITH = ['team.users', 'owner'];

    public function index(): JsonResponse
    {
        $projects = Project::with(self::WITH)->latest()->get();

        return response()->json([
            'projects' => ProjectResource::collection($projects),
        ]);
    }

    public function show(Project $project): JsonResponse
    {
        return response()->json([
            'project' => new ProjectResource($project->load(self::WITH)),
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
            'team_id' => $request->team_id,
            'owner_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Project created successfully',
            'project' => new ProjectResource($project->load(self::WITH)),
        ], 201);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $project->update([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'client' => $request->client,
            'progress' => $request->progress ?? 0,
            'team_id' => $request->team_id,
        ]);

        return response()->json([
            'message' => 'Project updated successfully',
            'project' => new ProjectResource($project->load(self::WITH)),
        ]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json([
            'message' => 'Project deleted successfully',
        ]);
    }
}
