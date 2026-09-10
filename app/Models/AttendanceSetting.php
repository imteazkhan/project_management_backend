<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $fillable = [
        'present_hours',
        'half_day_hours',
        'office_start_time',
    ];

    // Singleton row (id 1), created by its migration.
    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1], [
            'present_hours' => 6,
            'half_day_hours' => 3,
            'office_start_time' => '09:30:00',
        ]);
    }
}
