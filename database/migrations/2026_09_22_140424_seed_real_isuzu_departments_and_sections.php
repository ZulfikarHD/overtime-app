<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the real ISUZU factory departments and sections sourced from
 * docs/references/db_ot.xlsx (db_section sheet).
 *
 * These are additive – existing seeded demo data is not touched.
 * Uses insertOrIgnore so it is idempotent on re-runs.
 */
return new class extends Migration
{
    private const DEPARTMENTS = [
        ['code' => 'DEPT_PROD', 'name' => 'Production', 'cost_center_code' => 'CC-PROD-001', 'default_hourly_rate' => 45000.00, 'is_active' => true],
        ['code' => 'DEPT_MTC', 'name' => 'Maintenance', 'cost_center_code' => 'CC-MTC-002', 'default_hourly_rate' => 48000.00, 'is_active' => true],
        ['code' => 'DEPT_PCD', 'name' => 'Planning, Control & Delivery', 'cost_center_code' => 'CC-PCD-003', 'default_hourly_rate' => 47000.00, 'is_active' => true],
        ['code' => 'DEPT_QC', 'name' => 'Quality Control', 'cost_center_code' => 'CC-QC-004', 'default_hourly_rate' => 47000.00, 'is_active' => true],
        ['code' => 'DEPT_WI', 'name' => 'Warehouse & Inventory', 'cost_center_code' => 'CC-WI-005', 'default_hourly_rate' => 43000.00, 'is_active' => true],
    ];

    /**
     * @var array<array{dept_code: string, code: string, name: string}>
     */
    private const SECTIONS = [
        // Production
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_MAIN', 'name' => 'Production'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_BS_FSER', 'name' => 'Production Sect Body Shop FSer'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_BS_NSER', 'name' => 'Production Sect Body Shop NSer'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_KIT', 'name' => 'Production Sect Kit'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_OFF_NSER', 'name' => 'Production Sect Offline N Ser'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_PS', 'name' => 'Production Sect Paint Shop'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_TCF_FSER', 'name' => 'Production Sect TCF FSer'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_TCF_NSER', 'name' => 'Production Sect TCF NSer'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_TC_FSER', 'name' => 'Production Sect TrimCabFSer'],
        ['dept_code' => 'DEPT_PROD', 'code' => 'SEC_PROD_TC_NSER', 'name' => 'Production Sect TrimCabNSer'],
        // Maintenance
        ['dept_code' => 'DEPT_MTC', 'code' => 'SEC_MTC_MAIN', 'name' => 'Maintenance'],
        // Planning, Control & Delivery
        ['dept_code' => 'DEPT_PCD', 'code' => 'SEC_PCD_MAIN', 'name' => 'Planning, Control & Delivery'],
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
