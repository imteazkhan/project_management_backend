<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Team\StoreTeamRequest;
use App\Http\Requests\Api\Team\UpdateTeamRequest;
use App\Http\Resources\Api\TeamResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    private const WITH = ['users', 'manager'];

    public function index(): JsonResponse
    {
        $teams = Team::with(self::WITH)->latest()->get();

        return response()->json([
            'teams' => TeamResource::collection($teams),
        ]);
    }

    // Minimal user list for the manager/member pickers on the team form —
    // separate from the admin-only Users module so managers can staff teams
    // without gaining access to full account administration.
    public function assignableUsers(): JsonResponse
    {
        $users = User::orderBy('name')->get(['id', 'name', 'role']);

        return response()->json([
            'users' => $users,
        ]);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = Team::create([
            'name' => $request->name,
            'description' => $request->description,
            'manager_id' => $request->manager_id,
        ]);

        $this->syncMembers($team, $request->members ?? []);

        return response()->json([
            'message' => 'Team created successfully',
            'team' => new TeamResource($team->load(self::WITH)),
        ], 201);
    }

    public function show(Team $team): JsonResponse
    {
        return response()->json([
            'team' => new TeamResource($team->load(self::WITH)),
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        $team->update([
            'name' => $request->name,
            'description' => $request->description,
            'manager_id' => $request->manager_id,
        ]);

        $this->syncMembers($team, $request->members ?? []);

        return response()->json([
            'message' => 'Team updated successfully',
            'team' => new TeamResource($team->load(self::WITH)),
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
