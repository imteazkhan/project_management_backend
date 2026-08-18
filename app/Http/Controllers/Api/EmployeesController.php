<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Employee\StoreEmployeeRequest;
use App\Http\Requests\Api\Employee\UpdateEmployeeRequest;
use App\Http\Resources\Api\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class EmployeesController extends Controller
{
    public function index(): JsonResponse
    {
        $employees = Employee::with(['department', 'designation', 'manager'])->latest()->get();

        return response()->json([
            'employees' => EmployeeResource::collection($employees),
        ]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        return response()->json([
            'message' => 'Employee created successfully',
            'employee' => new EmployeeResource($employee->load(['department', 'designation', 'manager'])),
        ], 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        return response()->json([
            'employee' => new EmployeeResource($employee->load(['department', 'designation', 'manager'])),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());

        return response()->json([
            'message' => 'Employee updated successfully',
            'employee' => new EmployeeResource($employee->load(['department', 'designation', 'manager'])),
        ]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return response()->json([
            'message' => 'Employee deleted successfully',
        ]);
    }
}
