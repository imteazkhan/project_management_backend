<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Employee\StoreEmployeeRequest;
use App\Http\Requests\Api\Employee\UpdateEmployeeRequest;
use App\Http\Resources\Api\EmployeeResource;
use App\Mail\EmployeeCredentialsMail;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

        $message = 'Employee created successfully';

        if (!User::where('email', $employee->email)->exists()) {
            $password = Str::password(12);

            $user = User::create([
                'name' => $employee->full_name,
                'email' => $employee->email,
                'password' => Hash::make($password),
                'role' => 'employee',
                'employee_id' => $employee->id,
            ]);

            try {
                Mail::to($user->email)->send(new EmployeeCredentialsMail($employee, $password));
                $message .= ' and login credentials emailed to the employee';
            } catch (\Throwable $e) {
                Log::error('Failed to send employee credentials email', [
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage(),
                ]);
                $message .= ', but the credentials email could not be sent';
            }
        }

        return response()->json([
            'message' => $message,
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

    // Self-service profile: any signed-in role can view and edit their own
    // contact details. Organisational fields (department, designation,
    // manager, status) stay admin-managed via the routes above.
    public function me(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        return response()->json([
            'employee' => $employee
                ? new EmployeeResource($employee->load(['department', 'designation', 'manager', 'user']))
                : null,
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json([
                'message' => 'No employee profile is linked to this account.',
            ], 404);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($employee->avatar) {
                Storage::disk('public')->delete($employee->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $employee->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'employee' => new EmployeeResource($employee->load(['department', 'designation', 'manager', 'user'])),
        ]);
    }
}
