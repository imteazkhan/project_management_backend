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
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 4, 1);
            $table->boolean('is_half_day')->default(false);
            $table->text('reason');
            $table->string('status')->default('pending');

            // First-stage approval by the employee's team manager — null
            // when the employee has no manager, in which case the request
            // skips straight to the admin's final decision.
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('manager_action')->nullable();
            $table->timestamp('manager_decided_at')->nullable();
            $table->string('manager_note')->nullable();

            // Final decision, made by an admin (or by whoever actually
            // approved/rejected it when there was no manager stage).
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
