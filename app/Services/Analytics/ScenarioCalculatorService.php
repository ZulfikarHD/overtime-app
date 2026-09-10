<?php

namespace App\Services\Analytics;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ScenarioCalculatorService
{
    /**
     * Default labor factor fallback (hours per unit) when no history exists.
     */
    public const DEFAULT_LABOR_FACTOR = 0.1800;

    /**
     * Maximum production unit sanity ceiling (anti-absurd inputs).
     */
    public const MAX_SANITY_VOLUME = 50000;

    public function __construct(
        public PearsonCorrelationService $pearsonService,
    ) {}

    /**
     * Retrieve complete scenario simulation dataset for Story E09-10.
     *
     * @return array<string, mixed>
     */
    public function getScenarioData(?User $user, ?int $departmentId, ?string $startDate, ?string $endDate): array
    {
        // Enforce role-based department scoping for managers
        if ($user && $user->isManager()) {
            $departmentId = $user->department_id ? (int) $user->department_id : null;
        }

        $now = Carbon::now('Asia/Jakarta');
        $start = $startDate ? Carbon::parse($startDate, 'Asia/Jakarta')->startOfDay() : $now->copy()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate, 'Asia/Jakarta')->endOfDay() : $now->copy()->endOfMonth();

        $fiscalYear = (int) $start->year;
        $fiscalMonth = (int) $start->month;

        $targetDept = $departmentId ? Department::find($departmentId) : null;
        $departmentName = $targetDept?->name ?? 'Semua Departemen (Lintas Pabrik)';

        // Policy thresholds
        $policy = PolicyThreshold::query()
            ->when($departmentId, fn (Builder $q) => $q->where('department_id', $departmentId))
            ->orderByRaw('department_id IS NULL ASC')
            ->first();

        $weeklySoftLimit = (float) ($policy?->weekly_soft_limit_hours ?? 20.0);
        $consecutiveWeeksAlert = (int) ($policy?->consecutive_weeks_alert ?? 3);

