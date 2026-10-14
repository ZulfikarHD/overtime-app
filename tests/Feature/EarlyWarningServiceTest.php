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
 * A 310 h October budget gives a pacing of 10 h/day, so 8 h = 80 % (Kuning) and 10 h = 100 % (Merah)
 * with the default 70 / 90 limits; 5 h = 50 % (Hijau).
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

    foreach ([$standardSection, $customSection] as $section) {
        ewHours($section, '2026-10-12', 8.0);
        ewHours($section, '2026-10-13', 10.0);
        ewHours($section, '2026-10-14', 5.0);
    }

    $alerts = collect(ewAlerts());

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
        ->and($alerts->pluck('alert_date'))->not->toContain('2026-10-14');
});

test('exposes the documented alert fields', function () {
    $department = Department::factory()->create(['name' => 'Press Dept']);
    $section = ewSection($department, 'Press Line 1');
    ewHours($section, '2026-10-13', 9.5);

    $alert = ewAlerts()[0];

    expect($alert)->toBe([
        'section_id' => $section->id,
        'section_name' => 'Press Line 1',
        'department_id' => $department->id,
        'department_name' => 'Press Dept',
        'alert_date' => '2026-10-13',
        'index_pct' => 95.0,
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
    ewHours($section, '2026-10-06', 7.3);
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
    ewHours($section, '2026-10-13', 10.0, 'pending');

    expect(ewAlerts())->toBeEmpty();
});

test('streak counts consecutive working days across a weekend and resets on a Hijau working day', function () {
    $section = ewSection();

    // Wed 7, Thu 8, Fri 9 Kuning -> weekend (no overtime = Hijau but HLR) -> Mon 12 Kuning
    foreach (['2026-10-07', '2026-10-08', '2026-10-09', '2026-10-12'] as $date) {
        ewHours($section, $date, 8.0);
    }
    // Tue 13 Hijau resets, Wed 14 Kuning starts again
    ewHours($section, '2026-10-13', 5.0);
    ewHours($section, '2026-10-14', 8.0);

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

    foreach (['2026-10-07', '2026-10-08', '2026-10-09', '2026-10-12'] as $date) {
        ewHours($section, $date, 8.0);
    }

    $alerts = ewAlerts(from: '2026-10-12', to: '2026-10-14');

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['alert_date'])->toBe('2026-10-12')
        ->and($alerts[0]['streak_days'])->toBe(4);
});

test('a weekday holiday does not break the streak and an HLR alert day counts itself', function () {
    $section = ewSection();

    OperationalCalendar::factory()->forDate('2026-10-13')->holiday('Cuti Bersama')->create();

    ewHours($section, '2026-10-12', 8.0);   // Mon, HKN
    ewHours($section, '2026-10-13', 5.0);   // Tue, HLR holiday (Hijau) -> skipped
    ewHours($section, '2026-10-14', 8.0);   // Wed, HKN -> streak continues
    ewHours($section, '2026-10-10', 8.0);   // Sat, HLR alert -> counts itself only and is not part of the Mon streak

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

    foreach (['2026-09-29', '2026-09-30', '2026-10-01', '2026-10-02'] as $date) {
        ewHours($section, $date, 8.0);
    }

    $alerts = ewAlerts(from: '2026-10-01', to: '2026-10-02');

    expect(collect($alerts)->pluck('streak_days', 'alert_date')->all())->toEqual([
        '2026-10-01' => 3,
        '2026-10-02' => 4,
    ]);
});

test('sections without a budget produce no alert while a burn snapshot is an accepted fallback', function () {
    $noBudget = ewSection(null, 'No Budget', 0);
    ewHours($noBudget, '2026-10-13', 20.0);

    $snapshotDepartment = Department::factory()->create();
    $snapshotSection = ewSection($snapshotDepartment, 'Snapshot Only', 0);
    MonthlyBurnSnapshot::factory()->forSection($snapshotSection)->create([
        'fiscal_year' => 2026,
        'fiscal_month' => 10,
        'planned_budget_hours' => 310,
    ]);
    ewHours($snapshotSection, '2026-10-13', 10.0);

    $alerts = ewAlerts();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['section_id'])->toBe($snapshotSection->id)
        ->and($alerts[0]['index_pct'])->toBe(100.0);
});

