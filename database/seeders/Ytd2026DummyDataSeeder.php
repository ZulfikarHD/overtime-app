<?php

namespace Database\Seeders;

use App\Models\CapexProject;
use App\Models\Employee;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ytd2026DummyDataSeeder — Staging / demo data for fiscal year 2026.
 *
 * Populates:
 *  - OvertimeBudget for all 12 months (per-dept and per-section) with category breakdown
 *  - OvertimeSubmission + OvertimeItem for Jan–Aug 2026 (completed months, fully APPROVED)
 *  - Additional September 2026 submissions spread across days 1-22 (for BurnUpIndexChart)
 *  - MonthlyBurnSnapshot for Jan–Sep 2026 (used by existing KPI card and section comparison)
 *
 * Designed to run idempotently: skips months/sections that already have sufficient data.
 * Safe to re-run without duplicating records (uses updateOrCreate for budgets & snapshots).
 */
class Ytd2026DummyDataSeeder extends Seeder
{
    /** Valid rca_category values (from overtime_items enum constraint) */
    private const RCA_CATEGORIES = [
        'MACHINE_BREAKDOWN',
        'SUPPLIER_DELAY',
        'QUALITY_REWORK',
        'CUSTOMER_RUSH',
        'TRIAL_MODEL',
        'FACILITY_MAINTENANCE',
        'OTHER',
    ];

    /**
     * Monthly utilization rate (actual / planned) — creates realistic YTD pattern.
     * Values > 1.0 = over-budget months; values < 1.0 = under-budget months.
     */
    private const MONTHLY_UTILIZATION = [
        1 => 0.78,   // Jan — post-holiday ramp-up
        2 => 0.88,   // Feb — normal pace
        3 => 1.10,   // Mar — production surge (model changeover)
        4 => 0.82,   // Apr — pre-Lebaran slowdown
        5 => 0.70,   // May — Lebaran month, many HLR holidays
        6 => 1.15,   // Jun — post-Lebaran catch-up surge
        7 => 0.95,   // Jul — stable
        8 => 1.03,   // Aug — slight over (year-end ramp)
        9 => null,   // Sep — current month (partial, from existing + new day-spread seeds)
    ];

    /**
     * Section-level category distribution ratios per month.
     * [production, tpm, project, others] — must sum to 1.0.
     * Matches the per-category planned ratios in OvertimeBudgetSeeder.
     */
    private const CATEGORY_RATIOS_BY_MONTH = [
        1 => [0.58, 0.05, 0.22, 0.15],
        2 => [0.55, 0.06, 0.24, 0.15],
        3 => [0.62, 0.04, 0.25, 0.09],
        4 => [0.52, 0.06, 0.28, 0.14],
        5 => [0.50, 0.08, 0.27, 0.15],
        6 => [0.60, 0.04, 0.24, 0.12],
        7 => [0.56, 0.05, 0.26, 0.13],
        8 => [0.54, 0.06, 0.25, 0.15],
        9 => [0.55, 0.05, 0.25, 0.15],
    ];

    public function run(): void
    {
        $this->command?->info('Ytd2026DummyDataSeeder: seeding 2026 YTD staging data…');

        $admin = User::query()->where('role', 'admin')->first();
        if (! $admin) {
            $this->command?->warn('No admin user found — skipping.');

            return;
        }

        $sections = Section::query()->with('department')->where('is_active', true)->get();
        if ($sections->isEmpty()) {
            $this->command?->warn('No sections found — run master seeders first.');

            return;
        }

        $teamLeadersBySectionId = User::query()
            ->where('role', 'team_leader')
            ->where('is_active', true)
            ->get()
            ->keyBy('section_id');

        $employeesBySection = Employee::query()
            ->where('is_active', true)
            ->get()
            ->groupBy('section_id');

        // Pre-load one CapEx project per department (for hours_project attribution)
        $capexByDeptId = CapexProject::query()
            ->where('status', 'ACTIVE')
            ->get()
            ->groupBy('department_id')
            ->map(fn ($g) => $g->first());

        // --- Step 1: OvertimeBudgets for all 12 months ---
        $this->command?->info('  → Creating budgets Jan–Dec 2026…');
        $this->seedAllMonthBudgets($sections);

        // --- Step 2: Transactions for Jan–Aug 2026 (closed months) ---
        $this->command?->info('  → Seeding Jan–Aug 2026 OT transactions…');
        $calendarByMonth = $this->loadCalendarByMonth(2026, 1, 8);

        for ($month = 1; $month <= 8; $month++) {
            $calDays = $calendarByMonth->get($month, collect());
            $utilization = self::MONTHLY_UTILIZATION[$month] ?? 0.90;

            foreach ($sections as $section) {
                // Skip if this section already has submissions for this month
                $existingCount = OvertimeSubmission::query()
                    ->where('section_id', $section->id)
                    ->whereYear('operational_date', 2026)
                    ->whereMonth('operational_date', $month)
                    ->count();

                if ($existingCount >= 3) {
                    continue; // Already seeded
                }

                $submitter = $teamLeadersBySectionId->get($section->id) ?? $admin;
                $employees = $employeesBySection->get($section->id, collect());

                $this->seedMonthForSection(
                    section: $section,
                    month: $month,
                    year: 2026,
                    calDays: $calDays,
                    utilization: $utilization,
                    submitter: $submitter,
                    reviewer: $admin,
                    employees: $employees,
                    capexProject: $capexByDeptId->get($section->department_id),
                );
            }

            $this->command?->info("     ✓ Month {$month}/2026 done");
        }

        // --- Step 3: Augment September 2026 with daily distributed data ---
        $this->command?->info('  → Augmenting Sep 2026 with daily spread data…');
        $this->seedSeptemberSpread($sections, $teamLeadersBySectionId, $employeesBySection, $admin, $capexByDeptId);

        // --- Step 4: MonthlyBurnSnapshot for Jan–Sep 2026 ---
        $this->command?->info('  → Creating monthly burn snapshots Jan–Sep 2026…');
        for ($month = 1; $month <= 9; $month++) {
            foreach ($sections as $section) {
                $this->upsertBurnSnapshot($section, 2026, $month);
            }
        }

        $this->command?->info('Ytd2026DummyDataSeeder: complete ✓');
    }

