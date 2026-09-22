<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add per-overtime-category planned hours columns to overtime_budgets.
     *
     * Categories mirror the OvertimeItem breakdown:
     *   A = Produksi (Production)  → hours_production
     *   B = TPM / Maintenance      → hours_tpm
     *   C = Project / CapEx        → hours_project
     *   D = Lainnya (Others)       → hours_others
     *
     * These are raw planned HOURS (not index-converted) so the dashboard can
     * compare plan vs actual in both raw-hour and index-weighted views.
     */
    public function up(): void
    {
        Schema::table('overtime_budgets', function (Blueprint $table) {
            $table->decimal('planned_production_hours', 10, 2)->default(0.00)->after('planned_cost_idr');
            $table->decimal('planned_tpm_hours', 10, 2)->default(0.00)->after('planned_production_hours');
            $table->decimal('planned_project_hours', 10, 2)->default(0.00)->after('planned_tpm_hours');
            $table->decimal('planned_others_hours', 10, 2)->default(0.00)->after('planned_project_hours');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_budgets', function (Blueprint $table) {
            $table->dropColumn([
                'planned_production_hours',
                'planned_tpm_hours',
                'planned_project_hours',
                'planned_others_hours',
            ]);
        });
    }
};
