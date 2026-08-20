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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('role');
            $table->foreignId('department_id')->nullable()->after('avatar')
                ->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->after('department_id')
                ->constrained('designations')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->after('designation_id')
                ->constrained('users')->nullOnDelete();
            $table->date('joining_date')->nullable()->after('manager_id');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('joining_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('designation_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['phone', 'avatar', 'joining_date', 'status']);
        });
    }
};
