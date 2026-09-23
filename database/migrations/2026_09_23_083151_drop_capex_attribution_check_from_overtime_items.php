<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop BR-08 CapEx attribution check so project hours may exist without a CapEx project.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            try {
                DB::statement('ALTER TABLE overtime_items DROP CHECK chk_capex_attribution');
            } catch (\Throwable) {
                try {
                    DB::statement('ALTER TABLE overtime_items DROP CONSTRAINT chk_capex_attribution');
                } catch (\Throwable) {
                    // Constraint already absent (fresh install without BR-08 check).
                }
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement(
                'ALTER TABLE overtime_items ADD CONSTRAINT chk_capex_attribution CHECK ((hours_project = 0.00) OR (hours_project > 0.00 AND capex_project_id IS NOT NULL))'
            );
        }
    }
};
