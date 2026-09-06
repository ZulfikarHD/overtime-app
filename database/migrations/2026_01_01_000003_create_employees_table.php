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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('npk', 20)->unique();
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            $table->foreignId('section_id')->constrained('sections')->onDelete('restrict');
            $table->string('full_name', 150);
            $table->string('job_position', 100);
            $table->decimal('hourly_rate', 15, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'])) {
            DB::statement('CREATE INDEX idx_employees_dept_sec ON employees(department_id, section_id) WHERE is_active = TRUE');
        } else {
            Schema::table('employees', function (Blueprint $table) {
                $table->index(['department_id', 'section_id', 'is_active'], 'idx_employees_dept_sec');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
