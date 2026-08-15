<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Team\StoreTeamRequest;
use App\Http\Requests\Api\Team\UpdateTeamRequest;
use App\Http\Resources\Api\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        $teams = Team::with('users')->latest()->get();

        return response()->json([
            'teams' => TeamResource::collection($teams),
        ]);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = Team::create([
            'name' => $request->name,
        ]);

        $this->syncMembers($team, $request->members ?? []);

        return response()->json([
            'message' => 'Team created successfully',
            'team' => new TeamResource($team->load('users')),
        ], 201);
    }

    public function show(Team $team): JsonResponse
    {
        return response()->json([
            'team' => new TeamResource($team->load('users')),
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        $team->update([
            'name' => $request->name,
        ]);

        $this->syncMembers($team, $request->members ?? []);

        return response()->json([
            'message' => 'Team updated successfully',
            'team' => new TeamResource($team->load('users')),
        ]);
    }

    public function destroy(Team $team): JsonResponse
    {
        $team->delete();

        return response()->json([
            'message' => 'Team deleted successfully',
        ]);
    }

    private function syncMembers(Team $team, array $members): void
    {
        $sync = [];

        foreach ($members as $member) {
            $sync[$member['user_id']] = ['role' => $member['role']];
        }

        $team->users()->sync($sync);
    }
}
