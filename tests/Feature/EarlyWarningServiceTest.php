<?php

use App\Enums\BurnZone;
use App\Models\AlertFollowUp;
use App\Models\Department;
use App\Models\Employee;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use App\Services\Analytics\DashboardKpiService;
use App\Services\EarlyWarningService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Calendar of the scenarios (today = Wed 14 Oct 2026): 3-4 Oct and 10-11 Oct are weekends (HLR).
 * The index is month-to-date. A 310 h October budget expects 10 h per calendar day (10 h by 1 Oct, 120 h by 12 Oct,
 * 140 h by 14 Oct), so with the default 70 / 90 limits the cumulative hours of day D are Hijau up to 7 x D,
 * Kuning up to 9 x D and Merah above. Hours below are listed per day and accumulate.
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 10:00:00', 'Asia/Jakarta'));

    PolicyThreshold::factory()->plantDefault()->create();

    $this->service = app(EarlyWarningService::class);
    $this->admin = User::factory()->admin()->create();
    $this->reviewer = User::factory()->admin()->create();
    $this->employee = Employee::factory()->create();
});

function ewSection(?Department $department = null, ?string $name = null, float $octoberBudget = 310.0): Section
{
    $department ??= Department::factory()->create();
    $section = Section::factory()->create(array_filter([
        'department_id' => $department->id,
        'name' => $name,
    ]));

    if ($octoberBudget > 0) {
        OvertimeBudget::factory()->forSection($section)->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 10,
            'planned_hours' => $octoberBudget,
        ]);
    }

    return $section;
}

function ewHours(Section $section, string $date, float $hours, string $status = 'approved'): void
{
    $submission = OvertimeSubmission::factory()->create([
        'department_id' => $section->department_id,
        'section_id' => $section->id,
        'operational_date' => $date,
        'submission_date' => $date,
    ]);

    $item = OvertimeItem::factory()->forEmployee(test()->employee);
    $item = $status === 'approved' ? $item->approved(test()->reviewer) : $item->pending();

    $item->create([
        'overtime_submission_id' => $submission->id,
        'hours_production' => $hours,
        'hours_tpm' => 0,
        'hours_project' => 0,
        'hours_others' => 0,
    ]);
}

function ewAlerts(?User $user = null, string $from = '2026-10-01', string $to = '2026-10-14', ?int $departmentId = null, ?int $sectionId = null, ?BurnZone $zone = null): array
{
    return test()->service
        ->alertsFor($user ?? test()->admin, Carbon::parse($from), Carbon::parse($to), $departmentId, $sectionId, $zone)
        ->all();
}

test('classifies each day with the section department boundaries and never returns Hijau days', function () {
    $standard = Department::factory()->create();
    $custom = Department::factory()->create();
    PolicyThreshold::factory()->forDepartment($custom)->create(['green_max_pct' => 85.0, 'yellow_max_pct' => 100.0]);

    $standardSection = ewSection($standard, 'Press A');
    $customSection = ewSection($custom, 'Weld B');

    // 100 h by 12 Oct = 83.3 %, 120 h by 13 Oct = 92.3 %.
    foreach ([$standardSection, $customSection] as $section) {
        ewHours($section, '2026-10-12', 100.0);
        ewHours($section, '2026-10-13', 20.0);
    }

    $alerts = collect(ewAlerts(to: '2026-10-13'));

    $byKey = fn (Section $section, string $date) => $alerts->first(
        fn (array $alert) => $alert['section_id'] === $section->id && $alert['alert_date'] === $date,
    );

    expect($alerts)->toHaveCount(3)
        ->and($byKey($standardSection, '2026-10-12')['zone'])->toBe('yellow')
        ->and($byKey($standardSection, '2026-10-13')['zone'])->toBe('red')
        ->and($byKey($customSection, '2026-10-12'))->toBeNull()
        ->and($byKey($customSection, '2026-10-13')['zone'])->toBe('yellow')
        ->and($byKey($customSection, '2026-10-13')['green_max_pct'])->toBe(85.0)
        ->and($byKey($customSection, '2026-10-13')['yellow_max_pct'])->toBe(100.0)
        ->and($alerts->pluck('alert_date'))->not->toContain('2026-10-11');
});