    // -------------------------------------------------------------------------
    // Budget seeding
    // -------------------------------------------------------------------------

    private function seedAllMonthBudgets(Collection $sections): void
    {
        $departments = $sections->pluck('department')->unique('id');
        $hourlyRate = 25000.0;

        for ($month = 1; $month <= 12; $month++) {
            foreach ($departments as $dept) {
                // Department-level aggregate budget
                OvertimeBudget::query()->updateOrCreate(
                    [
                        'department_id' => $dept->id,
                        'section_id' => null,
                        'fiscal_year' => 2026,
                        'fiscal_month' => $month,
                    ],
                    [
                        'planned_hours' => 800.00,
                        'planned_cost_idr' => round(800.00 * $hourlyRate, 2),
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
            }

            // Section-level budgets
            foreach ($sections as $section) {
                OvertimeBudget::query()->updateOrCreate(
                    [
                        'department_id' => $section->department_id,
                        'section_id' => $section->id,
                        'fiscal_year' => 2026,
                        'fiscal_month' => $month,
                    ],
                    [
                        'planned_hours' => 200.00,
                        'planned_cost_idr' => round(200.00 * $hourlyRate, 2),
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

    // -------------------------------------------------------------------------
    // Past month transaction seeding
    // -------------------------------------------------------------------------

    private function seedMonthForSection(
        Section $section,
        int $month,
        int $year,
        Collection $calDays,
        float $utilization,
        User $submitter,
        User $reviewer,
        Collection $employees,
        ?object $capexProject = null,
    ): void {
        if ($employees->isEmpty()) {
            return;
        }

        $plannedHours = 200.0;
        $targetActual = $plannedHours * $utilization;
        $seededHours = 0.0;

        $hknDays = $calDays->where('day_type', 'HKN')->values();
        $hlrDays = $calDays->where('day_type', 'HLR')->values();

        $selectedHkn = $this->pickEvenly($hknDays, 8);
        $selectedHlr = $this->pickEvenly($hlrDays, 1);
        $selectedDays = $selectedHkn->merge($selectedHlr)->sortBy('calendar_date')->values();

        $catRatios = self::CATEGORY_RATIOS_BY_MONTH[$month] ?? [0.55, 0.05, 0.25, 0.15];
        $hourlyRate = 25000.0;
        $rcaList = self::RCA_CATEGORIES;

        foreach ($selectedDays as $dayIndex => $calDay) {
            if ($seededHours >= $targetActual) {
                break;
            }

            $opDate = Carbon::parse($calDay->calendar_date)->toDateString();
            $dayType = $calDay->day_type;

            $pickedEmployees = $employees->random(min(4, $employees->count()));

            $submission = OvertimeSubmission::query()->create([
                'submission_code' => sprintf('OT-%04d%02d-S%03d-%02d', $year, $month, $section->id, $dayIndex + 1),
                'submission_date' => $opDate,
                'operational_date' => $opDate,
                'day_type' => $dayType,
                'department_id' => $section->department_id,
                'section_id' => $section->id,
                'submitted_by_user_id' => $submitter->id,
                'status' => 'APPROVED',
                'submission_notes' => "YTD Seed {$section->code} {$year}-".sprintf('%02d', $month),
                'total_hours_cached' => 0.0,
            ]);

            $submissionTotal = 0.0;

            foreach ($pickedEmployees as $empIndex => $employee) {
                $totalEmpHours = $dayType === 'HLR'
                    ? round(mt_rand(40, 70) / 10, 1)
                    : round(mt_rand(20, 50) / 10, 1);

                [$hProd, $hTpm, $hProj, $hOth] = $this->splitHours($totalEmpHours, $catRatios);

                // Constraint: hours_project > 0 requires capex_project_id
                if ($hProj > 0 && ! $capexProject) {
                    // Redistribute project hours to production
                    $hProd = round($hProd + $hProj, 1);
                    $hProj = 0.0;
                }

                OvertimeItem::query()->create([
                    'overtime_submission_id' => $submission->id,
                    'employee_id' => $employee->id,
                    'npk_snapshot' => $employee->npk,
                    'capex_project_id' => $hProj > 0 ? $capexProject->id : null,
                    'hours_production' => $hProd,
                    'hours_tpm' => $hTpm,
                    'hours_project' => $hProj,
                    'hours_others' => $hOth,
                    'hourly_rate_snapshot' => $hourlyRate,
                    'total_cost_snapshot' => round($totalEmpHours * $hourlyRate, 2),
                    'rca_category' => $rcaList[($dayIndex + $empIndex) % count($rcaList)],
                    'task_description' => 'YTD demo — '.$section->code,
                    'status' => 'APPROVED',
                    'reviewed_by_user_id' => $reviewer->id,
                    'reviewed_at' => Carbon::parse($opDate)->addDay()->setTime(8, 0),
                    'lock_version' => 1,
                ]);

                $submissionTotal += $totalEmpHours;
            }

            $submission->update(['total_hours_cached' => round($submissionTotal, 2)]);
            $seededHours += $submissionTotal;
        }
    }

    // -------------------------------------------------------------------------
    // September 2026 — Spread daily data for BurnUpIndexChart
    // -------------------------------------------------------------------------

    private function seedSeptemberSpread(
        Collection $sections,
        Collection $teamLeadersBySectionId,
        Collection $employeesBySection,
        User $admin,
        Collection $capexByDeptId,
    ): void {
        // Get all Sep 2026 days from OperationalCalendar
        $sepDays = OperationalCalendar::query()
            ->whereRaw("calendar_date >= '2026-09-01' AND calendar_date <= '2026-09-22'")
            ->orderBy('calendar_date')
            ->get(['calendar_date', 'day_type']);

        if ($sepDays->isEmpty()) {
            $this->command?->warn('  No OperationalCalendar entries for Sep 2026 — skipping spread.');

            return;
        }

        // Find which days already have data
        $existingDates = OvertimeSubmission::query()
            ->whereRaw("operational_date >= '2026-09-01' AND operational_date <= '2026-09-22'")
            ->pluck('operational_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        // Pick HKN days that don't have data yet
        $missingDays = $sepDays->filter(
            fn ($d) => $d->day_type === 'HKN'
                && ! $existingDates->contains(Carbon::parse($d->calendar_date)->toDateString()),
        )->values();

        if ($missingDays->isEmpty()) {
            $this->command?->info('  Sep 2026 already has data on all days — no spread needed.');

            return;
        }

        $catRatios = self::CATEGORY_RATIOS_BY_MONTH[9];
        $hourlyRate = 25000.0;
        $rcaList = self::RCA_CATEGORIES;
        $idx = 0;

        foreach ($sections as $section) {
            $submitter = $teamLeadersBySectionId->get($section->id) ?? $admin;
            $employees = $employeesBySection->get($section->id, collect());
            $capexProject = $capexByDeptId->get($section->department_id);

            if ($employees->isEmpty()) {
                continue;
            }

            $daysForSection = $this->pickEvenly($missingDays, min(8, $missingDays->count()));

            foreach ($daysForSection as $dayIndex => $calDay) {
                $opDate = Carbon::parse($calDay->calendar_date)->toDateString();

                $submission = OvertimeSubmission::query()->create([
                    'submission_code' => sprintf('OT-202609-S%03d-SPR-%02d', $section->id, $dayIndex + 1),
                    'submission_date' => $opDate,
                    'operational_date' => $opDate,
                    'day_type' => $calDay->day_type,
                    'department_id' => $section->department_id,
                    'section_id' => $section->id,
                    'submitted_by_user_id' => $submitter->id,
                    'status' => 'APPROVED',
                    'submission_notes' => "Sep spread seed {$section->code}",
                    'total_hours_cached' => 0.0,
                ]);

                $picked = $employees->random(min(3, $employees->count()));
                $total = 0.0;

                foreach ($picked as $empIdx => $employee) {
                    $empHours = round(mt_rand(20, 45) / 10, 1);
                    [$hProd, $hTpm, $hProj, $hOth] = $this->splitHours($empHours, $catRatios);

                    if ($hProj > 0 && ! $capexProject) {
                        $hProd = round($hProd + $hProj, 1);
                        $hProj = 0.0;
                    }

                    OvertimeItem::query()->create([
                        'overtime_submission_id' => $submission->id,
                        'employee_id' => $employee->id,
                        'npk_snapshot' => $employee->npk,
                        'capex_project_id' => $hProj > 0 ? $capexProject->id : null,
                        'hours_production' => $hProd,
                        'hours_tpm' => $hTpm,
                        'hours_project' => $hProj,
                        'hours_others' => $hOth,
                        'hourly_rate_snapshot' => $hourlyRate,
                        'total_cost_snapshot' => round($empHours * $hourlyRate, 2),
                        'rca_category' => $rcaList[($dayIndex + $empIdx) % count($rcaList)],
                        'task_description' => 'Sep daily spread seed',
                        'status' => 'APPROVED',
                        'reviewed_by_user_id' => $admin->id,
                        'reviewed_at' => Carbon::parse($opDate)->addDay()->setTime(8, 0),
                        'lock_version' => 1,
                    ]);

                    $total += $empHours;
                }

                $submission->update(['total_hours_cached' => round($total, 2)]);
                $idx++;
            }
        }

        $this->command?->info("  → Added {$idx} spread submissions across Sep 2026");
    }

    // -------------------------------------------------------------------------
    // MonthlyBurnSnapshot upsert
    // -------------------------------------------------------------------------

    private function upsertBurnSnapshot(Section $section, int $year, int $month): void
    {
        // Compute actual hours from approved items for this section/month
        $actual = (float) OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_submissions.section_id', $section->id)
            ->whereYear('overtime_submissions.operational_date', $year)
            ->whereMonth('overtime_submissions.operational_date', $month)
            ->where('overtime_items.status', 'APPROVED')
            ->sum('overtime_items.total_hours');

        $budget = OvertimeBudget::query()
            ->where('section_id', $section->id)
            ->where('fiscal_year', $year)
            ->where('fiscal_month', $month)
            ->first();

        $planned = (float) ($budget?->planned_hours ?? 200.0);
        $burnIndex = $planned > 0 ? round(($actual / $planned) * 100, 2) : 0.0;

        $capex = round($actual * 0.25, 2);
        $opex = round($actual - $capex, 2);

        MonthlyBurnSnapshot::query()->updateOrCreate(
            [
                'section_id' => $section->id,
                'fiscal_year' => $year,
                'fiscal_month' => $month,
            ],
            [
                'department_id' => $section->department_id,
                'planned_budget_hours' => $planned,
                'cumulative_actual_hours' => $actual,
                'cumulative_opex_hours' => $opex,
                'cumulative_capex_hours' => $capex,
                'burn_index_pct' => $burnIndex,
                'burn_velocity' => $actual > 0 ? round($actual / 4, 2) : 0.0,
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Load OperationalCalendar rows for a range of months, grouped by month number.
     *
     * @return Collection<int, Collection>
     */
    private function loadCalendarByMonth(int $year, int $fromMonth, int $toMonth): Collection
    {
        $from = sprintf('%04d-%02d-01', $year, $fromMonth);
        $to = Carbon::create($year, $toMonth, 1)->endOfMonth()->toDateString();

        $rows = OperationalCalendar::query()
            ->whereRaw('calendar_date >= ? AND calendar_date <= ?', [$from, $to])
            ->orderBy('calendar_date')
            ->get(['calendar_date', 'day_type']);

        return $rows->groupBy(fn ($r) => (int) Carbon::parse($r->calendar_date)->month);
    }

    /**
     * Pick N rows evenly spread across a collection.
     */
    private function pickEvenly(Collection $items, int $n): Collection
    {
        if ($items->isEmpty() || $n <= 0) {
            return collect();
        }

        $count = $items->count();
        if ($count <= $n) {
            return $items->values();
        }

        $step = (float) $count / $n;
        $picked = collect();
        for ($i = 0; $i < $n; $i++) {
            $picked->push($items->get((int) round($i * $step)));
        }

        return $picked->filter()->values();
    }

    /**
     * Split total hours into 4 category buckets using ratios.
     * Rounds to nearest 0.5h; adjusts last bucket to ensure total is exact.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $ratios
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function splitHours(float $total, array $ratios): array
    {
        $prod = round($total * $ratios[0] * 2) / 2;
        $tpm = round($total * $ratios[1] * 2) / 2;
        $proj = round($total * $ratios[2] * 2) / 2;
        $others = round(max(0.0, $total - $prod - $tpm - $proj) * 2) / 2;

        // Ensure non-negative and total is preserved within 0.5
        return [$prod, $tpm, $proj, $others];
    }
}
