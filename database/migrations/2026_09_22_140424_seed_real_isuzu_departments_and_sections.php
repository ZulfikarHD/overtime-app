<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the real ISUZU factory departments and sections (canonical Cannon org).
 *
 * These are additive – existing seeded demo data is not touched.
 * Uses insertOrIgnore so it is idempotent on re-runs.
 */
return new class extends Migration
{
    private const DEPARTMENTS = [
        ['code' => 'DEPT_PCD', 'name' => 'Planning Control & Delivery', 'cost_center_code' => 'CC-PCD-003', 'default_hourly_rate' => 47000.00, 'is_active' => true],
        ['code' => 'DEPT_PROD', 'name' => 'Production', 'cost_center_code' => 'CC-PROD-001', 'default_hourly_rate' => 45000.00, 'is_active' => true],
        ['code' => 'DEPT_QC', 'name' => 'Quality Control', 'cost_center_code' => 'CC-QC-004', 'default_hourly_rate' => 47000.00, 'is_active' => true],
        ['code' => 'DEPT_WI', 'name' => 'Warehouse & Inventory', 'cost_center_code' => 'CC-WI-005', 'default_hourly_rate' => 43000.00, 'is_active' => true],
    ];

    /**
     * @var array<array{dept_code: string, code: string, name: string}>
     */
    private const SECTIONS = [
        // Planning Control & Delivery
        ['dept_code' => 'DEPT_PCD', 'code' => 'SEC_PCD_MAIN', 'name' => 'Planning Control & Delivery'],
        // Production
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_BODY_NS_A', 'name' => 'Body NS A'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_BODY_NS_B', 'name' => 'Body NS B'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_BODY_FS_A', 'name' => 'Body FS A'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_BODY_FS_B', 'name' => 'Body FS B'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_PAINT_A', 'name' => 'Paint Shop A'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_PAINT_B', 'name' => 'Paint Shop B'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_MODIFY', 'name' => 'Modify'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_TCAB_KIT_NS_A', 'name' => 'TCAB & KIT NS A'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_TCAB_KIT_NS_B', 'name' => 'TCAB & KIT NS B'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_TCAB_KIT_FS_A', 'name' => 'TCAB & KIT FS A'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_TCAB_KIT_FS_B', 'name' => 'TCAB & KIT FS B'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_TCF_NS_A', 'name' => 'TCF NS A'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_TCF_NS_B', 'name' => 'TCF NS B'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_TCF_FS_A', 'name' => 'TCF FS A'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_TCF_FS_B', 'name' => 'TCF FS B'],
        // Quality Control
        ['dept_code' => 'DEPT_QC', 'code' => 'SEC_QC_MAIN', 'name' => 'Quality Control'],
        // Warehouse & Inventory
        ['dept_code' => 'DEPT_WI', 'code' => 'SEC_WI_MAIN', 'name' => 'Warehouse & Inventory'],
    ];

    public function up(): void
    {
        $now = now();

        // --- Departments ---
        foreach (self::DEPARTMENTS as $dept) {
            DB::table('departments')->insertOrIgnore([
                'code' => $dept['code'],
                'name' => $dept['name'],
                'cost_center_code' => $dept['cost_center_code'],
                'default_hourly_rate' => $dept['default_hourly_rate'],
                'is_active' => $dept['is_active'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // --- Sections ---
        $deptIds = DB::table('departments')->pluck('id', 'code');

        foreach (self::SECTIONS as $sec) {
            $deptId = $deptIds[$sec['dept_code']] ?? null;
            if (! $deptId) {
                continue;
            }

            DB::table('sections')->insertOrIgnore([
                'department_id' => $deptId,
                'code' => $sec['code'],
                'name' => $sec['name'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Remove sections first (FK child)
        $codes = array_column(self::SECTIONS, 'code');
        DB::table('sections')->whereIn('code', $codes)->delete();

        $deptCodes = array_column(self::DEPARTMENTS, 'code');
        DB::table('departments')->whereIn('code', $deptCodes)->delete();
    }
};
