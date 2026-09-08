<?php

namespace App\Services\Analytics;

use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use Illuminate\Support\Carbon;

class BurnIndexCalculatorService
{
    /**
     * Executes CALC-01 through CALC-08 according to the Business Specification.
     *
     * @return array{
     *     planned_hours: float,
     *     actual_hours: float,
     *     remaining_hours: float,
     *     burn_index_pct: float,
     *     velocity_weekly: float,
     *     projected_total_hours: float,
     *     trajectory: 'on_pace'|'trending_over'|'will_overrun',
     *     opex_hours: float,
     *     capex_hours: float,
     *     capex_ratio_pct: float,
     *     opex_ratio_pct: float,
     *     burn_zone: 'ZONE_1_EXCELLENT'|'ZONE_2_GOOD'|'ZONE_3_WARNING'|'ZONE_4_POOR',
     *     cumulative_cost_idr: float,
     *     is_budget_configured: bool
     * }
     */
    public function calculateSectionMetrics(int $sectionId, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta')->startOfMonth()->toDateString();
        $endDate = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta')->endOfMonth()->toDateString();

        // 1. Fetch Planned Quota
        $budget = OvertimeBudget::query()
            ->where('section_id', $sectionId)
            ->where('fiscal_year', $year)
            ->where('fiscal_month', $month)
            ->first();

        $plannedHours = $budget ? (float) $budget->planned_hours : 0.0;

        // 2. Fetch Cumulative Realized Hours from Approved Items
        $metrics = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_submissions.section_id', $sectionId)
            ->whereBetween('overtime_submissions.operational_date', [$startDate, $endDate])
            ->where('overtime_items.status', 'APPROVED')
            ->selectRaw('
                COALESCE(SUM(overtime_items.hours_production), 0) as total_prod,
                COALESCE(SUM(overtime_items.hours_tpm), 0) as total_tpm,
                COALESCE(SUM(overtime_items.hours_project), 0) as total_project,
                COALESCE(SUM(overtime_items.hours_others), 0) as total_others,
                COALESCE(SUM(overtime_items.total_hours), 0) as cumulative_hours,
                COALESCE(SUM(overtime_items.total_cost_snapshot), 0) as cumulative_cost_idr
            ')
            ->first();

        $actualHours = (float) ($metrics->cumulative_hours ?? 0.0);
        $capexHours = (float) ($metrics->total_project ?? 0.0);
        $opexHours = (float) (($metrics->total_prod ?? 0.0) + ($metrics->total_tpm ?? 0.0) + ($metrics->total_others ?? 0.0));

        // CALC-03: Burn Index (%)
        $burnIndex = $plannedHours > 0
            ? round(($actualHours / $plannedHours) * 100, 2)
            : 0.0;

        // CALC-04: Remaining Budget Hours
        $remainingHours = round($plannedHours - $actualHours, 2);

        // CALC-05: Weekly Burn Velocity using Asia/Jakarta timezone
        $now = Carbon::now('Asia/Jakarta');
        if ($now->year === $year && $now->month === $month) {
            $elapsedWeeks = max(1.0, round($now->day / 7.0, 1));
        } elseif ($now->year > $year || ($now->year === $year && $now->month > $month)) {
            // Closed / Historical month default
            $elapsedWeeks = 4.3;
        } else {
            // Future month
            $elapsedWeeks = 1.0;
        }

        $velocity = round($actualHours / $elapsedWeeks, 2);
        $projectedTotal = round($velocity * 4.3, 1);

        // Trajectory Indicator
        if ($plannedHours <= 0) {
            $trajectory = $actualHours > 0 ? 'will_overrun' : 'on_pace';
        } else {
            $projectedRatio = ($projectedTotal / $plannedHours) * 100;
            $trajectory = match (true) {
                $projectedRatio <= 100.0 => 'on_pace',
                $projectedRatio <= 120.0 => 'trending_over',
                default => 'will_overrun',
            };
        }

        // CALC-08: CapEx vs OpEx Labor Ratio (%)
        $capexRatio = $actualHours > 0 ? round(($capexHours / $actualHours) * 100, 2) : 0.0;
        $opexRatio = round(100.0 - $capexRatio, 2);

        // Budget Control Matrix: Evaluate 4-Quadrant Zone
        $isHighBurn = $burnIndex > 100.0;
        $isHighHours = $plannedHours > 0 && $actualHours >= ($plannedHours * 0.75);

        $zone = match (true) {
            $plannedHours <= 0 && $actualHours <= 0 => 'ZONE_1_EXCELLENT',
            $plannedHours <= 0 && $actualHours > 0 => 'ZONE_4_POOR',
            ! $isHighBurn && ! $isHighHours => 'ZONE_1_EXCELLENT',
            ! $isHighBurn && $isHighHours => 'ZONE_2_GOOD',
            $isHighBurn && ! $isHighHours => 'ZONE_3_WARNING',
            default => 'ZONE_4_POOR',
        };

        return [
            'planned_hours' => $plannedHours,
            'actual_hours' => $actualHours,
            'remaining_hours' => $remainingHours,
            'burn_index_pct' => $burnIndex,
            'velocity_weekly' => $velocity,
            'projected_total_hours' => $projectedTotal,
            'trajectory' => $trajectory,
            'opex_hours' => $opexHours,
            'capex_hours' => $capexHours,
            'capex_ratio_pct' => $capexRatio,
            'opex_ratio_pct' => $opexRatio,
            'burn_zone' => $zone,
            'cumulative_cost_idr' => (float) ($metrics->cumulative_cost_idr ?? 0.0),
            'is_budget_configured' => $plannedHours > 0,
        ];
    }
}
