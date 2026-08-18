<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Designation\StoreDesignationRequest;
use App\Http\Requests\Api\Designation\UpdateDesignationRequest;
use App\Http\Resources\Api\DesignationResource;
use App\Models\Designation;
use Illuminate\Http\JsonResponse;

class DesignationController extends Controller
{
    public function index(): JsonResponse
    {
        $designations = Designation::latest()->get();

        return response()->json([
            'designations' => DesignationResource::collection($designations),
        ]);
    }

    public function store(StoreDesignationRequest $request): JsonResponse
    {
        $designation = Designation::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Designation created successfully',
            'designation' => new DesignationResource($designation),
        ], 201);
    }

    public function show(Designation $designation): JsonResponse
    {
        return response()->json([
            'designation' => new DesignationResource($designation),
        ]);
    }

    public function update(UpdateDesignationRequest $request, Designation $designation): JsonResponse
    {
        $designation->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Designation updated successfully',
            'designation' => new DesignationResource($designation),
        ]);
    }

    public function destroy(Designation $designation): JsonResponse
    {
        if ($designation->employees()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a designation that still has employees assigned to it',
            ], 409);
        }

        $designation->delete();

        return response()->json([
            'message' => 'Designation deleted successfully',
        ]);
    }
}
