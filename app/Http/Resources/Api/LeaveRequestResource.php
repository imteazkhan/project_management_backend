<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
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
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->employee?->full_name ?? $this->user->name,
            ] : null),
            'type' => $this->type,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'days' => (float) $this->days,
            'is_half_day' => $this->is_half_day,
            'reason' => $this->reason,
            'status' => $this->status,
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
            ] : null),
            'manager_action' => $this->manager_action,
            'manager_decided_at' => $this->manager_decided_at,
            'manager_note' => $this->manager_note,
            'decider' => $this->whenLoaded('decider', fn () => $this->decider ? [
                'id' => $this->decider->id,
                'name' => $this->decider->name,
            ] : null),
            'decided_at' => $this->decided_at,
            'decision_note' => $this->decision_note,
            'applied_at' => $this->created_at,
        ];
    }
}
