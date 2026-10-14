<?php

namespace App\Services;

use App\Enums\BurnZone;
use App\Models\AlertFollowUp;
use App\Models\Department;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use App\Services\Analytics\DashboardKpiService;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Derives Early Warning alerts (section + date in zone Kuning / Merah) from the daily burn index.
 *
 * The daily index itself comes from {@see DashboardKpiService::calculateDailyIndex()} (shared with the
 * Dashboard chart) and zones from {@see PolicyThresholdService::classifyWith()}, so alerts never diverge
 * from what the chart shows.
 *
 * @phpstan-type Alert array{
 *     section_id: int,
 *     section_name: string,
 *     department_id: int,
 *     department_name: ?string,
 *     alert_date: string,
 *     index_pct: float,
 *     zone: 'yellow'|'red',
 *     streak_days: int,
 *     green_max_pct: float,
 *     yellow_max_pct: float,
 *     follow_up_status: 'none'|'draft'|'sent',
 *     follow_up_id: ?int
 * }
 * @phpstan-type Day array{
 *     index_pct: float,
 *     actual_hours: float,
 *     zone: BurnZone,
 *     is_working_day: bool,
 *     streak_days: int
 * }
 */
class EarlyWarningService
{
    /**
     * How many days before the requested range are scanned so a streak that started earlier is counted.
     * Streaks are therefore capped at roughly this many calendar days.
     */
    public const STREAK_LOOKBACK_DAYS = 60;

    /**
     * Number of working days (HKN) shown in the alert detail chart.
     */
    public const SERIES_WORKING_DAYS = 7;

    /**
     * How long the sidebar open-alert count is cached.
     */
    public const OPEN_ALERT_COUNT_TTL_SECONDS = 60;

    /**
     * Alerts per page in the inbox.
     */
    public const INBOX_PER_PAGE = 25;

    /**
     * Calendar days scanned backwards from the alert date to find the series working days.
     */
    protected const SERIES_SCAN_DAYS = 30;

    protected const PLANT_DEFAULT_KEY = 0;

    public function __construct(
        public DashboardKpiService $kpiService,
        public PolicyThresholdService $thresholdService,
    ) {}

    /**
     * Alerts of the visible sections between two dates (inclusive), most severe first.
     *
     * Dates are interpreted as Asia/Jakarta calendar dates; days after today are ignored (no data yet).
     * Non-admin users are restricted to their own department server-side.
     *
     * @return Collection<int, Alert>
     */
    public function alertsFor(
        User $user,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $departmentId = null,
        ?int $sectionId = null,
        ?BurnZone $zone = null,
    ): Collection {
        $scopedDepartmentId = $this->resolveDepartmentScope($user, $departmentId);

        if ($scopedDepartmentId === false) {
            return collect();
        }

        $rangeFrom = $this->asCalendarDay($from);
        $rangeTo = $this->asCalendarDay($to);
        $today = Carbon::now('Asia/Jakarta')->startOfDay();

        if ($rangeTo->gt($today)) {
            $rangeTo = $today;
        }

        if ($rangeFrom->gt($rangeTo)) {
            return collect();
        }

        $sections = $this->sectionsInScope($scopedDepartmentId, $sectionId);

        if ($sections->isEmpty()) {
            return collect();
        }

        $evaluation = $this->evaluateSections($sections, $rangeFrom, $rangeTo);

        $fromKey = $rangeFrom->toDateString();
        $candidates = [];

        foreach ($sections as $section) {
            foreach ($evaluation['days'][$section->id] ?? [] as $date => $day) {
                if ($date < $fromKey || $day['zone'] === BurnZone::Green) {
                    continue;
                }

                if ($zone !== null && $day['zone'] !== $zone) {
                    continue;
                }

                $candidates[] = [$section, $date, $day];
            }
        }

        if ($candidates === []) {
            return collect();
        }

        $followUps = $this->latestFollowUps($sections->modelKeys(), $rangeFrom, $rangeTo);

        return collect($candidates)
            ->map(fn (array $candidate): array => $this->buildAlert(
                $candidate[0],
                $candidate[1],
                $candidate[2],
                $evaluation['thresholds'][$this->thresholdKey($candidate[0])],
                $followUps[$candidate[0]->id.'|'.$candidate[1]] ?? null,
            ))
            ->sort(fn (array $a, array $b): int => $this->compareAlerts($a, $b))
            ->values();
    }

