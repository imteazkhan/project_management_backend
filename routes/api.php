<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DesignationController;
use App\Http\Controllers\Api\EmployeesController;
use App\Http\Controllers\Api\ManagerController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAllDevices']);
        Route::post('refresh-token', [AuthController::class, 'refreshToken']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware('auth:sanctum')->get('/user', function (\Illuminate\Http\Request $request) {
    $user = $request->user()->load('employee');

    $data = $user->toArray();
    $data['employee'] = $user->employee ? [
        'id' => $user->employee->id,
        'full_name' => $user->employee->full_name,
        'email' => $user->employee->email,
    ] : null;

    return response()->json($data);
});

Route::middleware('auth:sanctum')->group(function () {
    // Login-account management (Users & Manager modules) is admin-only end
    // to end — managers/employees never see who else has a login.
    Route::middleware('role:admin')->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);

        Route::get('managers', [ManagerController::class, 'index']);
        Route::get('managers/{manager}', [ManagerController::class, 'show']);
        Route::post('managers', [ManagerController::class, 'store']);
        Route::put('managers/{manager}', [ManagerController::class, 'update']);
        Route::delete('managers/{manager}', [ManagerController::class, 'destroy']);
    });

    // Team membership: admins and managers can both create/edit teams and
    // need a minimal user list to populate the manager/member pickers, but
    // this stays separate from the admin-only Users module below. The
    // static "assignable-users" route must be registered before the
    // "teams/{team}" wildcard below so it isn't swallowed by the binding.
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('teams/assignable-users', [TeamController::class, 'assignableUsers']);
        Route::post('teams', [TeamController::class, 'store']);
        Route::put('teams/{team}', [TeamController::class, 'update']);
    });

    // Departments, designations and teams are shared reference data —
    // every signed-in role can read them, only admins manage the lists.
    Route::middleware('role:admin,manager,employee')->group(function () {
        Route::get('departments', [DepartmentController::class, 'index']);
        Route::get('departments/{department}', [DepartmentController::class, 'show']);

        Route::get('designations', [DesignationController::class, 'index']);
        Route::get('designations/{designation}', [DesignationController::class, 'show']);

        Route::get('teams', [TeamController::class, 'index']);
        Route::get('teams/{team}', [TeamController::class, 'show']);
    });

    // Employee directory: admins manage it, managers can browse it (to pick
    // team members). Individual employees don't get the full company
    // directory.
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('employees', [EmployeesController::class, 'index']);
        Route::get('employees/{employee}', [EmployeesController::class, 'show']);
    });

    // Self-service profile: every signed-in role can view and edit their own
    // contact details (name/email/phone/avatar). Organisational fields stay
    // admin-managed through the employees endpoints above.
    Route::middleware('role:admin,manager,employee')->group(function () {
        Route::get('profile', [EmployeesController::class, 'me']);
        Route::post('profile', [EmployeesController::class, 'updateMe']);
    });

    // All writes on reference/organisational data stay admin-only.
    Route::middleware('role:admin')->group(function () {
        Route::post('departments', [DepartmentController::class, 'store']);
        Route::put('departments/{department}', [DepartmentController::class, 'update']);
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy']);

        Route::post('designations', [DesignationController::class, 'store']);
        Route::put('designations/{designation}', [DesignationController::class, 'update']);
        Route::delete('designations/{designation}', [DesignationController::class, 'destroy']);

        Route::delete('teams/{team}', [TeamController::class, 'destroy']);

        Route::post('employees', [EmployeesController::class, 'store']);
        Route::put('employees/{employee}', [EmployeesController::class, 'update']);
        Route::delete('employees/{employee}', [EmployeesController::class, 'destroy']);
    });
});
