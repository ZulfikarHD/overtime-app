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
        Schema::create('overtime_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            $table->foreignId('section_id')->nullable()->constrained('sections')->onDelete('restrict');
            $table->smallInteger('fiscal_year');
            $table->smallInteger('fiscal_month');
            $table->decimal('planned_hours', 8, 2);
            $table->decimal('planned_cost_idr', 15, 2)->default(0.00);
            $table->decimal('week1_planned_hours', 8, 2)->default(0.00);
            $table->decimal('week2_planned_hours', 8, 2)->default(0.00);
            $table->decimal('week3_planned_hours', 8, 2)->default(0.00);
            $table->decimal('week4_planned_hours', 8, 2)->default(0.00);
            $table->decimal('week5_planned_hours', 8, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['department_id', 'section_id', 'fiscal_year', 'fiscal_month'], 'uq_budget_period');
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'mysql'])) {
            DB::statement('ALTER TABLE overtime_budgets ADD CONSTRAINT chk_fiscal_month CHECK (fiscal_month BETWEEN 1 AND 12)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_budgets');
    }
};
