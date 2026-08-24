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
        DB::statement("ALTER TABLE tasks MODIFY status ENUM('not_started', 'in_progress', 'submitted', 'completed') NOT NULL DEFAULT 'not_started'");

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress')->default(0)->after('status');
            $table->timestamp('submitted_at')->nullable()->after('due_date');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['progress', 'submitted_at', 'approved_at']);
        });

        DB::statement("ALTER TABLE tasks MODIFY status ENUM('todo', 'in_progress', 'done') NOT NULL DEFAULT 'todo'");
    }
};