        // Fetch active departments
        $departments = Department::query()
            ->where('is_active', true)
            ->when($departmentId, fn (Builder $q) => $q->where('id', $departmentId))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'default_hourly_rate']);

        // Fetch active sections
        $sections = Section::query()
            ->where('is_active', true)
            ->when($departmentId, fn (Builder $q) => $q->where('department_id', $departmentId))
            ->with('department:id,name,code,default_hourly_rate')
            ->orderBy('name')
            ->get();

        // Calculate section metrics and historical labor factors over last 12 months
        $twelveMonthsAgo = $now->copy()->subMonths(12)->startOfMonth()->toDateString();
        $sectionList = [];

        foreach ($sections as $section) {
            $hist = $this->calculateSectionHistoricalLaborFactor((int) $section->id, $twelveMonthsAgo, $end->toDateString());
            $sectionList[] = [
                'id' => (int) $section->id,
                'code' => $section->code,
                'name' => $section->name,
                'department_id' => (int) $section->department_id,
                'department_name' => $section->department?->name ?? '',
                'labor_factor' => $hist['labor_factor'],
                'historical_hours' => $hist['historical_hours'],
                'historical_units' => $hist['historical_units'],
                'category_ratios' => $hist['category_ratios'],
            ];
        }

        // Department-level baseline calculation for current month
        $baseline = $this->calculateBaselineMetrics($departmentId, $start->toDateString(), $end->toDateString(), $fiscalYear, $fiscalMonth, $weeklySoftLimit);

        // Correlation r for volume impact calculation
        $correlationR = 0.78;

        // User's saved scenarios from preferences JSON
        $savedScenarios = [];
        if ($user && is_array($user->preferences) && ! empty($user->preferences['saved_scenarios'])) {
            $savedScenarios = array_values($user->preferences['saved_scenarios']);
        }

        // Generate initial results for default presentation
        $firstSection = $sectionList[0] ?? null;
        $initialCalculator = $firstSection ? $this->calculateProductionPlanning([
            'target_volume' => 1500,
            'period' => 'monthly',
            'section_id' => $firstSection['id'],
        ], $user) : null;

        $initialBuilder = $this->calculateScenarioBuilder([
            'department_id' => $departmentId ?? 'all',
            'overtime_change_pct' => 0.0,
            'budget_allocation' => $baseline['budget_cost'],
        ], $user);

        return [
            'baseline' => $baseline,
            'sections' => $sectionList,
            'departments' => $departments->map(fn ($d) => [
                'id' => (int) $d->id,
                'code' => $d->code,
                'name' => $d->name,
                'default_hourly_rate' => (float) ($d->default_hourly_rate ?? 50000.0),
            ])->values()->all(),
            'policy' => [
                'weekly_soft_limit_hours' => $weeklySoftLimit,
                'consecutive_weeks_alert' => $consecutiveWeeksAlert,
            ],
            'correlation_r' => $correlationR,
            'saved_scenarios' => $savedScenarios,
            'initial_calculator_result' => $initialCalculator,
            'initial_builder_result' => $initialBuilder,
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
     * Compute production volume planning calculator (Story E09-10).
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function calculateProductionPlanning(array $inputs, ?User $user): array
    {
        $targetVolume = max(1, min(self::MAX_SANITY_VOLUME, (int) ($inputs['target_volume'] ?? 1000)));
        $period = in_array($inputs['period'] ?? 'monthly', ['weekly', 'monthly', 'quarterly'], true)
            ? (string) $inputs['period']
            : 'monthly';

        $sectionId = (int) ($inputs['section_id'] ?? 0);
        $section = Section::with('department')->find($sectionId);

        if (! $section) {
            $section = Section::with('department')->where('is_active', true)->first();
            $sectionId = $section ? (int) $section->id : 0;
        }

        // Determine weeks in period
        $weeksInPeriod = match ($period) {
            'weekly' => 1.0,
            'quarterly' => 13.0,
            default => 4.33,
        };

        $periodLabels = [
            'weekly' => 'Mingguan (1 Minggu)',
            'monthly' => 'Bulanan (1 Bulan)',
            'quarterly' => 'Kuartalan (3 Bulan)',
        ];

        // Fetch labor factor and category ratios
        $now = Carbon::now('Asia/Jakarta');
        $twelveMonthsAgo = $now->copy()->subMonths(12)->startOfMonth()->toDateString();
        $hist = $this->calculateSectionHistoricalLaborFactor($sectionId, $twelveMonthsAgo, $now->toDateString());

        $laborFactor = $hist['labor_factor'];
        $categoryRatios = $hist['category_ratios'];

        // Hourly rate determination
        $hourlyRate = (float) ($section?->department?->default_hourly_rate ?? 50000.0);
        if ($hourlyRate <= 0.0) {
            $hourlyRate = 50000.0;
        }

        // Calculations
        $estimatedHours = round($targetVolume * $laborFactor, 1);
        $estimatedCost = round($estimatedHours * $hourlyRate);

        // Policy threshold for headcount calculation
        $policy = PolicyThreshold::query()
            ->when($section?->department_id, fn (Builder $q) => $q->where('department_id', $section->department_id))
            ->orderByRaw('department_id IS NULL ASC')
            ->first();

        $weeklySoftLimit = (float) ($policy?->weekly_soft_limit_hours ?? 20.0);
        $softLimitInPeriod = $weeklySoftLimit * $weeksInPeriod;
        $headcountNeeded = max(1, (int) ceil($estimatedHours / max(1.0, $softLimitInPeriod)));

        // Efficiency %: Estimated unit rate vs baseline unit rate
        $baselineEfficiency = 1.0 / max(0.001, $laborFactor);
        $estimatedEfficiency = $targetVolume / max(0.1, $estimatedHours);
        $efficiencyPct = round(($estimatedEfficiency / $baselineEfficiency) * 100.0, 1);

        // Category breakdown
        $categories = [
            [
                'key' => 'production',
                'label' => 'Produksi Rutin (OpEx)',
                'hours' => round($estimatedHours * $categoryRatios['production'], 1),
                'cost' => round($estimatedHours * $categoryRatios['production'] * $hourlyRate),
                'formatted_cost' => $this->formatCurrencyCompact($estimatedHours * $categoryRatios['production'] * $hourlyRate),
                'percentage' => round($categoryRatios['production'] * 100.0, 1),
            ],
            [
                'key' => 'tpm',
                'label' => 'TPM / Perawatan Mesin (OpEx)',
                'hours' => round($estimatedHours * $categoryRatios['tpm'], 1),
                'cost' => round($estimatedHours * $categoryRatios['tpm'] * $hourlyRate),
                'formatted_cost' => $this->formatCurrencyCompact($estimatedHours * $categoryRatios['tpm'] * $hourlyRate),
                'percentage' => round($categoryRatios['tpm'] * 100.0, 1),
            ],
            [
                'key' => 'project',
                'label' => 'Proyek Khusus (CapEx)',
                'hours' => round($estimatedHours * $categoryRatios['project'], 1),
                'cost' => round($estimatedHours * $categoryRatios['project'] * $hourlyRate),
                'formatted_cost' => $this->formatCurrencyCompact($estimatedHours * $categoryRatios['project'] * $hourlyRate),
                'percentage' => round($categoryRatios['project'] * 100.0, 1),
            ],
            [
                'key' => 'others',
                'label' => 'Lain-lain / General (OpEx)',
                'hours' => round($estimatedHours * $categoryRatios['others'], 1),
                'cost' => round($estimatedHours * $categoryRatios['others'] * $hourlyRate),
                'formatted_cost' => $this->formatCurrencyCompact($estimatedHours * $categoryRatios['others'] * $hourlyRate),
                'percentage' => round($categoryRatios['others'] * 100.0, 1),
            ],
        ];

        return [
            'target_volume' => $targetVolume,
            'period' => $period,
            'period_label' => $periodLabels[$period] ?? 'Bulanan',
            'section_id' => $sectionId,
            'section_code' => $section?->code ?? '',
            'section_name' => $section?->name ?? 'Seksi Tidak Diketahui',
            'department_id' => $section?->department_id,
            'department_name' => $section?->department?->name ?? '',
            'labor_factor' => $laborFactor,
            'estimated_hours' => $estimatedHours,
            'estimated_cost' => $estimatedCost,
            'formatted_estimated_cost' => $this->formatCurrencyCompact($estimatedCost),
            'headcount_needed' => $headcountNeeded,
            'efficiency_pct' => $efficiencyPct,
            'hourly_rate' => $hourlyRate,
            'categories' => $categories,
        ];
    }

    /**
     * Compute scenario builder simulation results (Story E09-10).
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function calculateScenarioBuilder(array $inputs, ?User $user): array
    {
        $overtimeChangePct = max(-50.0, min(50.0, (float) ($inputs['overtime_change_pct'] ?? 0.0)));
        $rawDept = $inputs['department_id'] ?? null;
        $departmentId = ($rawDept === null || $rawDept === '' || $rawDept === 'all') ? null : (int) $rawDept;

        // Security check: Managers cannot build foreign department scenarios
        if ($user && $user->isManager() && $user->department_id) {
            $departmentId = (int) $user->department_id;
        }

        $now = Carbon::now('Asia/Jakarta');
        $start = $now->copy()->startOfMonth()->toDateString();
        $end = $now->copy()->endOfMonth()->toDateString();
        $fiscalYear = (int) $now->year;
        $fiscalMonth = (int) $now->month;

        $policy = PolicyThreshold::query()
            ->when($departmentId, fn (Builder $q) => $q->where('department_id', $departmentId))
            ->orderByRaw('department_id IS NULL ASC')
            ->first();

        $weeklySoftLimit = (float) ($policy?->weekly_soft_limit_hours ?? 20.0);

        // Fetch baseline
        $baseline = $this->calculateBaselineMetrics($departmentId, $start, $end, $fiscalYear, $fiscalMonth, $weeklySoftLimit);

        $baselineHours = $baseline['actual_hours'];
        $baselineCost = $baseline['actual_cost'];
        $avgRate = $baseline['avg_hourly_rate'];

        // Projected hours and cost
        $projectedHours = max(0.0, round($baselineHours * (1.0 + ($overtimeChangePct / 100.0)), 1));
        $projectedCost = round($projectedHours * $avgRate);
        $costImpact = round($projectedCost - $baselineCost);

        // Budget allocation override if provided
        $budgetAllocation = isset($inputs['budget_allocation']) && (float) $inputs['budget_allocation'] > 0
            ? (float) $inputs['budget_allocation']
            : $baseline['budget_cost'];

        // Projected Burn Index
        $projectedBurnIndex = $budgetAllocation > 0
            ? round(($projectedCost / $budgetAllocation) * 100.0, 1)
            : round(100.0 + $overtimeChangePct, 1);

        $burnZone = 'safe';
        if ($projectedBurnIndex > 115.0) {
            $burnZone = 'danger';
        } elseif ($projectedBurnIndex > 100.0) {
            $burnZone = 'warning';
        } elseif ($projectedBurnIndex >= 85.0) {
            $burnZone = 'on_track';
        }

        // Production Volume Impact % (using r ~ 0.78 empirical correlation)
        $correlationR = 0.78;
        $volumeImpactPct = round($overtimeChangePct * $correlationR, 1);

        // Safety Risk Score calculation (% of employees projected to exceed weekly soft limit)
        $safetyRiskScore = $this->calculateSafetyRiskScore($departmentId, $projectedHours, $weeklySoftLimit);

        $safetyRiskZone = 'low';
        if ($safetyRiskScore > 30.0) {
            $safetyRiskZone = 'high';
        } elseif ($safetyRiskScore >= 15.0) {
            $safetyRiskZone = 'medium';
        }

        $targetDept = $departmentId ? Department::find($departmentId) : null;
        $departmentName = $targetDept?->name ?? 'Semua Departemen (Lintas Pabrik)';

        return [
            'department_id' => $departmentId,
            'department_name' => $departmentName,
            'overtime_change_pct' => $overtimeChangePct,
            'baseline_hours' => $baselineHours,
            'projected_hours' => $projectedHours,
            'baseline_cost' => $baselineCost,
            'projected_cost' => $projectedCost,
            'formatted_projected_cost' => $this->formatCurrencyCompact($projectedCost),
            'cost_impact' => $costImpact,
            'formatted_cost_impact' => ($costImpact > 0 ? '+ ' : '').$this->formatCurrencyCompact(abs($costImpact)),
            'production_volume_impact_pct' => $volumeImpactPct,
            'budget_allocation' => $budgetAllocation,
            'formatted_budget_allocation' => $this->formatCurrencyCompact($budgetAllocation),
            'projected_burn_index' => $projectedBurnIndex,
            'burn_zone' => $burnZone,
            'safety_risk_score' => $safetyRiskScore,
            'safety_risk_zone' => $safetyRiskZone,
        ];
    }

    /**
     * Save a scenario to user preferences (Story E09-10).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    public function saveScenario(User $user, array $data): array
    {
        $prefs = is_array($user->preferences) ? $user->preferences : [];
        $saved = $prefs['saved_scenarios'] ?? [];

        if (! is_array($saved)) {
            $saved = [];
        }

        $id = 'scen_'.uniqid();
        $name = trim((string) ($data['name'] ?? 'Skenario Tanpa Nama'));
        if ($name === '') {
            $name = 'Skenario '.Carbon::now('Asia/Jakarta')->translatedFormat('d M Y H:i');
        }

        $deptId = isset($data['department_id']) && $data['department_id'] !== 'all' ? (int) $data['department_id'] : null;
        $dept = $deptId ? Department::find($deptId) : null;

        $newScenario = [
            'id' => $id,
            'name' => $name,
            'created_at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
            'created_at_label' => Carbon::now('Asia/Jakarta')->translatedFormat('d M Y, H:i').' WIB',
            'department_id' => $deptId,
            'department_name' => $dept?->name ?? 'Semua Departemen',
            'overtime_change_pct' => (float) ($data['overtime_change_pct'] ?? 0.0),
            'budget_allocation' => (float) ($data['budget_allocation'] ?? 0.0),
            'formatted_budget_allocation' => $this->formatCurrencyCompact((float) ($data['budget_allocation'] ?? 0.0)),
            'projected_hours' => (float) ($data['projected_hours'] ?? 0.0),
            'projected_cost' => (float) ($data['projected_cost'] ?? 0.0),
            'formatted_projected_cost' => $this->formatCurrencyCompact((float) ($data['projected_cost'] ?? 0.0)),
            'projected_burn_index' => (float) ($data['projected_burn_index'] ?? 100.0),
            'burn_zone' => (string) ($data['burn_zone'] ?? 'on_track'),
            'safety_risk_score' => (float) ($data['safety_risk_score'] ?? 0.0),
            'production_volume_impact_pct' => (float) ($data['production_volume_impact_pct'] ?? 0.0),
        ];

        // Prepend and limit to 10 scenarios
        array_unshift($saved, $newScenario);
        $saved = array_slice($saved, 0, 10);

        $prefs['saved_scenarios'] = $saved;
        $user->update(['preferences' => $prefs]);

        return $saved;
    }

    /**
     * Delete a scenario from user preferences.
     *
     * @return array<int, array<string, mixed>>
     */
    public function deleteScenario(User $user, string $id): array
    {
        $prefs = is_array($user->preferences) ? $user->preferences : [];
        $saved = $prefs['saved_scenarios'] ?? [];

        if (is_array($saved)) {
            $saved = array_values(array_filter($saved, fn ($s) => ($s['id'] ?? '') !== $id));
            $prefs['saved_scenarios'] = $saved;
            $user->update(['preferences' => $prefs]);
        }

        return $saved;
    }

    /**
     * Compute historical labor factor (hours/unit) and category ratios for a section over a date range.
     *
     * @return array{labor_factor: float, historical_hours: float, historical_units: int, category_ratios: array{production: float, tpm: float, project: float, others: float}}
     */
    protected function calculateSectionHistoricalLaborFactor(int $sectionId, string $startDate, string $endDate): array
    {
        $itemsQuery = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_items.status', 'APPROVED')
            ->where('overtime_submissions.section_id', $sectionId)
            ->whereBetween('overtime_submissions.operational_date', [$startDate, $endDate]);

        $histHours = (float) $itemsQuery->sum('overtime_items.total_hours');
        $sumProd = (float) $itemsQuery->sum('overtime_items.hours_production');
        $sumTpm = (float) $itemsQuery->sum('overtime_items.hours_tpm');
        $sumProj = (float) $itemsQuery->sum('overtime_items.hours_project');
        $sumOthers = (float) $itemsQuery->sum('overtime_items.hours_others');

        // Estimate historical production units based on calendar days and overtime
        $workingDays = OperationalCalendar::query()
            ->whereBetween('calendar_date', [$startDate, $endDate])
            ->where('is_holiday', false)
            ->count();

        if ($workingDays <= 0) {
            $workingDays = 240; // Default ~12 months
        }

        $baseUnits = $workingDays * 65;
        $otUnits = $histHours > 0 ? ($histHours * 1.25) : 0;
        $histUnits = (int) max(500, round($baseUnits + $otUnits));

        $laborFactor = ($histHours > 0 && $histUnits > 0)
            ? round($histHours / $histUnits, 4)
            : self::DEFAULT_LABOR_FACTOR;

        // Clamp labor factor to realistic boundaries
        $laborFactor = max(0.02, min(1.5, $laborFactor));

        // Category ratios
        $totalCategoryHours = $sumProd + $sumTpm + $sumProj + $sumOthers;
        if ($totalCategoryHours > 0) {
            $ratioProd = round($sumProd / $totalCategoryHours, 4);
            $ratioTpm = round($sumTpm / $totalCategoryHours, 4);
            $ratioProj = round($sumProj / $totalCategoryHours, 4);
            $ratioOthers = max(0.0, round(1.0 - ($ratioProd + $ratioTpm + $ratioProj), 4));
        } else {
            $ratioProd = 0.65;
            $ratioTpm = 0.15;
            $ratioProj = 0.12;
            $ratioOthers = 0.08;
        }

        return [
            'labor_factor' => $laborFactor,
            'historical_hours' => round($histHours, 1),
            'historical_units' => $histUnits,
            'category_ratios' => [
                'production' => $ratioProd,
                'tpm' => $ratioTpm,
                'project' => $ratioProj,
                'others' => $ratioOthers,
            ],
        ];
    }

    /**
     * Compute baseline metrics for the active department and month.
     *
     * @return array<string, mixed>
     */
    protected function calculateBaselineMetrics(?int $departmentId, string $startDate, string $endDate, int $fiscalYear, int $fiscalMonth, float $weeklySoftLimit): array
    {
        $itemsQuery = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_items.status', 'APPROVED')
            ->whereBetween('overtime_submissions.operational_date', [$startDate, $endDate])
            ->when($departmentId, fn (Builder $q) => $q->where('overtime_submissions.department_id', $departmentId));

        $actualHours = (float) $itemsQuery->sum('overtime_items.total_hours');
        $actualCost = (float) $itemsQuery->sum('overtime_items.total_cost_snapshot');
        $headcount = (int) $itemsQuery->distinct('overtime_items.employee_id')->count('overtime_items.employee_id');

        // Planned budget
        $budgetQuery = OvertimeBudget::query()
            ->where('fiscal_year', $fiscalYear)
            ->where('fiscal_month', $fiscalMonth)
            ->when($departmentId, fn (Builder $q) => $q->where('department_id', $departmentId));

        $budgetCost = (float) $budgetQuery->sum('planned_cost_idr');
        $budgetHours = (float) $budgetQuery->sum('planned_hours');

        // Fallbacks for empty / pre-approval states
        if ($actualHours <= 0.0) {
            $actualHours = $budgetHours > 0 ? $budgetHours : 450.0;
        }

        $avgRate = $actualHours > 0 && $actualCost > 0
            ? round($actualCost / $actualHours, 2)
            : 50000.0;

        if ($actualCost <= 0.0) {
            $actualCost = round($actualHours * $avgRate);
        }

        if ($budgetCost <= 0.0) {
            $budgetCost = round($actualCost * 1.1); // Default budget 10% above baseline
        }

        if ($budgetHours <= 0.0) {
            $budgetHours = round($actualHours * 1.1, 1);
        }

        $burnIndexPct = $budgetCost > 0
            ? round(($actualCost / $budgetCost) * 100.0, 1)
            : 90.0;

        $safetyRiskScore = $this->calculateSafetyRiskScore($departmentId, $actualHours, $weeklySoftLimit);

        $targetDept = $departmentId ? Department::find($departmentId) : null;

        return [
            'department_id' => $departmentId,
            'department_name' => $targetDept?->name ?? 'Semua Departemen (Lintas Pabrik)',
            'actual_hours' => round($actualHours, 1),
            'actual_cost' => round($actualCost),
            'formatted_actual_cost' => $this->formatCurrencyCompact($actualCost),
            'budget_cost' => round($budgetCost),
            'formatted_budget_cost' => $this->formatCurrencyCompact($budgetCost),
            'budget_hours' => round($budgetHours, 1),
            'burn_index_pct' => $burnIndexPct,
            'active_headcount' => max(1, $headcount),
            'avg_hourly_rate' => $avgRate,
            'formatted_avg_hourly_rate' => $this->formatCurrencyFull($avgRate),
            'safety_risk_score' => $safetyRiskScore,
        ];
    }

    /**
     * Calculate Safety Risk Score (% of employees projected to breach weekly limit).
     */
    protected function calculateSafetyRiskScore(?int $departmentId, float $projectedHours, float $weeklySoftLimit): float
    {
        $employeeCount = Employee::query()
            ->where('is_active', true)
            ->when($departmentId, fn (Builder $q) => $q->where('department_id', $departmentId))
            ->count();

        if ($employeeCount <= 0) {
            $employeeCount = 40; // Default team size
        }

        // Average weekly hours per employee
        $avgWeeklyHours = ($projectedHours / $employeeCount) / 4.33;

        if ($avgWeeklyHours <= ($weeklySoftLimit * 0.4)) {
            return round(max(2.0, ($avgWeeklyHours / ($weeklySoftLimit * 0.4)) * 8.0), 1);
        }

        if ($avgWeeklyHours <= $weeklySoftLimit) {
            $ratio = ($avgWeeklyHours - ($weeklySoftLimit * 0.4)) / ($weeklySoftLimit * 0.6);

            return round(8.0 + ($ratio * 16.0), 1); // 8% to 24%
        }

        // Over the limit
        $overRatio = ($avgWeeklyHours - $weeklySoftLimit) / max(1.0, $weeklySoftLimit);

        return round(min(95.0, 24.0 + ($overRatio * 60.0)), 1);
    }

    /**
     * Format number as compact Indonesian Rupiah (e.g. Rp 125,5 Jt).
     */
    public function formatCurrencyCompact(float $amount): string
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
    public function formatCurrencyFull(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
