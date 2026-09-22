<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\OvertimeBudget;
use App\Models\Section;
use Illuminate\Database\Seeder;

class OvertimeBudgetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentYear = 2026;
        $currentMonth = 9; // September 2026

        $departments = Department::with('sections')->get();

        foreach ($departments as $dept) {
            $hourlyRate = (float) $dept->default_hourly_rate;

            // 1. Department-level aggregate budget (section_id = null)
            $deptPlannedHours = 800.00;
            $deptPlannedCost = round($deptPlannedHours * $hourlyRate, 2);

            // Department-level category breakdown (A=Produksi 55%, B=TPM 5%, C=Project 25%, D=Others 15%)
            OvertimeBudget::updateOrCreate(
                [
                    'department_id' => $dept->id,
                    'section_id' => null,
                    'fiscal_year' => $currentYear,
                    'fiscal_month' => $currentMonth,
                ],
                [
                    'planned_hours' => $deptPlannedHours,
                    'planned_cost_idr' => $deptPlannedCost,
                    'planned_production_hours' => 440.00,
                    'planned_tpm_hours' => 40.00,
                    'planned_project_hours' => 200.00,
                    'planned_others_hours' => 120.00,
                    'week1_planned_hours' => 160.00,
                    'week2_planned_hours' => 180.00,
                    'week3_planned_hours' => 170.00,
                    'week4_planned_hours' => 190.00,
                    'week5_planned_hours' => 100.00,
                ],
            );

            // 2. Section-level budgets for each section in the department
            foreach ($dept->sections as $section) {
                $secPlannedHours = 200.00;
                $secPlannedCost = round($secPlannedHours * $hourlyRate, 2);

                OvertimeBudget::updateOrCreate(
                    [
                        'department_id' => $dept->id,
                        'section_id' => $section->id,
                        'fiscal_year' => $currentYear,
                        'fiscal_month' => $currentMonth,
                    ],
                    [
                        'planned_hours' => $secPlannedHours,
                        'planned_cost_idr' => $secPlannedCost,
                        'planned_production_hours' => 110.00,
                        'planned_tpm_hours' => 10.00,
                        'planned_project_hours' => 50.00,
                        'planned_others_hours' => 30.00,
                        'week1_planned_hours' => 40.00,
                        'week2_planned_hours' => 45.00,
                        'week3_planned_hours' => 45.00,
                        'week4_planned_hours' => 45.00,
                        'week5_planned_hours' => 25.00,
                    ],
                );
            }
        }
    }
}
