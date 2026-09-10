<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;
        $employee = $user?->employee;

        return [
            'id' => $this->id,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $employee?->full_name ?? $user->name,
                'department' => $employee?->department?->name,
                'teams' => $user->teams->pluck('name')->values(),
            ] : null,
            'date' => $this->date?->format('Y-m-d'),
            'check_in_at' => $this->check_in_at,
            'check_out_at' => $this->check_out_at,
            'total_minutes' => $this->total_minutes,
            'status' => $this->status,
        ];
    }
}
