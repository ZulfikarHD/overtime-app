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
            $table->enum('role', ['admin', 'manager', 'team_leader', 'user'])->default('user')->after('password');
            $table->string('npk', 20)->nullable()->unique()->after('role');
            $table->foreignId('department_id')->nullable()->after('npk')->constrained('departments')->nullOnDelete();
            $table->foreignId('section_id')->nullable()->after('department_id')->constrained('sections')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('section_id');

            $table->index(['role', 'is_active'], 'idx_users_role_active');
            $table->index(['department_id', 'section_id'], 'idx_users_dept_sec');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['section_id']);
            $table->dropIndex('idx_users_role_active');
            $table->dropIndex('idx_users_dept_sec');
            $table->dropColumn(['role', 'npk', 'department_id', 'section_id', 'is_active']);
        });
    }
};
