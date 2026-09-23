<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\PolicyThreshold;
use Illuminate\Database\Seeder;

class PolicyThresholdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Plant-wide Default Threshold (department_id = null)
        PolicyThreshold::updateOrCreate(
            ['department_id' => null],
            [
                'weekly_soft_limit_hours' => 20.0,
                'consecutive_weeks_alert' => 3,
                'spkl_grace_period_days' => 2,
                'burn_warning_pct' => 100.00,
                'burn_danger_pct' => 115.00,
            ],
        );

        // 2. Department-specific threshold overrides
        $deptPcd = Department::where('code', 'DEPT_PCD')->first();
        if ($deptPcd) {
            PolicyThreshold::updateOrCreate(
                ['department_id' => $deptPcd->id],
                [
                    'weekly_soft_limit_hours' => 22.0,
                    'consecutive_weeks_alert' => 3,
                    'spkl_grace_period_days' => 2,
                    'burn_warning_pct' => 100.00,
                    'burn_danger_pct' => 115.00,
                ],
            );
        }

        $deptProd = Department::where('code', 'DEPT_PROD')->first();
        if ($deptProd) {
            PolicyThreshold::updateOrCreate(
                ['department_id' => $deptProd->id],
                [
                    'weekly_soft_limit_hours' => 24.0,
                    'consecutive_weeks_alert' => 3,
                    'spkl_grace_period_days' => 3,
                    'burn_warning_pct' => 105.00,
                    'burn_danger_pct' => 120.00,
                ],
            );
        }
    }
}
