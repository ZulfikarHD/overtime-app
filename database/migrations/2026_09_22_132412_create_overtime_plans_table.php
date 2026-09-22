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
        Schema::create('overtime_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->onDelete('restrict');
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('fiscal_month'); // 1–12
            $table->string('plan_code', 50)->unique();
            $table->enum('status', ['DRAFT', 'PUBLISHED'])->default('DRAFT');
            $table->foreignId('submitted_by_user_id')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['section_id', 'fiscal_year', 'fiscal_month'], 'uk_plan_section_period');
            $table->index(['fiscal_year', 'fiscal_month', 'department_id'], 'idx_plans_period_dept');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_plans');
    }
};
