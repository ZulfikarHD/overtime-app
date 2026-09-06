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
        Schema::create('monthly_burn_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('section_id')->constrained('sections')->onDelete('cascade');
            $table->smallInteger('fiscal_year');
            $table->smallInteger('fiscal_month');
            $table->decimal('planned_budget_hours', 8, 2)->default(0.00);
            $table->decimal('cumulative_actual_hours', 8, 2)->default(0.00);
            $table->decimal('cumulative_opex_hours', 8, 2)->default(0.00);
            $table->decimal('cumulative_capex_hours', 8, 2)->default(0.00);
            $table->decimal('burn_index_pct', 6, 2)->default(0.00);
            $table->decimal('burn_velocity', 6, 2)->default(0.00);
            $table->enum('burn_zone', [
                'ZONE_1_EXCELLENT',
                'ZONE_2_GOOD',
                'ZONE_3_WARNING',
                'ZONE_4_POOR',
            ])->default('ZONE_1_EXCELLENT');
            $table->timestamp('last_recalculated_at')->useCurrent();

            $table->unique(['section_id', 'fiscal_year', 'fiscal_month'], 'uq_monthly_section_burn');
            $table->index(['department_id', 'fiscal_year', 'fiscal_month'], 'idx_burn_snapshot_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_burn_snapshots');
    }
};