    /**
     * Department and section choices of the inbox filter bar, plus the filters that actually apply.
     *
     * Non-admins are locked to their own department whatever the client sent; admins pick any department.
     * Sections are only offered once a department is in scope, and an unknown section is dropped.
     *
     * @return array{
     *     department_id: ?int,
     *     section_id: ?int,
     *     departments: list<array{id: int, code: string, name: string}>,
     *     sections: list<array{id: int, department_id: int, name: string}>
     * }
     */
    public function inboxScope(User $user, ?int $departmentId, ?int $sectionId): array
    {
        $scopedDepartmentId = $this->resolveDepartmentScope($user, $departmentId);

        if ($scopedDepartmentId === false) {
            return ['department_id' => null, 'section_id' => null, 'departments' => [], 'sections' => []];
        }

        $departments = Department::query()
            ->when(
                $user->isAdmin(),
                fn ($query) => $query->where('is_active', true),
                fn ($query) => $query->whereKey($scopedDepartmentId),
            )
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Department $department): array => [
                'id' => (int) $department->id,
                'code' => (string) $department->code,
                'name' => $department->name,
            ])
            ->values()
            ->all();

        $sections = $scopedDepartmentId === null
            ? []
            : Section::query()
                ->where('department_id', $scopedDepartmentId)
                ->orderBy('name')
                ->get(['id', 'department_id', 'name'])
                ->map(fn (Section $section): array => [
                    'id' => (int) $section->id,
                    'department_id' => (int) $section->department_id,
                    'name' => $section->name,
                ])
                ->values()
                ->all();

        $knownSection = collect($sections)->contains(fn (array $section): bool => $section['id'] === $sectionId);

        return [
            'department_id' => $scopedDepartmentId,
            'section_id' => $knownSection ? $sectionId : null,
            'departments' => $departments,
            'sections' => $sections,
        ];
    }

    /**
     * Content of the inbox page: the summary of the period and one page of the filtered alert list.
     *
     * The summary covers the department / section / date range filters only, so it keeps the whole picture
     * while the zone and follow-up status filters narrow the list. The list keeps alertsFor() ordering
     * (Merah first, then longest streak). An out-of-range page falls back to the last page.
     *
     * @param  array{
     *     department_id: ?int,
     *     section_id: ?int,
     *     date_from: string,
     *     date_to: string,
     *     zone: string,
     *     status: string,
     *     page: int
     * }  $filters
     * @return array{
     *     summary: array{total: int, red: int, yellow: int, without_follow_up: int},
     *     alerts: array{
     *         data: list<Alert>,
     *         pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: ?int, to: ?int}
     *     }
     * }
     */
    public function inboxFor(User $user, array $filters): array
    {
        $alerts = $this->alertsFor(
            $user,
            Carbon::parse($filters['date_from'], 'Asia/Jakarta'),
            Carbon::parse($filters['date_to'], 'Asia/Jakarta'),
            $filters['department_id'],
            $filters['section_id'],
        );

        $summary = [
            'total' => $alerts->count(),
            'red' => $alerts->where('zone', 'red')->count(),
            'yellow' => $alerts->where('zone', 'yellow')->count(),
            'without_follow_up' => $alerts->where('follow_up_status', 'none')->count(),
        ];

        $visible = $alerts
            ->when($filters['zone'] !== 'all', fn (Collection $items) => $items->where('zone', $filters['zone']))
            ->when($filters['status'] !== 'all', fn (Collection $items) => $items->where('follow_up_status', $filters['status']))
            ->values();

        $total = $visible->count();
        $lastPage = max(1, (int) ceil($total / self::INBOX_PER_PAGE));
        $page = min(max(1, $filters['page']), $lastPage);
        $offset = ($page - 1) * self::INBOX_PER_PAGE;

        return [
            'summary' => $summary,
            'alerts' => [
                'data' => $visible->slice($offset, self::INBOX_PER_PAGE)->values()->all(),
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => self::INBOX_PER_PAGE,
                    'total' => $total,
                    'from' => $total === 0 ? null : $offset + 1,
                    'to' => $total === 0 ? null : min($offset + self::INBOX_PER_PAGE, $total),
                ],
            ],
        ];
    }

    /**
     * One alert plus the daily index series (last working days) used by the detail chart.
     *
     * `alert` is null when the date is not (or no longer) an alert, e.g. the zone is Hijau,
     * the section has no budget for that month, or the date is in the future.
     *
     * @return array{
     *     alert: ?Alert,
     *     series: list<array{date: string, index_pct: float, actual_hours: float, zone: 'green'|'yellow'|'red', is_working_day: bool}>,
     *     green_max_pct: float,
     *     yellow_max_pct: float
     * }
     *
     * @throws AuthorizationException
     */
    public function alertDetail(User $user, Section $section, CarbonInterface $date): array
    {
        $this->assertCanViewSection($user, $section);

        $section->loadMissing('department:id,name');

        $alertDay = $this->asCalendarDay($date);
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $rangeTo = $alertDay->gt($today) ? $today : $alertDay;
        $rangeFrom = $rangeTo->copy()->subDays(self::SERIES_SCAN_DAYS);

        $evaluation = $this->evaluateSections(new EloquentCollection([$section]), $rangeFrom, $rangeTo);
        $threshold = $evaluation['thresholds'][$this->thresholdKey($section)];
        $days = $evaluation['days'][$section->id] ?? [];
        $alertKey = $alertDay->toDateString();

        $alert = null;
        if (isset($days[$alertKey]) && $days[$alertKey]['zone'] !== BurnZone::Green) {
            $followUps = $this->latestFollowUps([$section->id], $alertDay, $alertDay);
            $alert = $this->buildAlert(
                $section,
                $alertKey,
                $days[$alertKey],
                $threshold,
                $followUps[$section->id.'|'.$alertKey] ?? null,
            );
        }

        return [
            'alert' => $alert,
            'series' => $this->buildSeries($days, $alertKey, $rangeFrom->toDateString()),
            'green_max_pct' => (float) $threshold->green_max_pct,
            'yellow_max_pct' => (float) $threshold->yellow_max_pct,
        ];
    }

    /**
     * Number of open Merah alerts of the current month (Asia/Jakarta) that have no sent follow-up yet.
     *
     * Feeds the sidebar badge, which is rendered on every page, so the result is cached briefly per
     * visibility scope (one entry per department, one for admins). Users who cannot see alerts get 0
     * without touching the database.
     */
    public function openRedAlertCount(User $user): int
    {
        if (! $this->canViewAlerts($user)) {
            return 0;
        }

        $scopeKey = $user->isAdmin() ? 'all' : (string) ($user->department_id ?? 'none');

        if ($scopeKey === 'none') {
            return 0;
        }

        $today = Carbon::now('Asia/Jakarta')->startOfDay();

        return (int) Cache::remember(
            sprintf('early-warning:open-red:%s:%s', $scopeKey, $today->toDateString()),
            self::OPEN_ALERT_COUNT_TTL_SECONDS,
            fn (): int => $this->alertsFor($user, $today->copy()->startOfMonth(), $today, null, null, BurnZone::Red)
                ->where('follow_up_status', '!=', 'sent')
                ->count(),
        );
    }

    /**
     * Whether the given date is currently an alert (Kuning / Merah) for the section.
     */
    public function isAlert(User $user, Section $section, CarbonInterface $date): bool
    {
        return $this->alertDetail($user, $section, $date)['alert'] !== null;
    }

    /**
     * Compute index, zone and streak for every day of every section in a constant number of queries.
     *
     * The scan starts STREAK_LOOKBACK_DAYS before $from so streaks reaching into the past are counted.
     * Days of a month without a budget are not evaluated (no false Hijau / Merah) and break the streak.
     * HLR days never break a streak: an HLR alert day counts itself plus the working-day streak before it.
     *
     * @param  EloquentCollection<int, Section>  $sections
     * @return array{
     *     days: array<int, array<string, Day>>,
     *     thresholds: array<int, PolicyThreshold>
     * }
     */
    protected function evaluateSections(EloquentCollection $sections, Carbon $from, Carbon $to): array
    {
        $scanFrom = $from->copy()->subDays(self::STREAK_LOOKBACK_DAYS);
        $dates = $this->datesBetween($scanFrom, $to);
        $sectionIds = $sections->modelKeys();

        $periods = [];
        foreach ($dates as $date) {
            $periods[$date->format('Y-m')] = [$date->year, $date->month];
        }

        $plannedHours = $this->kpiService->resolvePlannedHoursForSections($sectionIds, array_values($periods));
        $actualHours = $this->approvedHoursBySectionDay($sectionIds, $scanFrom, $to);
        $dayTypes = $this->dayTypesBetween($scanFrom, $to);
        $thresholds = $this->thresholdsFor($sections);

        $days = [];

        foreach ($sections as $section) {
            $threshold = $thresholds[$this->thresholdKey($section)];
            $streak = 0;

            foreach ($dates as $date) {
                $key = $date->toDateString();
                $isWorkingDay = ($dayTypes[$key] ?? ($date->isWeekend() ? 'HLR' : 'HKN')) !== 'HLR';
                $planned = $plannedHours[$section->id][$date->format('Y-m')] ?? null;

                if ($planned === null) {
                    if ($isWorkingDay) {
                        $streak = 0;
                    }

                    continue;
                }

                $actual = $actualHours[$section->id][$key] ?? 0.0;
                $pacing = $this->kpiService->calculateDailyPacing($planned, $date->daysInMonth);
                $index = $this->kpiService->calculateDailyIndex($actual, $pacing);
                $zone = $this->thresholdService->classifyWith($index, $threshold);
                $isAlert = $zone !== BurnZone::Green;

                if ($isWorkingDay) {
                    $streak = $isAlert ? $streak + 1 : 0;
                    $dayStreak = $streak;
                } else {
                    $dayStreak = $isAlert ? $streak + 1 : 0;
                }

                $days[$section->id][$key] = [
                    'index_pct' => $index,
                    'actual_hours' => round($actual, 1),
                    'zone' => $zone,
                    'is_working_day' => $isWorkingDay,
                    'streak_days' => $dayStreak,
                ];
            }
        }

        return ['days' => $days, 'thresholds' => $thresholds];
    }

    /**
     * Department filter that applies to the user, or false when the user may not see any alert.
     *
     * Admin: any department (optional filter). Manager / Team Leader: always their own department,
     * whatever the client sent. Everyone else, or a non-admin without a department: nothing.
     */
    protected function resolveDepartmentScope(User $user, ?int $departmentId): int|false|null
    {
        if (! $this->canViewAlerts($user)) {
            return false;
        }

        [$scopedDepartmentId] = $this->kpiService->resolveScoping($user, $departmentId);

        if (! $user->isAdmin() && $scopedDepartmentId === null) {
            return false;
        }

        return $scopedDepartmentId;
    }

    protected function canViewAlerts(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isTeamLeader();
    }

    /**
     * @throws AuthorizationException
     */
    protected function assertCanViewSection(User $user, Section $section): void
    {
        $allowed = $this->canViewAlerts($user)
            && ($user->isAdmin()
                || ($user->department_id !== null && (int) $user->department_id === (int) $section->department_id));

        if (! $allowed) {
            throw new AuthorizationException;
        }
    }

    /**
     * @return EloquentCollection<int, Section>
     */
    protected function sectionsInScope(?int $departmentId, ?int $sectionId): EloquentCollection
    {
        return Section::query()
            ->with('department:id,name')
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->when($sectionId !== null, fn ($query) => $query->whereKey($sectionId))
            ->orderBy('id')
            ->get(['id', 'department_id', 'name']);
    }

    /**
     * Approved overtime hours grouped by section and operational date (single grouped query).
     *
     * The upper bound uses end-of-day so DATE and DATETIME storage both match on SQLite and MySQL.
     *
     * @param  list<int>  $sectionIds
     * @return array<int, array<string, float>> sectionId => 'Y-m-d' => hours
     */
    protected function approvedHoursBySectionDay(array $sectionIds, Carbon $from, Carbon $to): array
    {
        $rows = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_items.status', 'APPROVED')
            ->whereIn('overtime_submissions.section_id', $sectionIds)
            ->where('overtime_submissions.operational_date', '>=', $from->toDateString())
            ->where('overtime_submissions.operational_date', '<=', $to->copy()->endOfDay()->toDateTimeString())
            ->groupBy('overtime_submissions.section_id', 'overtime_submissions.operational_date')
            ->toBase()
            ->get([
                'overtime_submissions.section_id',
                'overtime_submissions.operational_date',
                DB::raw('SUM(overtime_items.total_hours) as total_hours'),
            ]);

        $hours = [];
        foreach ($rows as $row) {
            $hours[(int) $row->section_id][substr((string) $row->operational_date, 0, 10)] = (float) $row->total_hours;
        }

        return $hours;
    }

    /**
     * Day type (HKN / HLR) per date from the operational calendar.
     *
     * @return array<string, string>
     */
    protected function dayTypesBetween(Carbon $from, Carbon $to): array
    {
        $rows = OperationalCalendar::query()
            ->where('calendar_date', '>=', $from->toDateString())
            ->where('calendar_date', '<=', $to->copy()->endOfDay()->toDateTimeString())
            ->toBase()
            ->get(['calendar_date', 'day_type']);

        $dayTypes = [];
        foreach ($rows as $row) {
            $dayTypes[substr((string) $row->calendar_date, 0, 10)] = strtoupper((string) $row->day_type);
        }

        return $dayTypes;
    }

    /**
     * Effective zone limits per department (department override, else plant default) in one query.
     *
     * @param  EloquentCollection<int, Section>  $sections
     * @return array<int, PolicyThreshold>
     */
    protected function thresholdsFor(EloquentCollection $sections): array
    {
        $departmentIds = $sections->pluck('department_id')->unique()->values()->all();

        $rows = PolicyThreshold::query()
            ->whereNull('department_id')
            ->orWhereIn('department_id', $departmentIds)
            ->get();

        $thresholds = [];
        foreach ($rows as $row) {
            $thresholds[(int) ($row->department_id ?? self::PLANT_DEFAULT_KEY)] = $row;
        }

        $thresholds[self::PLANT_DEFAULT_KEY] ??= $this->thresholdService->getPlantDefault();

        foreach ($departmentIds as $departmentId) {
            $thresholds[(int) $departmentId] ??= $thresholds[self::PLANT_DEFAULT_KEY];
        }

        return $thresholds;
    }

    protected function thresholdKey(Section $section): int
    {
        return (int) $section->department_id;
    }

    /**
     * Latest follow-up (highest id) per section + alert date, keyed "sectionId|Y-m-d".
     *
     * @param  list<int>  $sectionIds
     * @return array<string, array{id: int, status: string}>
     */
    protected function latestFollowUps(array $sectionIds, Carbon $from, Carbon $to): array
    {
        $rows = AlertFollowUp::query()
            ->whereIn('section_id', $sectionIds)
            ->where('alert_date', '>=', $from->toDateString())
            ->where('alert_date', '<=', $to->copy()->endOfDay()->toDateTimeString())
            ->orderBy('id')
            ->get(['id', 'section_id', 'alert_date', 'status']);

        $latest = [];
        foreach ($rows as $row) {
            $latest[$row->section_id.'|'.$row->alert_date->toDateString()] = [
                'id' => $row->id,
                'status' => $row->status->value,
            ];
        }

        return $latest;
    }

    /**
     * @param  Day  $day
     * @param  array{id: int, status: string}|null  $followUp
     * @return Alert
     */
    protected function buildAlert(Section $section, string $date, array $day, PolicyThreshold $threshold, ?array $followUp): array
    {
        return [
            'section_id' => (int) $section->id,
            'section_name' => $section->name,
            'department_id' => (int) $section->department_id,
            'department_name' => $section->department?->name,
            'alert_date' => $date,
            'index_pct' => $day['index_pct'],
            'zone' => $day['zone']->value,
            'streak_days' => $day['streak_days'],
            'green_max_pct' => (float) $threshold->green_max_pct,
            'yellow_max_pct' => (float) $threshold->yellow_max_pct,
            'follow_up_status' => $followUp['status'] ?? 'none',
            'follow_up_id' => $followUp['id'] ?? null,
        ];
    }

    /**
     * Last working days up to the alert date (the alert date itself is always included, even on HLR).
     *
     * @param  array<string, Day>  $days
     * @return list<array{date: string, index_pct: float, actual_hours: float, zone: 'green'|'yellow'|'red', is_working_day: bool}>
     */
    protected function buildSeries(array $days, string $alertKey, string $fromKey): array
    {
        $series = [];
        $workingDays = 0;

        $keys = array_keys($days);
        rsort($keys);

        foreach ($keys as $key) {
            if ($key > $alertKey || $key < $fromKey) {
                continue;
            }

            $day = $days[$key];

            if (! $day['is_working_day'] && $key !== $alertKey) {
                continue;
            }

            $series[] = [
                'date' => $key,
                'index_pct' => $day['index_pct'],
                'actual_hours' => $day['actual_hours'],
                'zone' => $day['zone']->value,
                'is_working_day' => $day['is_working_day'],
            ];

            if ($day['is_working_day'] && ++$workingDays >= self::SERIES_WORKING_DAYS) {
                break;
            }
        }

        return array_reverse($series);
    }

    /**
     * Merah before Kuning, then longest streak, then newest date, then section name.
     *
     * @param  Alert  $a
     * @param  Alert  $b
     */
    protected function compareAlerts(array $a, array $b): int
    {
        return [$a['zone'] === 'red' ? 0 : 1, $b['streak_days'], $b['alert_date'], $a['section_name']]
            <=> [$b['zone'] === 'red' ? 0 : 1, $a['streak_days'], $a['alert_date'], $b['section_name']];
    }

    /**
     * @return list<Carbon>
     */
    protected function datesBetween(Carbon $from, Carbon $to): array
    {
        $dates = [];

        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
            $dates[] = $cursor->copy();
        }

        return $dates;
    }

    protected function asCalendarDay(CarbonInterface $date): Carbon
    {
        return Carbon::parse($date->toDateString(), 'Asia/Jakarta')->startOfDay();
    }
}
