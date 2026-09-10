<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveQuotaController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['quotas' => $this->format()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sick' => ['required', 'integer', 'min:0'],
            'casual' => ['required', 'integer', 'min:0'],
            'annual' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data as $type => $days) {
            LeaveQuota::updateOrCreate(['type' => $type], ['days' => $days]);
        }

        return response()->json(['quotas' => $this->format()]);
    }

    private function format(): array
    {
        $quotas = LeaveQuota::whereIn('type', LeaveQuota::TYPES)->pluck('days', 'type');

        return collect(LeaveQuota::TYPES)->mapWithKeys(
            fn (string $type) => [$type => (int) ($quotas[$type] ?? 0)],
        )->all();
    }
}
