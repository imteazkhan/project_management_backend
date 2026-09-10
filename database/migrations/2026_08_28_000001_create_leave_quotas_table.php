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
        Schema::create('leave_quotas', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->unsignedSmallInteger('days');
            $table->timestamps();
        });

        // Company-wide default quotas per leave type, editable by admins.
        // Unpaid leave has no quota row — it's always unlimited.
        $now = now();
        \DB::table('leave_quotas')->insert([
            ['type' => 'sick', 'days' => 14, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'casual', 'days' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'annual', 'days' => 12, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_quotas');
    }
};