test('exposes the documented alert fields', function () {
    $department = Department::factory()->create(['name' => 'Press Dept']);
    $section = ewSection($department, 'Press Line 1');
    ewHours($section, '2026-10-13', 120.0);

    $alert = ewAlerts()[0];

    expect($alert)->toBe([
        'section_id' => $section->id,
        'section_name' => 'Press Line 1',
        'department_id' => $department->id,
        'department_name' => 'Press Dept',
        'alert_date' => '2026-10-13',
        'index_pct' => 92.3,
        'zone' => 'red',
        'streak_days' => 1,
        'green_max_pct' => 70.0,
        'yellow_max_pct' => 90.0,
        'follow_up_status' => 'none',
        'follow_up_id' => null,
    ]);
});

test('the daily index matches the dashboard early warning chart for the same section', function () {
    $section = ewSection(null, 'Parity Section');
    ewHours($section, '2026-10-06', 70.3);
    ewHours($section, '2026-10-08', 9.7);
    ewHours($section, '2026-10-13', 12.0);

    $trend = app(DashboardKpiService::class)->getDailyIndexTrend($this->admin, '2026-10-14', null, $section->id);

    $alerts = collect(ewAlerts());

    expect($alerts)->not->toBeEmpty();

    foreach ($alerts as $alert) {
        $dayIndex = (int) substr($alert['alert_date'], 8, 2) - 1;

        expect($alert['index_pct'])->toBe($trend['daily_indices'][$dayIndex])
            ->and($alert['zone'])->toBe($trend['daily_zones'][$dayIndex]);
    }
});

test('only approved hours count toward the daily index', function () {
    $section = ewSection();
    ewHours($section, '2026-10-13', 200.0, 'pending');

    expect(ewAlerts())->toBeEmpty();
});

/**
 * Kuning on Wed 7 - Fri 9 and Mon 12 (cumulative 50, 57, 64 and 86 h), nothing on the weekend or on Tue 13.
 */
function ewStreakMonth(Section $section): void
{
    ewHours($section, '2026-10-07', 50.0);
    ewHours($section, '2026-10-08', 7.0);
    ewHours($section, '2026-10-09', 7.0);
    ewHours($section, '2026-10-12', 22.0);
}

test('streak counts consecutive working days across a weekend and resets on a Hijau working day', function () {
    $section = ewSection();

    ewStreakMonth($section);
    // Wed 7, Thu 8, Fri 9 Kuning -> weekend (HLR, Hijau) -> Mon 12 Kuning. Tue 13 falls back to Hijau (86 h of 130 h
    // expected = 66 %) and resets the streak, Wed 14 climbs back to Kuning (99 h of 140 h = 70.7 %).
    ewHours($section, '2026-10-14', 13.0);

    $streaks = collect(ewAlerts())->pluck('streak_days', 'alert_date')->all();

    expect($streaks)->toEqual([
        '2026-10-07' => 1,
        '2026-10-08' => 2,
        '2026-10-09' => 3,
        '2026-10-12' => 4,
        '2026-10-14' => 1,
    ]);
});

test('streak reaches back before the requested range', function () {
    $section = ewSection();

    ewStreakMonth($section);

    $alerts = ewAlerts(from: '2026-10-12', to: '2026-10-14');

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['alert_date'])->toBe('2026-10-12')
        ->and($alerts[0]['streak_days'])->toBe(4);
});

test('a weekday holiday does not break the streak and an HLR alert day counts itself', function () {
    $section = ewSection();

    OperationalCalendar::factory()->forDate('2026-10-13')->holiday('Cuti Bersama')->create();

    ewHours($section, '2026-10-10', 80.0);  // Sat, HLR alert (80 h of 100 h) -> counts itself only
    ewHours($section, '2026-10-12', 8.0);   // Mon, HKN (88 h of 120 h = 73 %) -> streak 1, not part of the Sat alert
    // Tue 13, HLR holiday: 88 h of 130 h = 68 % Hijau -> skipped, does not break the streak
    ewHours($section, '2026-10-14', 11.0);  // Wed, HKN (99 h of 140 h = 70.7 %) -> streak continues

    $streaks = collect(ewAlerts())->pluck('streak_days', 'alert_date')->all();

    expect($streaks['2026-10-10'])->toBe(1)
        ->and($streaks['2026-10-12'])->toBe(1)
        ->and($streaks['2026-10-14'])->toBe(2)
        ->and($streaks)->not->toHaveKey('2026-10-13');
});

