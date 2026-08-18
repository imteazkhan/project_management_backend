<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Manager\StoreManagerRequest;
use App\Http\Requests\Api\Manager\UpdateManagerRequest;
use App\Http\Resources\Api\ManagerResource;
use App\Models\Manager;
use Illuminate\Http\JsonResponse;

class ManagerController extends Controller
{
    public function index(): JsonResponse
    {
        $managers = Manager::with(['user', 'department'])->latest()->get();

        return response()->json([
            'managers' => ManagerResource::collection($managers),
        ]);
    }

    public function store(StoreManagerRequest $request): JsonResponse
    {
        $manager = Manager::create($request->validated());

        return response()->json([
            'message' => 'Manager created successfully',
            'manager' => new ManagerResource($manager->load(['user', 'department'])),
        ], 201);
    }

    public function show(Manager $manager): JsonResponse
    {
        return response()->json([
            'manager' => new ManagerResource($manager->load(['user', 'department'])),
        ]);
    }

    public function update(UpdateManagerRequest $request, Manager $manager): JsonResponse
    {
        $manager->update($request->validated());

        return response()->json([
            'message' => 'Manager updated successfully',
            'manager' => new ManagerResource($manager->load(['user', 'department'])),
        ]);
    }

    public function destroy(Manager $manager): JsonResponse
    {
        $manager->delete();

        return response()->json([
            'message' => 'Manager deleted successfully',
        ]);
    }
}
