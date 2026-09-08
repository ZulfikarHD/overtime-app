<?php

namespace App\Services\Analytics;

use App\Models\Department;
use App\Models\MonthlyBurnSnapshot;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Carbon;

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
    public function getDashboardData(User $user, int $year, int $month, ?int $departmentId = null, string $tab = 'sections'): array
    {
        // 1. Resolve accessible departments based on role
        if ($user->isManager() || $user->isTeamLeader()) {
            $departmentsQuery = Department::where('id', $user->department_id)->where('is_active', true);
            $targetDeptId = (int) $user->department_id;
        } else {
            $departmentsQuery = Department::where('is_active', true)->orderBy('code');
            $targetDeptId = $departmentId ?? Department::where('is_active', true)->orderBy('code')->value('id');
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
            ];
        }

        // 3. Retrieve pre-aggregated snapshots
        $existingSnapshots = MonthlyBurnSnapshot::query()
            ->whereIn('section_id', $sections->pluck('id'))
            ->where('fiscal_year', $year)
            ->where('fiscal_month', $month)
            ->get()
            ->keyBy('section_id');

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
        ];
    }
}
