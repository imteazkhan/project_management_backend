<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar ? Storage::disk('public')->url($this->avatar) : null,
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null),
            'designation' => $this->whenLoaded('designation', fn () => $this->designation ? [
                'id' => $this->designation->id,
                'name' => $this->designation->name,
            ] : null),
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
            ] : null),
            'has_user_account' => $this->whenLoaded('user', fn () => $this->user !== null),
            'joining_date' => $this->joining_date?->format('Y-m-d'),
            'status' => $this->status,
            'is_manager' => $this->is_manager,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
