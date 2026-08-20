<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Manager\StoreManagerRequest;
use App\Http\Requests\Api\Manager\UpdateManagerRequest;
use App\Http\Resources\Api\ManagerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ManagerController extends Controller
{
    public function index(): JsonResponse
    {
        $managers = User::with('employee')
            ->where('role', 'manager')
            ->orderBy('name')
            ->get();

        return response()->json([
            'managers' => ManagerResource::collection($managers),
        ]);
    }

    public function store(StoreManagerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['role'] = 'manager';

        $manager = User::create($data);

        return response()->json([
            'message' => 'Manager created successfully',
            'manager' => new ManagerResource($manager->load('employee')),
        ], 201);
    }

    public function show(User $manager): JsonResponse
    {
        abort_unless($manager->role === 'manager', 404);

        return response()->json([
            'manager' => new ManagerResource($manager->load('employee')),
        ]);
    }

    public function update(UpdateManagerRequest $request, User $manager): JsonResponse
    {
        abort_unless($manager->role === 'manager', 404);

        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $manager->update($data);

        return response()->json([
            'message' => 'Manager updated successfully',
            'manager' => new ManagerResource($manager->load('employee')),
        ]);
    }

    public function destroy(User $manager): JsonResponse
    {
        abort_unless($manager->role === 'manager', 404);

        $manager->delete();

        return response()->json([
            'message' => 'Manager deleted successfully',
        ]);
    }
}
