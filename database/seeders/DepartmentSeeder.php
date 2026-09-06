<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'code' => 'DEPT_STP',
                'name' => 'Stamping Department',
                'cost_center_code' => 'CC-STP-101',
                'default_hourly_rate' => 45000.00,
                'is_active' => true,
            ],
            [
                'code' => 'DEPT_WLD',
                'name' => 'Welding Department',
                'cost_center_code' => 'CC-WLD-102',
                'default_hourly_rate' => 46500.00,
                'is_active' => true,
            ],
            [
                'code' => 'DEPT_PNT',
                'name' => 'Painting Department',
                'cost_center_code' => 'CC-PNT-103',
                'default_hourly_rate' => 48000.00,
                'is_active' => true,
            ],
            [
                'code' => 'DEPT_ASY',
                'name' => 'Assembly Department',
                'cost_center_code' => 'CC-ASY-104',
                'default_hourly_rate' => 45000.00,
                'is_active' => true,
            ],
            [
                'code' => 'DEPT_QAC',
                'name' => 'Quality Assurance & Control',
                'cost_center_code' => 'CC-QAC-105',
                'default_hourly_rate' => 47000.00,
                'is_active' => true,
            ],
            [
                'code' => 'DEPT_MNT',
                'name' => 'Plant Maintenance & Facility',
                'cost_center_code' => 'CC-MNT-106',
                'default_hourly_rate' => 50000.00,
                'is_active' => true,
            ],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(['code' => $dept['code']], $dept);
        }
    }
}