test('sorts Merah first, then longest streak, then newest date', function () {
    $sectionA = ewSection(null, 'A Section');
    $sectionB = ewSection(null, 'B Section');

    ewHours($sectionA, '2026-10-12', 8.0);   // yellow, streak 1
    ewHours($sectionA, '2026-10-13', 8.0);   // yellow, streak 2
    ewHours($sectionB, '2026-10-13', 8.0);   // yellow, streak 1
    ewHours($sectionB, '2026-10-12', 10.0);  // red, streak 1 (older)
    ewHours($sectionA, '2026-10-14', 10.0);  // red, streak 3

    $order = collect(ewAlerts())->map(fn (array $a) => $a['section_name'].' '.$a['alert_date'].' '.$a['zone'])->all();

    expect($order)->toBe([
        'A Section 2026-10-14 red',
        'B Section 2026-10-12 red',
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

    foreach ([$sectionA, $sectionB] as $section) {
        ewHours($section, '2026-10-12', 8.0);
        ewHours($section, '2026-10-13', 10.0);
    }

    expect(ewAlerts())->toHaveCount(4)
        ->and(ewAlerts(departmentId: $deptA->id))->toHaveCount(2)
        ->and(collect(ewAlerts(departmentId: $deptA->id))->pluck('department_id')->unique()->all())->toBe([$deptA->id])
        ->and(ewAlerts(sectionId: $sectionB->id))->toHaveCount(2)
        ->and(ewAlerts(zone: BurnZone::Red))->toHaveCount(2)
        ->and(collect(ewAlerts(zone: BurnZone::Yellow))->pluck('zone')->unique()->all())->toBe(['yellow'])
        ->and(ewAlerts(departmentId: $deptA->id, sectionId: $sectionB->id))->toBeEmpty();
});

test('ignores future days and empty ranges', function () {
    $section = ewSection();
    ewHours($section, '2026-10-14', 8.0);
    ewHours($section, '2026-10-20', 8.0);

    expect(ewAlerts(from: '2026-10-14', to: '2026-10-31'))->toHaveCount(1)
        ->and(ewAlerts(from: '2026-10-15', to: '2026-10-31'))->toBeEmpty()
        ->and(ewAlerts(from: '2026-10-14', to: '2026-10-01'))->toBeEmpty();
});

test('non-admin users only receive alerts of their own department', function () {
    $own = Department::factory()->create();
    $other = Department::factory()->create();
    $ownSection = ewSection($own);
    $otherSection = ewSection($other);

    ewHours($ownSection, '2026-10-13', 8.0);
    ewHours($otherSection, '2026-10-13', 8.0);

    $manager = User::factory()->manager($own->id)->create();
    $teamLeader = User::factory()->teamLeader($ownSection->id, $own->id)->create();

    foreach ([$manager, $teamLeader] as $user) {
        $alerts = ewAlerts($user);
        expect($alerts)->toHaveCount(1)->and($alerts[0]['section_id'])->toBe($ownSection->id);

        // The department filter sent by the client is never trusted.
        expect(collect(ewAlerts($user, departmentId: $other->id))->pluck('department_id')->unique()->all())->toBe([$own->id])
            ->and(ewAlerts($user, sectionId: $otherSection->id))->toBeEmpty();
    }
});

test('operators and department-less non-admins receive nothing', function () {
    $section = ewSection();
    ewHours($section, '2026-10-13', 8.0);

    $operator = User::factory()->user()->create(['department_id' => $section->department_id]);
    $orphanManager = User::factory()->manager(null)->create();

    expect(ewAlerts($operator))->toBeEmpty()
        ->and(ewAlerts($orphanManager))->toBeEmpty()
        ->and(ewAlerts())->toHaveCount(1);
});

test('reports the status of the latest follow-up of each alert', function () {
    $section = ewSection();
    ewHours($section, '2026-10-12', 8.0);
    ewHours($section, '2026-10-13', 8.0);
    ewHours($section, '2026-10-14', 8.0);

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
    ewHours($first, '2026-10-13', 8.0);
    AlertFollowUp::factory()->forAlert($first, '2026-10-13')->create();

    $small = $countQueries();

    foreach (range(1, 5) as $i) {
        $section = ewSection(null, "Bulk {$i}");
        foreach (['2026-10-02', '2026-10-05', '2026-10-06', '2026-10-12', '2026-10-13'] as $date) {
            ewHours($section, $date, 8.0 + ($i % 2));
        }
        AlertFollowUp::factory()->forAlert($section, '2026-10-13')->create();
    }

    $large = $countQueries();

    expect($large)->toBe($small)
        ->and($large)->toBeLessThanOrEqual(10);
});

test('alert detail returns the alert, the last seven working days and the zone limits', function () {
    $section = ewSection();

    foreach (['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-12', '2026-10-13'] as $date) {
        ewHours($section, $date, 8.0);
    }
    ewHours($section, '2026-10-14', 10.0);
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
    ewHours($section, '2026-10-13', 8.0);

    $otherManager = User::factory()->manager(Department::factory()->create()->id)->create();
    $ownManager = User::factory()->manager($section->department_id)->create();
    $operator = User::factory()->user()->create(['department_id' => $section->department_id]);

    expect($this->service->isAlert($ownManager, $section, Carbon::parse('2026-10-13')))->toBeTrue();

    expect(fn () => $this->service->alertDetail($otherManager, $section, Carbon::parse('2026-10-13')))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->alertDetail($operator, $section, Carbon::parse('2026-10-13')))
        ->toThrow(AuthorizationException::class);
});

test('daily index calculation rounds the hours and handles a missing pacing target', function () {
    $kpi = app(DashboardKpiService::class);

    expect($kpi->calculateDailyIndex(8.04, 10.0))->toBe(80.0)
        ->and($kpi->calculateDailyIndex(3.0, 0.0))->toBe(100.0)
        ->and($kpi->calculateDailyIndex(0.0, 0.0))->toBe(0.0)
        ->and($kpi->calculateDailyPacing(310.0, 31))->toBe(10.0)
        ->and($kpi->calculateDailyPacing(310.0, 0))->toBe(0.0);
});
