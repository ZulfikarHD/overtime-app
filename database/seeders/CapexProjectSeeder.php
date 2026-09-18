<?php

namespace Database\Seeders;

use App\Models\CapexProject;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CapexProjectSeeder extends Seeder
{
    /**
     * Seed CapEx project master data for each production department.
     */
    public function run(): void
    {
        $year = (int) Carbon::now('Asia/Jakarta')->format('Y');
        $startOfYear = Carbon::create($year, 1, 1, 0, 0, 0, 'Asia/Jakarta')->toDateString();
        $targetEnd = Carbon::create($year, 12, 31, 0, 0, 0, 'Asia/Jakarta')->toDateString();

        $projects = [
            [
                'department_code' => 'DEPT_ASY',
                'project_code' => "CPX-{$year}-ASY-001",
                'asset_code' => 'AST-ASY-2101',
                'name' => 'Otomasi Feeder Robot Assy Line 3',
                'allocated_labor_hours' => 500.00,
                'allocated_labor_budget_idr' => 25000000.00,
                'physical_progress_pct' => 35.00,
                'status' => 'ACTIVE',
            ],
            [
                'department_code' => 'DEPT_WLD',
                'project_code' => "CPX-{$year}-WLD-001",
                'asset_code' => 'AST-WLD-1804',
                'name' => 'Pemasangan Lini Robot Welding Underbody',
                'allocated_labor_hours' => 800.00,
                'allocated_labor_budget_idr' => 40000000.00,
                'physical_progress_pct' => 55.00,
                'status' => 'ACTIVE',
            ],
            [
                'department_code' => 'DEPT_STP',
                'project_code' => "CPX-{$year}-STP-001",
                'asset_code' => 'AST-STP-0902',
                'name' => 'Instalasi Jig Stamping Press 500T',
                'allocated_labor_hours' => 350.00,
                'allocated_labor_budget_idr' => 17500000.00,
                'physical_progress_pct' => 20.00,
                'status' => 'ACTIVE',
            ],
            [
                'department_code' => 'DEPT_PNT',
                'project_code' => "CPX-{$year}-PNT-001",
                'asset_code' => 'AST-PNT-1205',
                'name' => 'Upgrading Sealer Robot Paint Line 2',
                'allocated_labor_hours' => 420.00,
                'allocated_labor_budget_idr' => 21000000.00,
                'physical_progress_pct' => 10.00,
                'status' => 'PLANNING',
            ],
            [
                'department_code' => 'DEPT_MNT',
                'project_code' => "CPX-{$year}-MNT-001",
                'asset_code' => 'AST-MNT-0701',
                'name' => 'Retrofit Overhead Crane Workshop',
                'allocated_labor_hours' => 280.00,
                'allocated_labor_budget_idr' => 14000000.00,
                'physical_progress_pct' => 70.00,
                'status' => 'ACTIVE',
            ],
            [
                'department_code' => 'DEPT_QAC',
                'project_code' => "CPX-{$year}-QAC-001",
                'asset_code' => 'AST-QAC-0403',
                'name' => 'Instalasi CMM Inspection Cell',
                'allocated_labor_hours' => 200.00,
                'allocated_labor_budget_idr' => 12000000.00,
                'physical_progress_pct' => 100.00,
                'status' => 'COMPLETED',
            ],
        ];

        foreach ($projects as $project) {
            $department = Department::where('code', $project['department_code'])->first();
            if (! $department) {
                continue;
            }

            CapexProject::updateOrCreate(
                ['project_code' => $project['project_code']],
                [
                    'asset_code' => $project['asset_code'],
                    'name' => $project['name'],
                    'department_id' => $department->id,
                    'allocated_labor_hours' => $project['allocated_labor_hours'],
                    'allocated_labor_budget_idr' => $project['allocated_labor_budget_idr'],
                    'physical_progress_pct' => $project['physical_progress_pct'],
                    'status' => $project['status'],
                    'start_date' => $startOfYear,
                    'target_end_date' => $targetEnd,
                ],
            );
        }
    }
}
