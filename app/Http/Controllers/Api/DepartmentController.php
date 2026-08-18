<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Department\StoreDepartmentRequest;
use App\Http\Requests\Api\Department\UpdateDepartmentRequest;
use App\Http\Resources\Api\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        $departments = Department::latest()->get();

        return response()->json([
            'departments' => DepartmentResource::collection($departments),
        ]);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Department created successfully',
            'department' => new DepartmentResource($department),
        ], 201);
    }

    public function show(Department $department): JsonResponse
    {
        return response()->json([
            'department' => new DepartmentResource($department),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $department->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Department updated successfully',
            'department' => new DepartmentResource($department),
        ]);
    }

    public function destroy(Department $department): JsonResponse
    {
        if ($department->employees()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a department that still has employees assigned to it',
            ], 409);
        }

        $department->delete();

        return response()->json([
            'message' => 'Department deleted successfully',
        ]);
    }
}