test('streak continues across a month boundary when both months have a budget', function () {
    $section = ewSection();
    OvertimeBudget::factory()->forSection($section)->create([
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 300,
    ]);

    // September: 210 h by the 29th (72 %), 215 h by the 30th (72 %). October starts again from zero: 8 h by the 1st
    // (80 % of 10 h) and 16 h by the 2nd (80 % of 20 h).
    ewHours($section, '2026-09-29', 210.0);
    ewHours($section, '2026-09-30', 5.0);
    ewHours($section, '2026-10-01', 8.0);
    ewHours($section, '2026-10-02', 8.0);

    $alerts = ewAlerts(from: '2026-10-01', to: '2026-10-02');

    expect(collect($alerts)->pluck('streak_days', 'alert_date')->all())->toEqual([
        '2026-10-01' => 3,
        '2026-10-02' => 4,
    ]);
});

test('sections without a budget produce no alert while a burn snapshot is an accepted fallback', function () {
    $noBudget = ewSection(null, 'No Budget', 0);
    ewHours($noBudget, '2026-10-13', 200.0);

    $snapshotDepartment = Department::factory()->create();
    $snapshotSection = ewSection($snapshotDepartment, 'Snapshot Only', 0);
    MonthlyBurnSnapshot::factory()->forSection($snapshotSection)->create([
        'fiscal_year' => 2026,
        'fiscal_month' => 10,
        'planned_budget_hours' => 310,
    ]);
    ewHours($snapshotSection, '2026-10-13', 130.0);

    $alerts = ewAlerts(to: '2026-10-13');

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['section_id'])->toBe($snapshotSection->id)
        ->and($alerts[0]['index_pct'])->toBe(100.0);
});

test('sorts Merah first, then longest streak, then newest date', function () {
    $sectionA = ewSection(null, 'A Section');
    $sectionB = ewSection(null, 'B Section');

    ewHours($sectionA, '2026-10-12', 100.0); // A: 12 yellow (83 %), 13 yellow (77 %), 14 red after +30 h (93 %)
    ewHours($sectionA, '2026-10-14', 30.0);
    ewHours($sectionB, '2026-10-12', 110.0); // B: 12 red (92 %), 13 yellow (85 %), 14 yellow (79 %)

    $order = collect(ewAlerts())->map(fn (array $a) => $a['section_name'].' '.$a['alert_date'].' '.$a['zone'])->all();

    expect($order)->toBe([
        'A Section 2026-10-14 red',
        'B Section 2026-10-12 red',
        'B Section 2026-10-14 yellow',
        'A Section 2026-10-13 yellow',
        'B Section 2026-10-13 yellow',
        'A Section 2026-10-12 yellow',
    ]);
});

test('filters by department, section and zone', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $sectionA = ewSection($deptA);
    $sectionB = ewSection($deptB);

    // 12 Oct: 100 h = 83 % Kuning, 13 Oct: 120 h = 92 % Merah.
    foreach ([$sectionA, $sectionB] as $section) {
        ewHours($section, '2026-10-12', 100.0);
        ewHours($section, '2026-10-13', 20.0);
    }

    $to = '2026-10-13';

    expect(ewAlerts(to: $to))->toHaveCount(4)
        ->and(ewAlerts(to: $to, departmentId: $deptA->id))->toHaveCount(2)
        ->and(collect(ewAlerts(to: $to, departmentId: $deptA->id))->pluck('department_id')->unique()->all())->toBe([$deptA->id])
        ->and(ewAlerts(to: $to, sectionId: $sectionB->id))->toHaveCount(2)
        ->and(ewAlerts(to: $to, zone: BurnZone::Red))->toHaveCount(2)
        ->and(collect(ewAlerts(to: $to, zone: BurnZone::Yellow))->pluck('zone')->unique()->all())->toBe(['yellow'])
        ->and(ewAlerts(to: $to, departmentId: $deptA->id, sectionId: $sectionB->id))->toBeEmpty();
});

test('ignores future days and empty ranges', function () {
    $section = ewSection();
    ewHours($section, '2026-10-14', 120.0);
    ewHours($section, '2026-10-20', 80.0);

    expect(ewAlerts(from: '2026-10-14', to: '2026-10-31'))->toHaveCount(1)
        ->and(ewAlerts(from: '2026-10-15', to: '2026-10-31'))->toBeEmpty()
        ->and(ewAlerts(from: '2026-10-14', to: '2026-10-01'))->toBeEmpty();
});

