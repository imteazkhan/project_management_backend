<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tasks MODIFY status ENUM('not_started', 'in_progress', 'submitted', 'completed', 'rejected', 'paused') NOT NULL DEFAULT 'not_started'");

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('paused_from_status')->nullable()->after('rejection_reason');
            $table->boolean('is_edited')->default(false)->after('paused_from_status');
            $table->boolean('is_added_later')->default(false)->after('is_edited');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['paused_from_status', 'is_edited', 'is_added_later']);
        });

        DB::statement("ALTER TABLE tasks MODIFY status ENUM('not_started', 'in_progress', 'submitted', 'completed', 'rejected') NOT NULL DEFAULT 'not_started'");
    }
};
