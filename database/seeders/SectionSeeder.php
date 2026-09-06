<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Section;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = Department::pluck('id', 'code');

        $sections = [
            // Stamping
            [
                'department_code' => 'DEPT_STP',
                'code' => 'SEC_STP_BLANK',
                'name' => 'Blanking & Shearing Line',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_STP',
                'code' => 'SEC_STP_PRESS',
                'name' => 'Tandem & Transfer Press Line',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_STP',
                'code' => 'SEC_STP_DIE',
                'name' => 'Die Maintenance & Staging',
                'is_active' => true,
            ],
            // Welding
            [
                'department_code' => 'DEPT_WLD',
                'code' => 'SEC_WLD_UNDER',
                'name' => 'Underbody Robot Welding',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_WLD',
                'code' => 'SEC_WLD_MAIN',
                'name' => 'Main Body Framing Line',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_WLD',
                'code' => 'SEC_WLD_CLOS',
                'name' => 'Closures (Door & Hood) Line',
                'is_active' => true,
            ],
            // Painting
            [
                'department_code' => 'DEPT_PNT',
                'code' => 'SEC_PNT_ED',
                'name' => 'Electrodeposition & Sealer Line',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_PNT',
                'code' => 'SEC_PNT_TOP',
                'name' => 'Topcoat & Clearcoat Spray Line',
                'is_active' => true,
            ],
            // Assembly
            [
                'department_code' => 'DEPT_ASY',
                'code' => 'SEC_ASY_TRIM',
                'name' => 'Trim & Cockpit Installation',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_ASY',
                'code' => 'SEC_ASY_CHAS',
                'name' => 'Chassis & Powertrain Marriage',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_ASY',
                'code' => 'SEC_ASY_FINAL',
                'name' => 'Final Electrical & Interior',
                'is_active' => true,
            ],
            // Quality Assurance
            [
                'department_code' => 'DEPT_QAC',
                'code' => 'SEC_QAC_LINE',
                'name' => 'In-Line Inspection & Tester Line',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_QAC',
                'code' => 'SEC_QAC_AUDIT',
                'name' => 'Static Vehicle Audit & Shower Test',
                'is_active' => true,
            ],
            // Maintenance
            [
                'department_code' => 'DEPT_MNT',
                'code' => 'SEC_MNT_ELEC',
                'name' => 'Electrical & Automation Maintenance',
                'is_active' => true,
            ],
            [
                'department_code' => 'DEPT_MNT',
                'code' => 'SEC_MNT_MECH',
                'name' => 'Mechanical & Hydraulic Maintenance',
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
