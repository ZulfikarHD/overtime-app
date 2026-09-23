<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Canonical plant org (Cannon / ISUZU Karawang).
     */
    public function run(): void
    {
        $departments = [
            [
                'code' => 'DEPT_PCD',
                'name' => 'Planning Control & Delivery',
                'cost_center_code' => 'CC-PCD-003',
                'default_hourly_rate' => 47000.00,
                'is_active' => true,
            ],
            [
                'code' => 'DEPT_PROD',
                'name' => 'Production',
                'cost_center_code' => 'CC-PROD-001',
                'default_hourly_rate' => 45000.00,
                'is_active' => true,
            ],
            [
                'code' => 'DEPT_QC',
                'name' => 'Quality Control',
                'cost_center_code' => 'CC-QC-004',
                'default_hourly_rate' => 47000.00,
                'is_active' => true,
            ],
            [
                'code' => 'DEPT_WI',
                'name' => 'Warehouse & Inventory',
                'cost_center_code' => 'CC-WI-005',
                'default_hourly_rate' => 43000.00,
                'is_active' => true,
            ],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(['code' => $dept['code']], $dept);
        }
    }
}
