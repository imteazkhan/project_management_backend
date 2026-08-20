<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Employee\StoreEmployeeRequest;
use App\Http\Requests\Api\Employee\UpdateEmployeeRequest;
use App\Http\Resources\Api\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployeesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::with(['department', 'designation', 'manager', 'user'])
            ->when($request->boolean('unlinked'), fn ($query) => $query->doesntHave('user'))
            ->when($request->has('is_manager'), fn ($query) => $query->where('is_manager', $request->boolean('is_manager')))
            ->latest()
            ->get();

        return response()->json([
            'employees' => EmployeeResource::collection($employees),
        ]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $employee = Employee::create($data);

        return response()->json([
            'message' => 'Employee created successfully',
            'employee' => new EmployeeResource($employee->load(['department', 'designation', 'manager', 'user'])),
        ], 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        return response()->json([
            'employee' => new EmployeeResource($employee->load(['department', 'designation', 'manager', 'user'])),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            if ($employee->avatar) {
                Storage::disk('public')->delete($employee->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $employee->update($data);

        return response()->json([
            'message' => 'Employee updated successfully',
            'employee' => new EmployeeResource($employee->load(['department', 'designation', 'manager', 'user'])),
        ]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        if ($employee->avatar) {
            Storage::disk('public')->delete($employee->avatar);
        }

        $employee->delete();

        return response()->json([
            'message' => 'Employee deleted successfully',
        ]);
    }
}