test('non-admin users only receive alerts of their own department', function () {
    $own = Department::factory()->create();
    $other = Department::factory()->create();
    $ownSection = ewSection($own);
    $otherSection = ewSection($other);

    ewHours($ownSection, '2026-10-13', 100.0);
    ewHours($otherSection, '2026-10-13', 100.0);

    $manager = User::factory()->manager($own->id)->create();
    $teamLeader = User::factory()->teamLeader($ownSection->id, $own->id)->create();

    foreach ([$manager, $teamLeader] as $user) {
        $alerts = ewAlerts($user, to: '2026-10-13');
        expect($alerts)->toHaveCount(1)->and($alerts[0]['section_id'])->toBe($ownSection->id);

        // The department filter sent by the client is never trusted.
        expect(collect(ewAlerts($user, to: '2026-10-13', departmentId: $other->id))->pluck('department_id')->unique()->all())->toBe([$own->id])
            ->and(ewAlerts($user, to: '2026-10-13', sectionId: $otherSection->id))->toBeEmpty();
    }
});

test('operators and department-less non-admins receive nothing', function () {
    $section = ewSection();
    ewHours($section, '2026-10-13', 100.0);

    $operator = User::factory()->user()->create(['department_id' => $section->department_id]);
    $orphanManager = User::factory()->manager(null)->create();

    expect(ewAlerts($operator))->toBeEmpty()
        ->and(ewAlerts($orphanManager))->toBeEmpty()
        ->and(ewAlerts(to: '2026-10-13'))->toHaveCount(1);
});

test('reports the status of the latest follow-up of each alert', function () {
    $section = ewSection();
    // 100 h: Kuning on 12, 13 and 14 Oct (83 %, 77 % and 71 %).
    ewHours($section, '2026-10-12', 100.0);

    $draft = AlertFollowUp::factory()->forAlert($section, '2026-10-13')->create();
    AlertFollowUp::factory()->forAlert($section, '2026-10-14')->create();
    $sent = AlertFollowUp::factory()->forAlert($section, '2026-10-14')->sent()->create();
    // Legacy month-level rows without an alert date are never linked.
    AlertFollowUp::factory()->create(['section_id' => $section->id, 'alert_date' => null]);

    $alerts = collect(ewAlerts())->keyBy('alert_date');

    expect($alerts['2026-10-12']['follow_up_status'])->toBe('none')
        ->and($alerts['2026-10-12']['follow_up_id'])->toBeNull()
        ->and($alerts['2026-10-13']['follow_up_status'])->toBe('draft')
        ->and($alerts['2026-10-13']['follow_up_id'])->toBe($draft->id)
        ->and($alerts['2026-10-14']['follow_up_status'])->toBe('sent')
        ->and($alerts['2026-10-14']['follow_up_id'])->toBe($sent->id);
});

test('computing a whole plant range uses a constant number of queries', function () {
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        ewAlerts(from: '2026-10-01', to: '2026-10-14');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $first = ewSection(null, 'Solo');
    ewHours($first, '2026-10-13', 100.0);
    AlertFollowUp::factory()->forAlert($first, '2026-10-13')->create();

    $small = $countQueries();

    foreach (range(1, 5) as $i) {
        $section = ewSection(null, "Bulk {$i}");
        foreach (['2026-10-02', '2026-10-05', '2026-10-06', '2026-10-12', '2026-10-13'] as $date) {
            ewHours($section, $date, 80.0 + ($i % 2));
        }
        AlertFollowUp::factory()->forAlert($section, '2026-10-13')->create();
    }

    $large = $countQueries();

    expect($large)->toBe($small)
        ->and($large)->toBeLessThanOrEqual(10);
});

test('alert detail returns the alert, the last seven working days and the zone limits', function () {
    $section = ewSection();

    // Kuning every day from Mon 5 Oct (the weekend is Kuning too but HLR), Merah on the 14th: 140 h of 140 h = 100 %.
    ewHours($section, '2026-10-05', 40.0);
    foreach (['2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-12', '2026-10-13'] as $date) {
        ewHours($section, $date, 10.0);
    }
    ewHours($section, '2026-10-14', 40.0);
    $followUp = AlertFollowUp::factory()->forAlert($section, '2026-10-14')->create();

    $detail = $this->service->alertDetail($this->admin, $section, Carbon::parse('2026-10-14'));

    expect($detail['alert'])->toMatchArray([
        'section_id' => $section->id,
        'alert_date' => '2026-10-14',
        'zone' => 'red',
        'streak_days' => 8,
        'follow_up_status' => 'draft',
        'follow_up_id' => $followUp->id,
    ])
        ->and($detail['green_max_pct'])->toBe(70.0)
        ->and($detail['yellow_max_pct'])->toBe(90.0)
        ->and(array_column($detail['series'], 'date'))->toBe([
            '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-12', '2026-10-13', '2026-10-14',
        ])
        ->and(collect($detail['series'])->every(fn (array $day) => $day['is_working_day']))->toBeTrue()
        ->and(end($detail['series'])['index_pct'])->toBe(100.0);
});

