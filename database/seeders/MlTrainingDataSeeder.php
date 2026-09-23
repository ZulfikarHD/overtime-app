<?php

namespace Database\Seeders;

use App\Models\MlForecastInput;
use App\Models\MlTrainingData;
use Illuminate\Database\Seeder;

/**
 * Seeds 30 historical training rows (Jan 2024 – Jun 2026) from the ML reference xlsx,
 * and 6 forecast placeholder rows (Jul – Dec 2026) from the Forecasting sheet.
 */
class MlTrainingDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Training data (Jan 2024 – Jun 2026) ──────────────────────────────
        // Source: docs/references/ml_references.xlsx → Sheet "Data"
        // Y = Total Index Overtime (aktual, hardcode dari Data sheet)
        $training = [
            // year, month, X1 (hari kerja), X2 (volume produksi), X3 (man power), Y (overtime index)
            [2024,  1, 20, 3107, 845,  81305],
            [2024,  2, 22, 2694, 814,  67057],
            [2024,  3, 19, 2211, 798,  39619],
            [2024,  4, 19, 2352, 789,  42895],
            [2024,  5, 16, 2008, 756,  36966],
            [2024,  6, 20, 2659, 790,  59948],
            [2024,  7, 19, 2839, 790,  82171],
            [2024,  8, 22, 3374, 690,  90534],
            [2024,  9, 22, 3208, 690, 100190],
            [2024, 10, 20, 3023, 697,  91437],
            [2024, 11, 23, 3237, 700,  76332],
            [2024, 12, 22, 3014, 699,  62074],
            [2025,  1, 18, 2617, 694,  89619],
            [2025,  2, 20, 2443, 706,  75216],
            [2025,  3, 20, 3004, 706,  78847],
            [2025,  4, 19, 2934, 707,  92254],
            [2025,  5, 17, 2717, 707,  68708],
            [2025,  6, 19, 2960, 708,  91078],
            [2025,  7, 19, 2832, 794,  73434],
            [2025,  8, 23, 3402, 787,  60522],
            [2025,  9, 21, 2747, 775,  44904],
            [2025, 10, 21, 2456, 775,  45540],
            [2025, 11, 22, 2463, 733,  38491],
            [2025, 12, 22, 1998, 729,  29413],
            [2026,  1, 20, 2485, 730,  62983],
            [2026,  2, 20, 2290, 730,  54317],
            [2026,  3, 19, 2173, 730,  62758],
            [2026,  4, 17, 3283, 730,  48410],
            [2026,  5, 21, 2889, 704,  88003],
            [2026,  6, 20, 3698, 714,  84449],
        ];

        foreach ($training as [$year, $month, $wd, $vol, $mp, $ot]) {
            MlTrainingData::updateOrCreate(
                ['year' => $year, 'month' => $month],
                [
                    'working_days' => $wd,
                    'production_volume' => $vol,
                    'man_power' => $mp,
                    'overtime_index' => $ot,
                ],
            );
        }

        // ── Forecast inputs (Jul – Dec 2026) ─────────────────────────────────
        // Source: docs/references/ml_references.xlsx → Sheet "Forecasting"
        // X values as planned by client; actual_overtime_index left null until available.
        $forecast = [
            // year, month, X1, X2, X3
            [2026,  7, 23, 3714, 893],
            [2026,  8, 19, 3340, 893],
            [2026,  9, 22, 4076, 893],
            [2026, 10, 21, 4189, 893],
            [2026, 11, 21, 3781, 893],
            [2026, 12, 22, 3945, 893],
        ];

        foreach ($forecast as [$year, $month, $wd, $vol, $mp]) {
            MlForecastInput::updateOrCreate(
                ['year' => $year, 'month' => $month],
                [
                    'working_days' => $wd,
                    'production_volume' => $vol,
                    'man_power' => $mp,
                    'actual_overtime_index' => null,
                ],
            );
        }
    }
}
