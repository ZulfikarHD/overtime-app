<?php

namespace App\Services\Analytics;

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\MlPrediction;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MonthlySnapshotService
{
    public function __construct(
        public BurnIndexCalculatorService $calculator,
    ) {}

    /**
     * Retrieve an existing snapshot or recalculate and store it if missing or forced.
     */
    public function getOrRecalculate(int $sectionId, int $year, int $month, bool $force = false): ?MonthlyBurnSnapshot
    {
        if (! $force) {
            $snapshot = MonthlyBurnSnapshot::query()
                ->with(['department', 'section'])
                ->where('section_id', $sectionId)
                ->where('fiscal_year', $year)
                ->where('fiscal_month', $month)
                ->first();

            if ($snapshot) {
                return $snapshot;
            }
        }

        return $this->recalculate($sectionId, $year, $month);
    }

    /**
     * Execute live calculation and upsert the result into monthly_burn_snapshots.
     */
    public function recalculate(int $sectionId, int $year, int $month): ?MonthlyBurnSnapshot
    {
        $section = Section::find($sectionId);
        if (! $section) {
            return null;
        }

        $metrics = $this->calculator->calculateSectionMetrics($sectionId, $year, $month);

        /** @var MonthlyBurnSnapshot $snapshot */
        $snapshot = MonthlyBurnSnapshot::updateOrCreate(
            [
                'section_id' => $sectionId,
                'fiscal_year' => $year,
                'fiscal_month' => $month,
            ],
            [
                'department_id' => $section->department_id,
                'planned_budget_hours' => $metrics['planned_hours'],
                'cumulative_actual_hours' => $metrics['actual_hours'],
                'cumulative_opex_hours' => $metrics['opex_hours'],
                'cumulative_capex_hours' => $metrics['capex_hours'],
                'burn_index_pct' => $metrics['burn_index_pct'],
                'burn_velocity' => $metrics['velocity_weekly'],
                'burn_zone' => $metrics['burn_zone'],
                'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
            ]
        );

        return $snapshot->load(['department', 'section']);
    }

    /**
     * Recalculate snapshots across sections.
     */
    public function recalculateAll(int $year, int $month, ?int $departmentId = null): void
    {
        $sectionsQuery = Section::where('is_active', true);

        if ($departmentId !== null) {
            $sectionsQuery->where('department_id', $departmentId);
        }

        $sections = $sectionsQuery->get(['id']);

        foreach ($sections as $section) {
            $this->recalculate($section->id, $year, $month);
        }
    }

    /**
     * Build the consolidated dashboard data scoped to the authenticated user.
     *
     * @return array{
     *     departments: list<array{id: int, code: string, name: string}>,
     *     selected_department: array{id: int, code: string, name: string}|null,
     *     fiscal_year: int,
     *     fiscal_month: int,
     *     current_tab: string,
     *     snapshots: list<array<string, mixed>>,
     *     summary: array{
     *         total_planned_hours: float,
     *         total_actual_hours: float,
     *         total_remaining_hours: float,
     *         department_burn_index_pct: float,
     *         department_burn_zone: string,
     *         warning_sections_count: int,
     *         danger_sections_count: int,
     *         configured_sections_count: int,
     *         total_sections_count: int
     *     }
     * }
     */
    public function getDashboardData(
        User $user,
        int $year,
        int $month,
        ?int $departmentId = null,
        string $tab = 'sections',
        string $rangeType = 'month',
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        // 1. Resolve accessible departments based on role
        if ($user->isManager() || $user->isTeamLeader()) {
            $departmentsQuery = Department::where('id', $user->department_id)->where('is_active', true);
            $targetDeptId = (int) $user->department_id;
        } else {
            $departmentsQuery = Department::where('is_active', true)->orderBy('code');
            if ($departmentId === 0 || $departmentId === -1) {
                $targetDeptId = null;
            } elseif ($departmentId !== null) {
                $targetDeptId = (int) $departmentId;
            } else {
                $targetDeptId = Department::where('is_active', true)->orderBy('code')->value('id');
            }
        }

        /** @var list<array{id: int, code: string, name: string}> $departments */
        $departments = $departmentsQuery->get(['id', 'code', 'name'])->toArray();

        $selectedDepartment = null;
        if ($targetDeptId) {
            $deptModel = Department::find($targetDeptId);
            if ($deptModel) {
                $selectedDepartment = [
                    'id' => $deptModel->id,
                    'code' => $deptModel->code,
                    'name' => $deptModel->name,
                ];
            }
        }

        // 2. Fetch target sections scoped to role
        if ($user->isTeamLeader() && $user->section_id) {
            $sections = Section::where('id', $user->section_id)
                ->where('is_active', true)
                ->with('department')
                ->get();
        } elseif ($targetDeptId) {
            $sections = Section::where('department_id', $targetDeptId)
                ->where('is_active', true)
                ->orderBy('code')
                ->with('department')
                ->get();
        } elseif ($user->isAdmin()) {
            $sections = Section::where('is_active', true)
                ->orderBy('department_id')
                ->orderBy('code')
                ->with('department')
                ->get();
        } else {
            $sections = collect();
        }

        if ($sections->isEmpty()) {
            return [
                'departments' => $departments,
                'selected_department' => $selectedDepartment,
                'fiscal_year' => $year,
                'fiscal_month' => $month,
                'current_tab' => $tab,
                'snapshots' => [],
                'summary' => [
                    'total_planned_hours' => 0.0,
                    'total_actual_hours' => 0.0,
                    'total_remaining_hours' => 0.0,
                    'department_burn_index_pct' => 0.0,
                    'department_burn_zone' => 'ZONE_1_EXCELLENT',
                    'warning_sections_count' => 0,
                    'danger_sections_count' => 0,
                    'configured_sections_count' => 0,
                    'total_sections_count' => 0,
                ],
                'departments_summary' => [],
                'capex_opex' => [
                    'summary' => [
                        'total_hours' => 0.0,
                        'capex_hours' => 0.0,
                        'opex_hours' => 0.0,
                        'capex_ratio_pct' => 0.0,
                        'opex_ratio_pct' => 0.0,
                        'capex_cost_idr' => 0.0,
                        'opex_cost_idr' => 0.0,
                        'total_cost_idr' => 0.0,
                        'range_type' => $rangeType,
                        'start_date' => Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta')->startOfMonth()->toDateString(),
                        'end_date' => Carbon::create($year, $month, 1, 23, 59, 59, 'Asia/Jakarta')->endOfMonth()->toDateString(),
                    ],
                    'sections' => [],
                    'projects' => [],
                ],
            ];
        }

        // 3. Retrieve pre-aggregated snapshots
        $existingSnapshots = MonthlyBurnSnapshot::query()
            ->whereIn('section_id', $sections->pluck('id'))
            ->where('fiscal_year', $year)
            ->where('fiscal_month', $month)
            ->get()
            ->keyBy('section_id');

        // 4. Retrieve optional ML predictions for month-end trajectory (when Epic-08 / ML models run)
        $mlPredictions = MlPrediction::query()
            ->where('target_type', 'SECTION')
            ->whereIn('target_id', $sections->pluck('id'))
            ->where('prediction_horizon', 'MONTH_END')
            ->orderByDesc('id')
            ->get()
            ->unique('target_id')
            ->keyBy('target_id');

        $snapshotsList = [];
        $totalPlanned = 0.0;
        $totalActual = 0.0;
        $warningCount = 0;
        $dangerCount = 0;
        $configuredCount = 0;

        foreach ($sections as $section) {
            /** @var MonthlyBurnSnapshot|null $snapshot */
            $snapshot = $existingSnapshots->get($section->id);

            // If snapshot is missing from table, lazily calculate & store it
            if (! $snapshot) {
                $snapshot = $this->recalculate($section->id, $year, $month);
            }

            $planned = (float) $snapshot->planned_budget_hours;
            $actual = (float) $snapshot->cumulative_actual_hours;
            $opex = (float) $snapshot->cumulative_opex_hours;
            $capex = (float) $snapshot->cumulative_capex_hours;
            $burnPct = (float) $snapshot->burn_index_pct;
            $velocity = (float) $snapshot->burn_velocity;
            $zone = (string) $snapshot->burn_zone;
            $isConfigured = $planned > 0.0;
            $remaining = round($planned - $actual, 2);
            $projectedTotal = round($velocity * 4.3, 1);

            // Trajectory Indicator
            if (! $isConfigured) {
                $trajectory = $actual > 0.0 ? 'will_overrun' : 'on_pace';
            } else {
                $projectedRatio = ($projectedTotal / $planned) * 100;
                $trajectory = match (true) {
                    $projectedRatio <= 100.0 => 'on_pace',
                    $projectedRatio <= 120.0 => 'trending_over',
                    default => 'will_overrun',
                };
            }

            $capexRatio = $actual > 0.0 ? round(($capex / $actual) * 100, 2) : 0.0;
            $opexRatio = round(100.0 - $capexRatio, 2);

            $mlPred = $mlPredictions->get($section->id);
            $mlForecast = null;
            if ($mlPred) {
                $predictedVal = (float) $mlPred->predicted_value;
                $lower = $mlPred->confidence_interval_lower !== null ? (float) $mlPred->confidence_interval_lower : null;
                $upper = $mlPred->confidence_interval_upper !== null ? (float) $mlPred->confidence_interval_upper : null;
                $confidenceDelta = null;
                if ($upper !== null && $lower !== null) {
                    $confidenceDelta = round(($upper - $lower) / 2, 1);
                } elseif ($upper !== null) {
                    $confidenceDelta = round(abs($upper - $predictedVal), 1);
                }

                $mlForecast = [
                    'predicted_value' => $predictedVal,
                    'confidence_interval_lower' => $lower,
                    'confidence_interval_upper' => $upper,
                    'confidence_delta' => $confidenceDelta,
                    'risk_level' => $mlPred->risk_level,
                    'fallback_used' => (bool) $mlPred->fallback_used,
                ];
            }

            $snapshotsList[] = [
                'id' => $snapshot->id,
                'section_id' => $section->id,
                'section_code' => $section->code,
                'section_name' => $section->name,
                'department_id' => $section->department_id,
                'department_name' => $section->department?->name ?? '',
                'department_code' => $section->department?->code ?? '',
                'planned_budget_hours' => $planned,
                'cumulative_actual_hours' => $actual,
                'remaining_budget_hours' => $remaining,
                'burn_index_pct' => $burnPct,
                'burn_velocity' => $velocity,
                'projected_total_hours' => $projectedTotal,
                'trajectory' => $trajectory,
                'ml_forecast' => $mlForecast,
                'burn_zone' => $zone,
                'cumulative_opex_hours' => $opex,
                'cumulative_capex_hours' => $capex,
                'capex_ratio_pct' => $capexRatio,
                'opex_ratio_pct' => $opexRatio,
                'is_budget_configured' => $isConfigured,
                'last_recalculated_at' => $snapshot->last_recalculated_at?->toISOString() ?? Carbon::now('Asia/Jakarta')->toISOString(),
            ];

            $totalPlanned += $planned;
            $totalActual += $actual;

            if ($isConfigured) {
                $configuredCount++;
            }

            if ($zone === 'ZONE_3_WARNING') {
                $warningCount++;
            } elseif ($zone === 'ZONE_4_POOR') {
                $dangerCount++;
            }
        }

        $deptBurnPct = $totalPlanned > 0.0 ? round(($totalActual / $totalPlanned) * 100, 2) : 0.0;
        $isDeptHighBurn = $deptBurnPct > 100.0;
        $isDeptHighHours = $totalPlanned > 0.0 && $totalActual >= ($totalPlanned * 0.75);

        $departmentZone = match (true) {
            $totalPlanned <= 0.0 && $totalActual <= 0.0 => 'ZONE_1_EXCELLENT',
            $totalPlanned <= 0.0 && $totalActual > 0.0 => 'ZONE_4_POOR',
            ! $isDeptHighBurn && ! $isDeptHighHours => 'ZONE_1_EXCELLENT',
            ! $isDeptHighBurn && $isDeptHighHours => 'ZONE_2_GOOD',
            $isDeptHighBurn && ! $isDeptHighHours => 'ZONE_3_WARNING',
            default => 'ZONE_4_POOR',
        };

        $capexOpex = $this->getCapexOpexBreakdown(
            $user,
            $year,
            $month,
            $targetDeptId,
            $sections,
            $rangeType,
            $startDate,
            $endDate,
        );

        $deptSnapshotsMap = collect($snapshotsList)->groupBy('department_id');
        $departmentsSummary = [];

        foreach ($departments as $dept) {
            $deptSnaps = $deptSnapshotsMap->get($dept['id'], collect());
            $dPlanned = (float) $deptSnaps->sum('planned_budget_hours');
            $dActual = (float) $deptSnaps->sum('cumulative_actual_hours');
            $dRemaining = round($dPlanned - $dActual, 2);
            $dBurnPct = $dPlanned > 0.0 ? round(($dActual / $dPlanned) * 100, 2) : 0.0;
            $isHighBurn = $dBurnPct > 100.0;
            $isHighHours = $dPlanned > 0.0 && $dActual >= ($dPlanned * 0.75);

            $dZone = match (true) {
                $dPlanned <= 0.0 && $dActual <= 0.0 => 'ZONE_1_EXCELLENT',
                $dPlanned <= 0.0 && $dActual > 0.0 => 'ZONE_4_POOR',
                ! $isHighBurn && ! $isHighHours => 'ZONE_1_EXCELLENT',
                ! $isHighBurn && $isHighHours => 'ZONE_2_GOOD',
                $isHighBurn && ! $isHighHours => 'ZONE_3_WARNING',
                default => 'ZONE_4_POOR',
            };

            $dWarning = $deptSnaps->where('burn_zone', 'ZONE_3_WARNING')->count();
            $dDanger = $deptSnaps->where('burn_zone', 'ZONE_4_POOR')->count();
            $dConfigured = $deptSnaps->where('is_budget_configured', true)->count();
            $dTotal = $deptSnaps->count();

            $departmentsSummary[] = [
                'id' => $dept['id'],
                'code' => $dept['code'],
                'name' => $dept['name'],
                'total_planned_hours' => round($dPlanned, 2),
                'total_actual_hours' => round($dActual, 2),
                'total_remaining_hours' => $dRemaining,
                'department_burn_index_pct' => $dBurnPct,
                'department_burn_zone' => $dZone,
                'warning_sections_count' => $dWarning,
                'danger_sections_count' => $dDanger,
                'configured_sections_count' => $dConfigured,
                'total_sections_count' => $dTotal,
            ];
        }

        return [
            'departments' => $departments,
            'selected_department' => $selectedDepartment,
            'fiscal_year' => $year,
            'fiscal_month' => $month,
            'current_tab' => $tab,
            'snapshots' => $snapshotsList,
            'summary' => [
                'total_planned_hours' => round($totalPlanned, 2),
                'total_actual_hours' => round($totalActual, 2),
                'total_remaining_hours' => round($totalPlanned - $totalActual, 2),
                'department_burn_index_pct' => $deptBurnPct,
                'department_burn_zone' => $departmentZone,
                'warning_sections_count' => $warningCount,
                'danger_sections_count' => $dangerCount,
                'configured_sections_count' => $configuredCount,
                'total_sections_count' => count($sections),
            ],
            'departments_summary' => $departmentsSummary,
            'capex_opex' => $capexOpex,
        ];
    }

    /**
     * Compute CapEx vs OpEx distribution, section breakdown, and CapEx projects performance table.
     *
     * @param  Collection<int, Section>  $sections
     * @return array{
     *     summary: array{
     *         total_hours: float,
     *         capex_hours: float,
     *         opex_hours: float,
     *         capex_ratio_pct: float,
     *         opex_ratio_pct: float,
     *         capex_cost_idr: float,
     *         opex_cost_idr: float,
     *         total_cost_idr: float,
     *         range_type: string,
     *         start_date: string,
     *         end_date: string,
     *     },
     *     sections: list<array{
     *         section_id: int,
     *         section_code: string,
     *         section_name: string,
     *         capex_hours: float,
     *         opex_hours: float,
     *         total_hours: float,
     *         capex_ratio_pct: float,
     *     }>,
     *     projects: list<array{
     *         id: int,
     *         project_code: string,
     *         asset_code: string|null,
     *         name: string,
     *         department_id: int,
     *         department_name: string,
     *         department_code: string,
     *         period_logged_hours: float,
     *         cumulative_logged_hours: float,
     *         allocated_labor_hours: float,
     *         allocated_labor_budget_idr: float,
     *         period_cost_idr: float,
     *         cumulative_cost_idr: float,
     *         variance_hours: float,
     *         physical_progress_pct: float,
     *         status: string,
     *         start_date: string|null,
     *         target_end_date: string|null,
     *         has_logged_hours: bool,
     *     }>
     * }
     */
    public function getCapexOpexBreakdown(
        User $user,
        int $year,
        int $month,
        ?int $targetDeptId,
        $sections,
        string $rangeType = 'month',
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        // Resolve date boundaries
        if ($rangeType === 'ytd') {
            $resolvedStartDate = Carbon::create($year, 1, 1, 0, 0, 0, 'Asia/Jakarta')->startOfYear()->toDateString();
            $resolvedEndDate = Carbon::create($year, $month, 1, 23, 59, 59, 'Asia/Jakarta')->endOfMonth()->toDateString();
        } elseif ($rangeType === 'custom' && $startDate && $endDate) {
            try {
                $resolvedStartDate = Carbon::parse($startDate, 'Asia/Jakarta')->toDateString();
                $resolvedEndDate = Carbon::parse($endDate, 'Asia/Jakarta')->toDateString();
            } catch (\Throwable) {
                $rangeType = 'month';
                $resolvedStartDate = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta')->startOfMonth()->toDateString();
                $resolvedEndDate = Carbon::create($year, $month, 1, 23, 59, 59, 'Asia/Jakarta')->endOfMonth()->toDateString();
            }
        } else {
            $rangeType = 'month';
            $resolvedStartDate = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta')->startOfMonth()->toDateString();
            $resolvedEndDate = Carbon::create($year, $month, 1, 23, 59, 59, 'Asia/Jakarta')->endOfMonth()->toDateString();
        }

        if ($sections->isEmpty()) {
            return [
                'summary' => [
                    'total_hours' => 0.0,
                    'capex_hours' => 0.0,
                    'opex_hours' => 0.0,
                    'capex_ratio_pct' => 0.0,
                    'opex_ratio_pct' => 0.0,
                    'capex_cost_idr' => 0.0,
                    'opex_cost_idr' => 0.0,
                    'total_cost_idr' => 0.0,
                    'range_type' => $rangeType,
                    'start_date' => $resolvedStartDate,
                    'end_date' => $resolvedEndDate,
                ],
                'sections' => [],
                'projects' => [],
            ];
        }

        $sectionIds = $sections->pluck('id');

        // Base query for approved items in date range for these sections
        $itemsQuery = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_items.status', 'APPROVED')
            ->whereBetween('overtime_submissions.operational_date', [$resolvedStartDate, $resolvedEndDate])
            ->whereIn('overtime_submissions.section_id', $sectionIds);

        // 1. Macro KPI aggregations
        $macroMetrics = (clone $itemsQuery)
            ->selectRaw('
                COALESCE(SUM(overtime_items.hours_project), 0) as capex_hours,
                COALESCE(SUM(overtime_items.hours_production + overtime_items.hours_tpm + overtime_items.hours_others), 0) as opex_hours,
                COALESCE(SUM(overtime_items.total_hours), 0) as total_hours,
                COALESCE(SUM(overtime_items.hours_project * overtime_items.hourly_rate_snapshot), 0) as capex_cost_idr,
                COALESCE(SUM((overtime_items.hours_production + overtime_items.hours_tpm + overtime_items.hours_others) * overtime_items.hourly_rate_snapshot), 0) as opex_cost_idr,
                COALESCE(SUM(overtime_items.total_cost_snapshot), 0) as total_cost_idr
            ')
            ->first();

        $capexHours = round((float) ($macroMetrics->capex_hours ?? 0.0), 2);
        $opexHours = round((float) ($macroMetrics->opex_hours ?? 0.0), 2);
        $totalHours = round((float) ($macroMetrics->total_hours ?? 0.0), 2);
        $capexRatio = $totalHours > 0 ? round(($capexHours / $totalHours) * 100, 2) : 0.0;
        $opexRatio = $totalHours > 0 ? round(($opexHours / $totalHours) * 100, 2) : 0.0;
        $capexCostIdr = round((float) ($macroMetrics->capex_cost_idr ?? 0.0), 2);
        $opexCostIdr = round((float) ($macroMetrics->opex_cost_idr ?? 0.0), 2);
        $totalCostIdr = round((float) ($macroMetrics->total_cost_idr ?? 0.0), 2);

        // 2. Section comparison breakdown
        $sectionMetrics = (clone $itemsQuery)
            ->selectRaw('
                overtime_submissions.section_id,
                COALESCE(SUM(overtime_items.hours_project), 0) as capex_hours,
                COALESCE(SUM(overtime_items.hours_production + overtime_items.hours_tpm + overtime_items.hours_others), 0) as opex_hours,
                COALESCE(SUM(overtime_items.total_hours), 0) as total_hours
            ')
            ->groupBy('overtime_submissions.section_id')
            ->get()
            ->keyBy('section_id');

        $sectionsBreakdown = [];
        foreach ($sections as $section) {
            $metric = $sectionMetrics->get($section->id);
            $secCapex = round((float) ($metric->capex_hours ?? 0.0), 2);
            $secOpex = round((float) ($metric->opex_hours ?? 0.0), 2);
            $secTotal = round((float) ($metric->total_hours ?? 0.0), 2);
            $secCapexRatio = $secTotal > 0 ? round(($secCapex / $secTotal) * 100, 2) : 0.0;

            $sectionsBreakdown[] = [
                'section_id' => $section->id,
                'section_code' => $section->code,
                'section_name' => $section->name,
                'capex_hours' => $secCapex,
                'opex_hours' => $secOpex,
                'total_hours' => $secTotal,
                'capex_ratio_pct' => $secCapexRatio,
            ];
        }

        // 3. CapEx Projects Performance Table
        $projectsQuery = CapexProject::query()->with('department');
        if ($user->isTeamLeader() && $user->department_id) {
            $projectsQuery->where('department_id', $user->department_id);
        } elseif ($targetDeptId) {
            $projectsQuery->where('department_id', $targetDeptId);
        }
        $capexProjects = $projectsQuery->orderBy('project_code')->get();

        // Hours logged in period per project for these sections
        $projectPeriodHours = (clone $itemsQuery)
            ->whereNotNull('overtime_items.capex_project_id')
            ->selectRaw('
                overtime_items.capex_project_id,
                COALESCE(SUM(overtime_items.hours_project), 0) as logged_hours,
                COALESCE(SUM(overtime_items.hours_project * overtime_items.hourly_rate_snapshot), 0) as logged_cost_idr
            ')
            ->groupBy('overtime_items.capex_project_id')
            ->get()
            ->keyBy('capex_project_id');

        // Cumulative hours across all time per project
        $projectCumulativeHours = OvertimeItem::query()
            ->where('status', 'APPROVED')
            ->whereNotNull('capex_project_id')
            ->selectRaw('
                capex_project_id,
                COALESCE(SUM(hours_project), 0) as cumulative_hours,
                COALESCE(SUM(hours_project * hourly_rate_snapshot), 0) as cumulative_cost_idr
            ')
            ->groupBy('capex_project_id')
            ->get()
            ->keyBy('capex_project_id');

        // Include any project that had logged hours in period even if under different department scope
        $loggedProjectIds = $projectPeriodHours->keys()->toArray();
        $missingProjectIds = array_diff($loggedProjectIds, $capexProjects->pluck('id')->toArray());
        if (! empty($missingProjectIds)) {
            $extraProjects = CapexProject::with('department')->whereIn('id', $missingProjectIds)->get();
            $capexProjects = $capexProjects->concat($extraProjects);
        }

        $projectsList = [];
        foreach ($capexProjects as $project) {
            $periodLogged = round((float) ($projectPeriodHours->get($project->id)?->logged_hours ?? 0.0), 2);
            $cumLogged = round((float) ($projectCumulativeHours->get($project->id)?->cumulative_hours ?? 0.0), 2);
            $allocatedHours = round((float) $project->allocated_labor_hours, 2);
            $allocatedBudget = round((float) $project->allocated_labor_budget_idr, 2);
            $varianceHours = round($cumLogged - $allocatedHours, 2);
            $periodCostIdr = round((float) ($projectPeriodHours->get($project->id)?->logged_cost_idr ?? 0.0), 2);
            $cumCostIdr = round((float) ($projectCumulativeHours->get($project->id)?->cumulative_cost_idr ?? 0.0), 2);
            $progressPct = round((float) $project->physical_progress_pct, 1);

            $projectsList[] = [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'asset_code' => $project->asset_code,
                'name' => $project->name,
                'department_id' => $project->department_id,
                'department_name' => $project->department?->name ?? '',
                'department_code' => $project->department?->code ?? '',
                'period_logged_hours' => $periodLogged,
                'cumulative_logged_hours' => $cumLogged,
                'allocated_labor_hours' => $allocatedHours,
                'allocated_labor_budget_idr' => $allocatedBudget,
                'period_cost_idr' => $periodCostIdr,
                'cumulative_cost_idr' => $cumCostIdr,
                'variance_hours' => $varianceHours,
                'physical_progress_pct' => $progressPct,
                'status' => $project->status,
                'start_date' => $project->start_date?->toDateString(),
                'target_end_date' => $project->target_end_date?->toDateString(),
                'has_logged_hours' => $periodLogged > 0 || $cumLogged > 0,
            ];
        }

        return [
            'summary' => [
                'total_hours' => $totalHours,
                'capex_hours' => $capexHours,
                'opex_hours' => $opexHours,
                'capex_ratio_pct' => $capexRatio,
                'opex_ratio_pct' => $opexRatio,
                'capex_cost_idr' => $capexCostIdr,
                'opex_cost_idr' => $opexCostIdr,
                'total_cost_idr' => $totalCostIdr,
                'range_type' => $rangeType,
                'start_date' => $resolvedStartDate,
                'end_date' => $resolvedEndDate,
            ],
            'sections' => $sectionsBreakdown,
            'projects' => $projectsList,
        ];
    }

    /**
     * Build 5-week burndown, tabular breakdown, and 4-quadrant scatter matrix for a section.
     *
     * @return array{
     *     section: array{id: int, code: string, name: string, department_id: int, department_name: string, department_code: string},
     *     fiscal_year: int,
     *     fiscal_month: int,
     *     is_budget_configured: bool,
     *     summary: array{
     *         planned_hours: float,
     *         actual_hours: float,
     *         remaining_hours: float,
     *         burn_index_pct: float,
     *         burn_zone: string,
     *         burn_velocity: float,
     *         projected_total_hours: float,
     *         trajectory: string,
     *         last_recalculated_at: string|null,
     *     },
     *     weeks: list<array{
     *         week_number: int,
     *         label: string,
     *         date_range: string,
     *         planned_hours: float,
     *         actual_hours: float|null,
     *         cumulative_planned_hours: float,
     *         cumulative_actual_hours: float|null,
     *         hkn_hours: float|null,
     *         hlr_hours: float|null,
     *         burn_pct: float|null,
     *         deviation_hours: float|null,
     *         is_future: bool,
     *         is_current: bool,
     *     }>,
     *     ml_trajectory: list<float|null>|null,
     *     ml_forecast: array{
     *         predicted_value: float,
     *         confidence_interval_lower: float|null,
     *         confidence_interval_upper: float|null,
     *         confidence_delta: float|null,
     *         risk_level: string|null,
     *         fallback_used: bool,
     *     }|null,
     *     scatter_plot: array{
     *         current_burn_pct: float,
     *         cumulative_actual_hours: float,
     *         planned_budget_hours: float,
     *         burn_zone: string,
     *         threshold_hours_75_pct: float,
     *         threshold_burn_100_pct: float,
     *         max_x_scale: float,
     *         max_y_scale: float,
     *     }
     * }
     */
    public function getWeeklyBurndown(Section $section, int $year, int $month): array
    {
        $section->loadMissing('department');

        $startOfMonth = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta');
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $totalDays = $endOfMonth->day;

        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];
        $mName = $monthNames[$month] ?? $startOfMonth->format('M');

        $now = Carbon::now('Asia/Jakarta');
        $isCurrentMonth = ($now->year === $year && $now->month === $month);
        $isPastMonth = ($now->year > $year || ($now->year === $year && $now->month > $month));
        $isFutureMonth = ! $isCurrentMonth && ! $isPastMonth;

        $currentDay = $now->day;
        $activeWeek = match (true) {
            $currentDay <= 7 => 1,
            $currentDay <= 14 => 2,
            $currentDay <= 21 => 3,
            $currentDay <= 28 => 4,
            default => 5,
        };

        // 1. Fetch Budget
        $budget = OvertimeBudget::query()
            ->where('section_id', $section->id)
            ->where('fiscal_year', $year)
            ->where('fiscal_month', $month)
            ->first();

        $plannedHours = $budget ? (float) $budget->planned_hours : 0.0;
        $w1 = $budget ? (float) $budget->week1_planned_hours : 0.0;
        $w2 = $budget ? (float) $budget->week2_planned_hours : 0.0;
        $w3 = $budget ? (float) $budget->week3_planned_hours : 0.0;
        $w4 = $budget ? (float) $budget->week4_planned_hours : 0.0;
        $w5 = $budget ? (float) $budget->week5_planned_hours : 0.0;

        if ($budget && ($w1 + $w2 + $w3 + $w4 + $w5) == 0 && $plannedHours > 0) {
            $weeklyAvg = round($plannedHours / 4.3, 2);
            $w1 = $weeklyAvg;
            $w2 = $weeklyAvg;
            $w3 = $weeklyAvg;
            $w4 = $weeklyAvg;
            $w5 = round(max(0, $plannedHours - ($weeklyAvg * 4)), 2);
        }

        $isConfigured = $plannedHours > 0.0;
        $weeklyPlanned = [1 => $w1, 2 => $w2, 3 => $w3, 4 => $w4, 5 => $w5];

        // 2. Fetch approved items
        $startStr = $startOfMonth->toDateString();
        $endStr = $endOfMonth->toDateString();

        $items = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_submissions.section_id', $section->id)
            ->whereBetween('overtime_submissions.operational_date', [$startStr, $endStr])
            ->where('overtime_items.status', 'APPROVED')
            ->select([
                'overtime_items.id',
                'overtime_items.total_hours',
                'overtime_submissions.operational_date',
                'overtime_submissions.day_type',
            ])
            ->get();

        $weekBuckets = [
            1 => ['actual' => 0.0, 'hkn' => 0.0, 'hlr' => 0.0],
            2 => ['actual' => 0.0, 'hkn' => 0.0, 'hlr' => 0.0],
            3 => ['actual' => 0.0, 'hkn' => 0.0, 'hlr' => 0.0],
            4 => ['actual' => 0.0, 'hkn' => 0.0, 'hlr' => 0.0],
            5 => ['actual' => 0.0, 'hkn' => 0.0, 'hlr' => 0.0],
        ];

        foreach ($items as $item) {
            $day = Carbon::parse($item->operational_date)->day;
            $w = match (true) {
                $day <= 7 => 1,
                $day <= 14 => 2,
                $day <= 21 => 3,
                $day <= 28 => 4,
                default => 5,
            };
            $hrs = (float) $item->total_hours;
            $weekBuckets[$w]['actual'] += $hrs;
            if ($item->day_type === 'HKN') {
                $weekBuckets[$w]['hkn'] += $hrs;
            } else {
                $weekBuckets[$w]['hlr'] += $hrs;
            }
        }

        // 3. Assemble weekly stats
        $weeks = [];
        $runningPlanned = 0.0;
        $runningActual = 0.0;
        $lastActualCumulative = 0.0;

        for ($w = 1; $w <= 5; $w++) {
            $planHrs = round($weeklyPlanned[$w], 2);
            $runningPlanned = round($runningPlanned + $planHrs, 2);

            $dateRange = match ($w) {
                1 => sprintf('%02d - %02d %s', 1, 7, $mName),
                2 => sprintf('%02d - %02d %s', 8, 14, $mName),
                3 => sprintf('%02d - %02d %s', 15, 21, $mName),
                4 => sprintf('%02d - %02d %s', 22, 28, $mName),
                default => $totalDays >= 29 ? sprintf('%02d - %02d %s', 29, $totalDays, $mName) : '-',
            };

            if ($isFutureMonth) {
                $isFuture = true;
                $isCurrent = false;
            } elseif ($isPastMonth) {
                $isFuture = false;
                $isCurrent = false;
            } else {
                $isFuture = ($w > $activeWeek);
                $isCurrent = ($w === $activeWeek);
            }

            if ($isFuture) {
                $actualHrs = null;
                $hknHrs = null;
                $hlrHrs = null;
                $cumulativeActual = null;
                $burnPct = null;
                $deviation = null;
            } else {
                $actualHrs = round($weekBuckets[$w]['actual'], 2);
                $hknHrs = round($weekBuckets[$w]['hkn'], 2);
                $hlrHrs = round($weekBuckets[$w]['hlr'], 2);
                $runningActual = round($runningActual + $actualHrs, 2);
                $lastActualCumulative = $runningActual;
                $cumulativeActual = $runningActual;

                $burnPct = $runningPlanned > 0 ? round(($cumulativeActual / $runningPlanned) * 100, 1) : 0.0;
                $deviation = round($actualHrs - $planHrs, 2);
            }

            $weeks[] = [
                'week_number' => $w,
                'label' => sprintf('Minggu %d', $w),
                'date_range' => $dateRange,
                'planned_hours' => $planHrs,
                'actual_hours' => $actualHrs,
                'cumulative_planned_hours' => $runningPlanned,
                'cumulative_actual_hours' => $cumulativeActual,
                'hkn_hours' => $hknHrs,
                'hlr_hours' => $hlrHrs,
                'burn_pct' => $burnPct,
                'deviation_hours' => $deviation,
                'is_future' => $isFuture,
                'is_current' => $isCurrent,
            ];
        }

        // 4. ML Prediction and Trajectory Interpolation
        $mlPred = MlPrediction::query()
            ->where('target_type', 'SECTION')
            ->where('target_id', $section->id)
            ->where('prediction_horizon', 'MONTH_END')
            ->orderByDesc('id')
            ->first();

        $mlForecast = null;
        $mlTrajectory = null;

        if ($mlPred) {
            $predictedVal = (float) $mlPred->predicted_value;
            $lower = $mlPred->confidence_interval_lower !== null ? (float) $mlPred->confidence_interval_lower : null;
            $upper = $mlPred->confidence_interval_upper !== null ? (float) $mlPred->confidence_interval_upper : null;
            $confidenceDelta = null;
            if ($upper !== null && $lower !== null) {
                $confidenceDelta = round(($upper - $lower) / 2, 1);
            } elseif ($upper !== null) {
                $confidenceDelta = round(abs($upper - $predictedVal), 1);
            }

            $mlForecast = [
                'predicted_value' => $predictedVal,
                'confidence_interval_lower' => $lower,
                'confidence_interval_upper' => $upper,
                'confidence_delta' => $confidenceDelta,
                'risk_level' => $mlPred->risk_level,
                'fallback_used' => (bool) $mlPred->fallback_used,
            ];

            // 5-point ML line for chart
            $mlPoints = [];
            if ($isFutureMonth) {
                for ($i = 1; $i <= 5; $i++) {
                    $mlPoints[] = round(($predictedVal / 5) * $i, 1);
                }
            } elseif ($isPastMonth) {
                for ($i = 1; $i <= 4; $i++) {
                    $mlPoints[] = null;
                }
                $mlPoints[] = $predictedVal;
            } else {
                for ($i = 1; $i <= 5; $i++) {
                    if ($i < $activeWeek) {
                        $mlPoints[] = null;
                    } elseif ($i === $activeWeek) {
                        $mlPoints[] = $lastActualCumulative;
                    } else {
                        $remaining = 5 - $activeWeek;
                        $step = ($predictedVal - $lastActualCumulative) / max(1, $remaining);
                        $mlPoints[] = round($lastActualCumulative + ($step * ($i - $activeWeek)), 1);
                    }
                }
            }
            $mlTrajectory = $mlPoints;
        }

        // 5. Section Snapshot & Summary
        $snapshot = $this->getOrRecalculate($section->id, $year, $month);
        $cumActual = $snapshot ? (float) $snapshot->cumulative_actual_hours : $lastActualCumulative;
        $burnIndexPct = $snapshot ? (float) $snapshot->burn_index_pct : ($plannedHours > 0 ? round(($cumActual / $plannedHours) * 100, 2) : 0.0);
        $velocity = $snapshot ? (float) $snapshot->burn_velocity : 0.0;
        $zone = $snapshot ? (string) $snapshot->burn_zone : 'ZONE_1_EXCELLENT';
        $projectedTotal = round($velocity * 4.3, 1);

        if (! $isConfigured) {
            $trajectory = $cumActual > 0 ? 'will_overrun' : 'on_pace';
        } else {
            $projectedRatio = ($projectedTotal / $plannedHours) * 100;
            $trajectory = match (true) {
                $projectedRatio <= 100.0 => 'on_pace',
                $projectedRatio <= 120.0 => 'trending_over',
                default => 'will_overrun',
            };
        }

        $scatterPlot = [
            'current_burn_pct' => $burnIndexPct,
            'cumulative_actual_hours' => $cumActual,
            'planned_budget_hours' => $plannedHours,
            'burn_zone' => $zone,
            'threshold_hours_75_pct' => round($plannedHours * 0.75, 2),
            'threshold_burn_100_pct' => 100.0,
            'max_x_scale' => max(150.0, ceil($burnIndexPct * 1.15)),
            'max_y_scale' => max(ceil($plannedHours * 1.25), ceil($cumActual * 1.15), 50.0),
        ];

        return [
            'section' => [
                'id' => $section->id,
                'code' => $section->code,
                'name' => $section->name,
                'department_id' => $section->department_id,
                'department_name' => $section->department?->name ?? '',
                'department_code' => $section->department?->code ?? '',
            ],
            'fiscal_year' => $year,
            'fiscal_month' => $month,
            'is_budget_configured' => $isConfigured,
            'summary' => [
                'planned_hours' => $plannedHours,
                'actual_hours' => $cumActual,
                'remaining_hours' => round($plannedHours - $cumActual, 2),
                'burn_index_pct' => $burnIndexPct,
                'burn_zone' => $zone,
                'burn_velocity' => $velocity,
                'projected_total_hours' => $projectedTotal,
                'trajectory' => $trajectory,
                'last_recalculated_at' => $snapshot?->last_recalculated_at?->toISOString() ?? Carbon::now('Asia/Jakarta')->toISOString(),
            ],
            'weeks' => $weeks,
            'ml_trajectory' => $mlTrajectory,
            'ml_forecast' => $mlForecast,
            'scatter_plot' => $scatterPlot,
        ];
    }

    /**
     * Compile structured data for server-side PDF export (weekly standup or monthly closing).
     *
     * @return array<string, mixed>
     */
    public function getPdfExportData(
        User $user,
        int $year,
        int $month,
        ?int $departmentId = null,
        string $reportType = 'standup'
    ): array {
        $data = $this->getDashboardData(
            $user,
            $year,
            $month,
            $departmentId,
            'department'
        );

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $periodLabel = ($monthNames[$month] ?? 'Bulan '.$month).' '.$year;
        $deptLabel = $data['selected_department']
            ? ($data['selected_department']['code'].' - '.$data['selected_department']['name'])
            : 'Semua Departemen (Lintas Pabrik)';

        $deptSlug = $data['selected_department']
            ? Str::slug($data['selected_department']['code'])
            : 'ALL';

        $prefix = $reportType === 'monthly' ? 'Laporan-Bulanan-Burn-Index' : 'Laporan-Standup-Burn-Index';
        $filename = "{$prefix}-{$deptSlug}-{$year}-".sprintf('%02d', $month).'.pdf';

        // Sort snapshots by burn_index_pct descending (highest burn first)
        /** @var list<array<string, mixed>> $rankedSnapshots */
        $rankedSnapshots = collect($data['snapshots'])
            ->sortByDesc('burn_index_pct')
            ->values()
            ->all();

        // Assign rank numbers
        foreach ($rankedSnapshots as $index => &$snap) {
            $snap['rank'] = $index + 1;
        }
        unset($snap);

        $highRiskSections = array_filter($rankedSnapshots, function ($s) {
            return in_array($s['burn_zone'], ['ZONE_3_WARNING', 'ZONE_4_POOR'], true)
                || ($s['is_budget_configured'] && $s['burn_index_pct'] > 100);
        });

        return [
            'report_type' => $reportType,
            'title' => $reportType === 'monthly'
                ? 'LAPORAN ANALISIS BULANAN LENGKAP'
                : 'RINGKASAN STANDUP MINGGUAN',
            'subtitle' => 'Overtime Burn Index & Budget Control Matrix',
            'department_label' => $deptLabel,
            'period_label' => $periodLabel,
            'fiscal_year' => $year,
            'fiscal_month' => $month,
            'generated_at' => Carbon::now('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
            'printed_by' => $user->name.($user->npk ? ' (NPK: '.$user->npk.')' : ''),
            'summary' => $data['summary'],
            'snapshots' => $rankedSnapshots,
            'departments_summary' => $data['departments_summary'],
            'high_risk_sections' => array_values($highRiskSections),
            'capex_opex' => $data['capex_opex'] ?? null,
            'filename' => $filename,
        ];
    }
}
