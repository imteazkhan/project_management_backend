<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['settings' => $this->format(AttendanceSetting::current())]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'present_hours' => ['required', 'numeric', 'min:0'],
            'half_day_hours' => ['required', 'numeric', 'min:0'],
            'office_start_time' => ['required', 'date_format:H:i'],
        ]);

        $settings = AttendanceSetting::current();
        $settings->update([
            'present_hours' => $validated['present_hours'],
            'half_day_hours' => $validated['half_day_hours'],
            'office_start_time' => $validated['office_start_time'].':00',
        ]);

        return response()->json(['settings' => $this->format($settings->fresh())]);
    }

    private function format(AttendanceSetting $settings): array
    {
        return [
            'present_hours' => (float) $settings->present_hours,
            'half_day_hours' => (float) $settings->half_day_hours,
            'office_start_time' => substr($settings->office_start_time, 0, 5),
        ];
    }
}
