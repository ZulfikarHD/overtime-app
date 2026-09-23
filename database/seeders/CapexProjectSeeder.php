<?php

namespace Database\Seeders;

use App\Models\CapexProject;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CapexProjectSeeder extends Seeder
{
    /**
     * Seed CapEx project master data for each plant department.
     */
    public function run(): void
    {
        $year = (int) Carbon::now('Asia/Jakarta')->format('Y');
        $startOfYear = Carbon::create($year, 1, 1, 0, 0, 0, 'Asia/Jakarta')->toDateString();
        $targetEnd = Carbon::create($year, 12, 31, 0, 0, 0, 'Asia/Jakarta')->toDateString();

        $projects = [
            [
                'department_code' => 'DEPT_PROD',
                'project_code' => "CPX-{$year}-PROD-001",
                'asset_code' => 'AST-PROD-2101',
                'name' => 'Otomasi Feeder Robot Body NS Line',
                'allocated_labor_hours' => 500.00,
                'allocated_labor_budget_idr' => 25000000.00,
                'physical_progress_pct' => 35.00,
                'status' => 'ACTIVE',
            ],
            [
                'department_code' => 'DEPT_PROD',
                'project_code' => "CPX-{$year}-PROD-002",
                'asset_code' => 'AST-PROD-1804',
                'name' => 'Upgrading Sealer Robot Paint Shop A',
                'allocated_labor_hours' => 800.00,
                'allocated_labor_budget_idr' => 40000000.00,
                'physical_progress_pct' => 55.00,
                'status' => 'ACTIVE',
            ],
            [
                'department_code' => 'DEPT_PCD',
                'project_code' => "CPX-{$year}-PCD-001",
                'asset_code' => 'AST-PCD-0902',
                'name' => 'Digitalisasi Planning Control Dashboard',
                'allocated_labor_hours' => 350.00,
                'allocated_labor_budget_idr' => 17500000.00,
                'physical_progress_pct' => 20.00,
                'status' => 'ACTIVE',
            ],
            [
                'department_code' => 'DEPT_QC',
                'project_code' => "CPX-{$year}-QC-001",
                'asset_code' => 'AST-QC-0403',
                'name' => 'Instalasi CMM Inspection Cell',
                'allocated_labor_hours' => 200.00,
                'allocated_labor_budget_idr' => 12000000.00,
                'physical_progress_pct' => 100.00,
                'status' => 'COMPLETED',
            ],
            [
                'department_code' => 'DEPT_WI',
                'project_code' => "CPX-{$year}-WI-001",
                'asset_code' => 'AST-WI-0701',
                'name' => 'Retrofit Rack System Warehouse',
                'allocated_labor_hours' => 280.00,
                'allocated_labor_budget_idr' => 14000000.00,
                'physical_progress_pct' => 70.00,
                'status' => 'ACTIVE',
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
