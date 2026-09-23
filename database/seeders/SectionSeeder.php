<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Section;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Canonical plant org (Cannon / ISUZU Karawang).
     */
    public function run(): void
    {
        $departments = Department::pluck('id', 'code');

        $sections = [
            // Planning Control & Delivery
            [
                'department_code' => 'DEPT_PCD',
                'code' => 'SEC_PCD_MAIN',
                'name' => 'Planning Control & Delivery',
                'is_active' => true,
            ],
            // Production
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_BODY_NS_A',
                'name' => 'Body NS A',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_BODY_NS_B',
                'name' => 'Body NS B',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_BODY_FS_A',
                'name' => 'Body FS A',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_BODY_FS_B',
                'name' => 'Body FS B',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_PAINT_A',
                'name' => 'Paint Shop A',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_PAINT_B',
                'name' => 'Paint Shop B',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_MODIFY',
                'name' => 'Modify',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_TCAB_KIT_NS_A',
                'name' => 'TCAB & KIT NS A',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_TCAB_KIT_NS_B',
                'name' => 'TCAB & KIT NS B',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_TCAB_KIT_FS_A',
                'name' => 'TCAB & KIT FS A',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_TCAB_KIT_FS_B',
                'name' => 'TCAB & KIT FS B',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_TCF_NS_A',
                'name' => 'TCF NS A',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_TCF_NS_B',
                'name' => 'TCF NS B',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_TCF_FS_A',
                'name' => 'TCF FS A',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PROD',
                'code' => 'SEC_PROD_TCF_FS_B',
                'name' => 'TCF FS B',
                'is_active' => true,
            ],
            // Quality Control
            [
                'department_code' => 'DEPT_QC',
                'code' => 'SEC_QC_MAIN',
                'name' => 'Quality Control',
                'is_active' => true,
            ],
            // Warehouse & Inventory
            [
                'department_code' => 'DEPT_WI',
                'code' => 'SEC_WI_MAIN',
                'name' => 'Warehouse & Inventory',
                'is_active' => true,
            ],
        ];

        foreach ($sections as $sec) {
            $departmentId = $departments[$sec['department_code']] ?? null;
            if ($departmentId) {
                Section::updateOrCreate(
                    ['code' => $sec['code']],
                    [
                        'department_id' => $departmentId,
                        'name' => $sec['name'],
                        'is_active' => $sec['is_active'],
                    ],
                );
            }
        }
    }
}