test('alert detail has no alert for a Hijau, budget-less or future date', function () {
    $section = ewSection();
    ewHours($section, '2026-10-13', 5.0);

    $hijau = $this->service->alertDetail($this->admin, $section, Carbon::parse('2026-10-13'));
    $future = $this->service->alertDetail($this->admin, $section, Carbon::parse('2026-10-20'));
    $noBudget = $this->service->alertDetail($this->admin, ewSection(null, 'Empty', 0), Carbon::parse('2026-10-13'));

    expect($hijau['alert'])->toBeNull()
        ->and($hijau['series'])->not->toBeEmpty()
        ->and($future['alert'])->toBeNull()
        ->and($noBudget['alert'])->toBeNull()
        ->and($noBudget['series'])->toBeEmpty()
        ->and($noBudget['green_max_pct'])->toBe(70.0)
        ->and($this->service->isAlert($this->admin, $section, Carbon::parse('2026-10-13')))->toBeFalse();
});

test('alert detail rejects users outside the section department', function () {
    $section = ewSection();
    ewHours($section, '2026-10-13', 100.0);

    $otherManager = User::factory()->manager(Department::factory()->create()->id)->create();
    $ownManager = User::factory()->manager($section->department_id)->create();
    $operator = User::factory()->user()->create(['department_id' => $section->department_id]);

    expect($this->service->isAlert($ownManager, $section, Carbon::parse('2026-10-13')))->toBeTrue();

    expect(fn () => $this->service->alertDetail($otherManager, $section, Carbon::parse('2026-10-13')))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->alertDetail($operator, $section, Carbon::parse('2026-10-13')))
        ->toThrow(AuthorizationException::class);
});

test('month-to-date index calculation rounds the hours and handles a missing plan', function () {
    $kpi = app(DashboardKpiService::class);

    expect($kpi->calculateMonthToDateIndex(8.04, 310.0, 1, 31))->toBe(80.0)
        ->and($kpi->calculateMonthToDateIndex(100.0, 310.0, 12, 31))->toBe(83.3)
        ->and($kpi->calculateMonthToDateIndex(310.0, 310.0, 31, 31))->toBe(100.0)
        ->and($kpi->calculateMonthToDateIndex(3.0, 0.0, 5, 31))->toBe(100.0)
        ->and($kpi->calculateMonthToDateIndex(0.0, 0.0, 5, 31))->toBe(0.0)
        ->and($kpi->calculateExpectedHours(310.0, 12, 31))->toBe(120.0)
        ->and($kpi->calculateExpectedHours(310.0, 40, 31))->toBe(310.0)
        ->and($kpi->calculateExpectedHours(310.0, 0, 31))->toBe(0.0)
        ->and($kpi->calculateDailyPacing(310.0, 31))->toBe(10.0)
        ->and($kpi->calculateDailyPacing(310.0, 0))->toBe(0.0);
});

test('one busy day inside a month that is still under its pace is not an alert', function () {
    $section = ewSection();

    // 12 h on one day is far above the 10 h daily share, but 12 h of the 120 h expected by 12 Oct is only 10 %.
    ewHours($section, '2026-10-12', 12.0);

    expect(ewAlerts())->toBeEmpty();
});

test('a month that is over its pace stays flagged on the quiet days that follow', function () {
    $section = ewSection();

    ewHours($section, '2026-10-12', 130.0);   // 108 % of the 120 h expected by the 12th

    $alerts = collect(ewAlerts())->keyBy('alert_date');

    // No overtime on the 13th and 14th, yet the section is still far above its pace for the month so far.
    expect($alerts->keys()->sort()->values()->all())->toBe(['2026-10-12', '2026-10-13', '2026-10-14'])
        ->and($alerts['2026-10-12']['zone'])->toBe('red')
        ->and($alerts['2026-10-13']['index_pct'])->toBe(100.0)
        ->and($alerts['2026-10-14']['index_pct'])->toBe(92.9)
        ->and($alerts['2026-10-14']['streak_days'])->toBe(3);
});
