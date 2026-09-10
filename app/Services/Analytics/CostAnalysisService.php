<?php

namespace App\Services\Analytics;

use App\Models\Department;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class CostAnalysisService
{
    /**
     * Indonesian month abbreviations for chart labels.
     *
     * @var array<int, string>
     */
    protected const MONTH_NAMES_ID = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    /**
     * Retrieve complete cost analysis dataset for Story E09-08.
     *
     * @return array{
     *     kpi: array{
     *         total_cost: float,
     *         formatted_total_cost: string,
     *         planned_budget: float,
     *         formatted_planned_budget: string,
     *         remaining_budget: float,
     *         formatted_remaining_budget: string,
     *         budget_consumption_pct: float,
     *         has_budget: bool,
     *         avg_cost_per_employee: float,
     *         formatted_avg_cost_per_employee: string,
     *         active_employee_count: int,
     *         capex_cost: float,
     *         formatted_capex_cost: string,
     *         opex_cost: float,
     *         formatted_opex_cost: string,
     *         capex_ratio_pct: float
     *     },
     *     department_costs: list<array{
     *         department_id: int,
     *         department_code: string,
     *         department_name: string,
     *         total_hours: float,
     *         total_cost: float,
     *         formatted_total_cost: string,
     *         planned_cost: float,
     *         formatted_planned_cost: string,
     *         budget_consumption_pct: float,
     *         is_over_budget: bool,
     *         burn_zone: 'safe'|'on_track'|'warning'|'danger',
     *         avg_rate_per_hour: float,
     *         formatted_avg_rate: string,
     *         capex_cost: float,
     *         opex_cost: float,
     *         trend: 'up'|'down'|'stable',
     *         trend_variance_pct: float
     *     }>,
     *     monthly_trend_6m: array{
     *         labels: list<string>,
     *         opex_series: list<float>,
     *         capex_series: list<float>,
     *         total_series: list<float>
     *     },
     *     budget_vs_actual: array{
     *         labels: list<string>,
     *         planned_series: list<float>,
     *         actual_series: list<float>,
     *         variance_series: list<float>,
     *         is_section_breakdown: bool
     *     },
     *     scope: array{
     *         department_id: int|null,
     *         department_name: string,
     *         start_date: string,
     *         end_date: string,
     *         fiscal_year: int,
     *         fiscal_month: int
     *     }
     * }
     */
    public function getCostData(User $user, ?int $departmentId, ?string $startDate, ?string $endDate): array
    {
        // Enforce role-based department scoping
        if ($user->isManager()) {
            $departmentId = $user->department_id ? (int) $user->department_id : null;
        }

        $now = Carbon::now('Asia/Jakarta');
        $start = $startDate ? Carbon::parse($startDate, 'Asia/Jakarta')->startOfDay() : $now->copy()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate, 'Asia/Jakarta')->endOfDay() : $now->copy()->endOfMonth();

        $fiscalYear = (int) $start->year;
        $fiscalMonth = (int) $start->month;

        $targetDept = $departmentId ? Department::find($departmentId) : null;
        $departmentName = $targetDept?->name ?? 'Semua Departemen (Lintas Pabrik)';

        // 1. Department Breakdown & Totals
        $departments = Department::query()
            ->where('is_active', true)
            ->when($departmentId, fn (Builder $q) => $q->where('id', $departmentId))
            ->orderBy('name')
            ->get();

        $departmentCosts = [];
        $grandTotalCost = 0.0;
        $grandTotalHours = 0.0;
        $grandPlannedBudget = 0.0;
        $grandCapexCost = 0.0;
        $grandOpexCost = 0.0;

        // Previous month date range for trend calculation
        $prevMonthStart = $start->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $prevMonthEnd = $start->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();

        foreach ($departments as $dept) {
            $currentMetrics = $this->queryDepartmentPeriodMetrics((int) $dept->id, $start->toDateString(), $end->toDateString());
            $prevMetrics = $this->queryDepartmentPeriodMetrics((int) $dept->id, $prevMonthStart, $prevMonthEnd);

            $plannedBudget = (float) OvertimeBudget::query()
                ->where('department_id', $dept->id)
                ->where('fiscal_year', $fiscalYear)
                ->where('fiscal_month', $fiscalMonth)
                ->sum('planned_cost_idr');

            $consumptionPct = $plannedBudget > 0.0
                ? round(($currentMetrics['total_cost'] / $plannedBudget) * 100, 1)
                : 0.0;

            $isOverBudget = $plannedBudget > 0.0 && $currentMetrics['total_cost'] > $plannedBudget;

            // Burn zone classification
            $burnZone = 'safe';
            if ($consumptionPct > 115.0) {
                $burnZone = 'danger';
            } elseif ($consumptionPct > 100.0) {
                $burnZone = 'warning';
            } elseif ($consumptionPct >= 85.0) {
                $burnZone = 'on_track';
            }

            // MoM trend calculation
            $prevCost = $prevMetrics['total_cost'];
            $trend = 'stable';
            $trendVariancePct = 0.0;
            if ($prevCost > 0.0) {
                $trendVariancePct = round((($currentMetrics['total_cost'] - $prevCost) / $prevCost) * 100, 1);
                if ($trendVariancePct > 5.0) {
                    $trend = 'up';
                } elseif ($trendVariancePct < -5.0) {
                    $trend = 'down';
                }
            } elseif ($currentMetrics['total_cost'] > 0.0) {
                $trend = 'up';
                $trendVariancePct = 100.0;
            }

            $avgRate = $currentMetrics['total_hours'] > 0.0
                ? round($currentMetrics['total_cost'] / $currentMetrics['total_hours'], 2)
                : (float) ($dept->default_hourly_rate ?? 0.0);

            $departmentCosts[] = [
                'department_id' => (int) $dept->id,
                'department_code' => (string) $dept->code,
                'department_name' => (string) $dept->name,
                'total_hours' => round($currentMetrics['total_hours'], 1),
                'total_cost' => round($currentMetrics['total_cost'], 2),
                'formatted_total_cost' => $this->formatCurrencyCompact($currentMetrics['total_cost']),
                'planned_cost' => round($plannedBudget, 2),
                'formatted_planned_cost' => $this->formatCurrencyCompact($plannedBudget),
                'budget_consumption_pct' => $consumptionPct,
                'is_over_budget' => $isOverBudget,
                'burn_zone' => $burnZone,
                'avg_rate_per_hour' => $avgRate,
                'formatted_avg_rate' => $this->formatCurrencyFull($avgRate),
                'capex_cost' => round($currentMetrics['capex_cost'], 2),
                'opex_cost' => round($currentMetrics['opex_cost'], 2),
                'trend' => $trend,
                'trend_variance_pct' => $trendVariancePct,
            ];

            $grandTotalCost += $currentMetrics['total_cost'];
            $grandTotalHours += $currentMetrics['total_hours'];
            $grandPlannedBudget += $plannedBudget;
            $grandCapexCost += $currentMetrics['capex_cost'];
            $grandOpexCost += $currentMetrics['opex_cost'];
        }

        // Sort department costs descending by total_cost
        usort($departmentCosts, fn (array $a, array $b) => $b['total_cost'] <=> $a['total_cost']);

        // 2. Distinct active employee count for average cost calculation
        $activeEmployeeCountQuery = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_items.status', 'APPROVED')
            ->whereBetween('overtime_submissions.operational_date', [$start->toDateString(), $end->toDateString()]);

        if ($departmentId !== null) {
            $activeEmployeeCountQuery->where('overtime_submissions.department_id', $departmentId);
        }

        $activeEmployeeCount = (int) $activeEmployeeCountQuery->distinct('overtime_items.employee_id')->count('overtime_items.employee_id');

        $avgCostPerEmployee = $activeEmployeeCount > 0
            ? round($grandTotalCost / $activeEmployeeCount, 2)
            : 0.0;

        $remainingBudget = max(0.0, round($grandPlannedBudget - $grandTotalCost, 2));
        $grandConsumptionPct = $grandPlannedBudget > 0.0
            ? round(($grandTotalCost / $grandPlannedBudget) * 100, 1)
            : 0.0;

        $capexRatioPct = $grandTotalCost > 0.0
            ? round(($grandCapexCost / $grandTotalCost) * 100, 1)
            : 0.0;

        // 3. 6-Month Stacked Historical Trend (OpEx vs CapEx)
        $monthlyTrend6m = $this->calculate6MonthCostTrend($departmentId, $start);

        // 4. Budget vs Actual Bar Chart Data
        $budgetVsActual = $this->calculateBudgetVsActual($departmentId, $fiscalYear, $fiscalMonth, $departmentCosts);

        return [
            'kpi' => [
                'total_cost' => round($grandTotalCost, 2),
                'formatted_total_cost' => $this->formatCurrencyCompact($grandTotalCost),
                'planned_budget' => round($grandPlannedBudget, 2),
                'formatted_planned_budget' => $this->formatCurrencyCompact($grandPlannedBudget),
                'remaining_budget' => $remainingBudget,
                'formatted_remaining_budget' => $this->formatCurrencyCompact($remainingBudget),
                'budget_consumption_pct' => $grandConsumptionPct,
                'has_budget' => $grandPlannedBudget > 0.0,
                'avg_cost_per_employee' => $avgCostPerEmployee,
                'formatted_avg_cost_per_employee' => $this->formatCurrencyCompact($avgCostPerEmployee),
                'active_employee_count' => $activeEmployeeCount,
                'capex_cost' => round($grandCapexCost, 2),
                'formatted_capex_cost' => $this->formatCurrencyCompact($grandCapexCost),
                'opex_cost' => round($grandOpexCost, 2),
                'formatted_opex_cost' => $this->formatCurrencyCompact($grandOpexCost),
                'capex_ratio_pct' => $capexRatioPct,
            ],
            'department_costs' => $departmentCosts,
            'monthly_trend_6m' => $monthlyTrend6m,
            'budget_vs_actual' => $budgetVsActual,
            'scope' => [
                'department_id' => $departmentId,
                'department_name' => $departmentName,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'fiscal_year' => $fiscalYear,
                'fiscal_month' => $fiscalMonth,
            ],
        ];
    }

    /**
     * Query overtime metrics (hours, total cost, capex cost, opex cost) for a department over a date range.
     *
     * @return array{total_hours: float, total_cost: float, capex_cost: float, opex_cost: float}
     */
    protected function queryDepartmentPeriodMetrics(int $departmentId, string $startDate, string $endDate): array
    {
        $items = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_items.status', 'APPROVED')
            ->where('overtime_submissions.department_id', $departmentId)
            ->whereBetween('overtime_submissions.operational_date', [$startDate, $endDate])
            ->selectRaw('
                COALESCE(SUM(overtime_items.total_hours), 0) as total_hours,
                COALESCE(SUM(overtime_items.total_cost_snapshot), 0) as total_cost,
                COALESCE(SUM(
                    CASE
                        WHEN overtime_items.capex_project_id IS NOT NULL OR overtime_items.hours_project > 0
                        THEN (overtime_items.hours_project * overtime_items.hourly_rate_snapshot)
                        ELSE 0
                    END
                ), 0) as capex_cost
            ')
            ->first();

        $totalHours = (float) ($items?->total_hours ?? 0.0);
        $totalCost = (float) ($items?->total_cost ?? 0.0);
        $capexCost = (float) ($items?->capex_cost ?? 0.0);
        $opexCost = max(0.0, $totalCost - $capexCost);

        return [
            'total_hours' => $totalHours,
            'total_cost' => $totalCost,
            'capex_cost' => $capexCost,
            'opex_cost' => $opexCost,
        ];
    }

    /**
     * Calculate 6-month historical monthly cost trend for OpEx and CapEx.
     *
     * @return array{
     *     labels: list<string>,
     *     opex_series: list<float>,
     *     capex_series: list<float>,
     *     total_series: list<float>
     * }
     */
    protected function calculate6MonthCostTrend(?int $departmentId, Carbon $referenceDate): array
    {
        $labels = [];
        $opexSeries = [];
        $capexSeries = [];
        $totalSeries = [];

        // 6 months ending at reference date
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = $referenceDate->copy()->subMonthsNoOverflow($i);
            $monthNum = (int) $monthDate->month;
            $monthYear = (int) $monthDate->year;
            $monthLabel = self::MONTH_NAMES_ID[$monthNum] ?? $monthDate->format('M');
            if ($i === 5 || $monthNum === 1) {
                $monthLabel .= ' '.$monthDate->format('y');
            }

            $monthStart = $monthDate->copy()->startOfMonth()->toDateString();
            $monthEnd = $monthDate->copy()->endOfMonth()->toDateString();

            $query = OvertimeItem::query()
                ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
                ->where('overtime_items.status', 'APPROVED')
                ->whereBetween('overtime_submissions.operational_date', [$monthStart, $monthEnd]);

            if ($departmentId !== null) {
                $query->where('overtime_submissions.department_id', $departmentId);
            }

            $metrics = $query->selectRaw('
                COALESCE(SUM(overtime_items.total_cost_snapshot), 0) as total_cost,
                COALESCE(SUM(
                    CASE
                        WHEN overtime_items.capex_project_id IS NOT NULL OR overtime_items.hours_project > 0
                        THEN (overtime_items.hours_project * overtime_items.hourly_rate_snapshot)
                        ELSE 0
                    END
                ), 0) as capex_cost
            ')->first();

            $total = (float) ($metrics?->total_cost ?? 0.0);
            $capex = (float) ($metrics?->capex_cost ?? 0.0);
            $opex = max(0.0, $total - $capex);

            $labels[] = $monthLabel;
            $opexSeries[] = round($opex, 2);
            $capexSeries[] = round($capex, 2);
            $totalSeries[] = round($total, 2);
        }

        return [
            'labels' => $labels,
            'opex_series' => $opexSeries,
            'capex_series' => $capexSeries,
            'total_series' => $totalSeries,
        ];
    }

    /**
     * Calculate Budget vs Actual comparisons.
     * If all departments, groups by department.
     * If single department, groups by sections within that department.
     *
     * @param  list<array<string, mixed>>  $departmentCosts
     * @return array{
     *     labels: list<string>,
     *     planned_series: list<float>,
     *     actual_series: list<float>,
     *     variance_series: list<float>,
     *     is_section_breakdown: bool
     * }
     */
    protected function calculateBudgetVsActual(?int $departmentId, int $fiscalYear, int $fiscalMonth, array $departmentCosts): array
    {
        $labels = [];
        $plannedSeries = [];
        $actualSeries = [];
        $varianceSeries = [];
        $isSectionBreakdown = false;

        if ($departmentId !== null) {
            // Department-scoped: breakdown by sections
            $isSectionBreakdown = true;
            $sections = Section::where('department_id', $departmentId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $now = Carbon::createFromDate($fiscalYear, $fiscalMonth, 1, 'Asia/Jakarta');
            $start = $now->copy()->startOfMonth()->toDateString();
            $end = $now->copy()->endOfMonth()->toDateString();

            foreach ($sections as $section) {
                $actualCost = (float) OvertimeItem::query()
                    ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
                    ->where('overtime_items.status', 'APPROVED')
                    ->where('overtime_submissions.section_id', $section->id)
                    ->whereBetween('overtime_submissions.operational_date', [$start, $end])
                    ->sum('overtime_items.total_cost_snapshot');

                $plannedCost = (float) OvertimeBudget::query()
                    ->where('section_id', $section->id)
                    ->where('fiscal_year', $fiscalYear)
                    ->where('fiscal_month', $fiscalMonth)
                    ->sum('planned_cost_idr');

                $labels[] = (string) $section->name;
                $plannedSeries[] = round($plannedCost, 2);
                $actualSeries[] = round($actualCost, 2);
                $varianceSeries[] = round($actualCost - $plannedCost, 2);
            }
        } else {
            // Plant-wide: breakdown by departments
            foreach ($departmentCosts as $dept) {
                $labels[] = (string) $dept['department_name'];
                $plannedSeries[] = (float) $dept['planned_cost'];
                $actualSeries[] = (float) $dept['total_cost'];
                $varianceSeries[] = round((float) $dept['total_cost'] - (float) $dept['planned_cost'], 2);
            }
        }

        return [
            'labels' => $labels,
            'planned_series' => $plannedSeries,
            'actual_series' => $actualSeries,
            'variance_series' => $varianceSeries,
            'is_section_breakdown' => $isSectionBreakdown,
        ];
    }

    /**
     * Format number as compact Indonesian Rupiah (e.g. Rp 125,5 Jt).
     */
    protected function formatCurrencyCompact(float $amount): string
    {
        if ($amount <= 0.0) {
            return 'Rp 0';
        }

        if ($amount >= 1_000_000_000) {
            $val = round($amount / 1_000_000_000, 1);

            return 'Rp '.number_format($val, 1, ',', '.').' M';
        }

        if ($amount >= 1_000_000) {
            $val = round($amount / 1_000_000, 1);

            return 'Rp '.number_format($val, 1, ',', '.').' Jt';
        }

        if ($amount >= 1_000) {
            $val = round($amount / 1_000, 1);

            return 'Rp '.number_format($val, 1, ',', '.').' Rb';
        }

        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * Format number as full Indonesian Rupiah (e.g. Rp 125.500.000).
     */
    protected function formatCurrencyFull(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
