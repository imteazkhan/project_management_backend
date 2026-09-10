<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('present_hours', 4, 1)->default(6);
            $table->decimal('half_day_hours', 4, 1)->default(3);
            $table->time('office_start_time')->default('09:30:00');
            $table->timestamps();
        });

        // Singleton settings row.
        \DB::table('attendance_settings')->insert([
            'present_hours' => 6,
            'half_day_hours' => 3,
            'office_start_time' => '09:30:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
