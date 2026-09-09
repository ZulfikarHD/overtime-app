<?php

use App\Jobs\RecalculateMonthlyBurnSnapshotJob;
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
use App\Notifications\BudgetThresholdAlert;
use App\Services\BudgetAlertService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $todayWib = Carbon::now('Asia/Jakarta')->format('Y-m-d');
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $todayWib],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );
});

test('budget alert service sends warning notification when burn index crosses warning threshold', function () {
    Notification::fake();

    $dept = Department::factory()->create(['name' => 'Stamping Dept', 'is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Press Line A', 'is_active' => true]);

    $manager = User::factory()->manager($dept->id)->create(['is_active' => true]);
    $admin = User::factory()->admin()->create(['is_active' => true]);

    $snapshot = MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 103.0,
        'cumulative_opex_hours' => 103.0,
        'cumulative_capex_hours' => 0.0,
        'burn_index_pct' => 103.0,
        'burn_velocity' => 25.0,
        'burn_zone' => 'ZONE_3_WARNING',
        'warned_at' => null,
        'danger_at' => null,
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $service = app(BudgetAlertService::class);
    $service->evaluateAndNotify($snapshot);

    $snapshot->refresh();
    expect($snapshot->warned_at)->not->toBeNull()
        ->and($snapshot->danger_at)->toBeNull();

    Notification::assertSentTo([$admin, $manager], BudgetThresholdAlert::class, function (BudgetThresholdAlert $notification) use ($section) {
        $data = $notification->toArray(new User);

        return $data['alert_level'] === 'warning'
            && $data['section_id'] === $section->id
            && $data['burn_index_pct'] === 103.0
            && str_contains($data['title'], 'Peringatan');
    });
});

test('budget alert service sends danger notification when burn index crosses danger threshold', function () {
    Notification::fake();

    $dept = Department::factory()->create(['name' => 'Assembly Dept', 'is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Body Line', 'is_active' => true]);

    $manager = User::factory()->manager($dept->id)->create(['is_active' => true]);
    $admin = User::factory()->admin()->create(['is_active' => true]);

    $snapshot = MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 118.4,
        'cumulative_opex_hours' => 118.4,
        'cumulative_capex_hours' => 0.0,
        'burn_index_pct' => 118.4,
        'burn_velocity' => 30.0,
        'burn_zone' => 'ZONE_4_POOR',
        'warned_at' => null,
        'danger_at' => null,
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $service = app(BudgetAlertService::class);
    $service->evaluateAndNotify($snapshot);

    $snapshot->refresh();
    expect($snapshot->danger_at)->not->toBeNull()
        ->and($snapshot->warned_at)->not->toBeNull();

    Notification::assertSentTo([$admin, $manager], BudgetThresholdAlert::class, function (BudgetThresholdAlert $notification) use ($section) {
        $data = $notification->toArray(new User);

        return $data['alert_level'] === 'danger'
            && $data['section_id'] === $section->id
            && $data['burn_index_pct'] === 118.4
            && str_contains($data['title'], 'Kritis');
    });
});

test('notifications are sent only once per threshold crossing per section per fiscal month (anti-fatigue lock)', function () {
    Notification::fake();

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $manager = User::factory()->manager($dept->id)->create(['is_active' => true]);

    $initialWarnedAt = Carbon::now('Asia/Jakarta')->subHours(2);
    $snapshot = MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 105.0,
        'burn_index_pct' => 105.0,
        'burn_velocity' => 25.0,
        'burn_zone' => 'ZONE_3_WARNING',
        'warned_at' => $initialWarnedAt,
        'danger_at' => null,
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $service = app(BudgetAlertService::class);
    $service->evaluateAndNotify($snapshot);

    Notification::assertNothingSent();

    $snapshot->refresh();
    expect($snapshot->warned_at->timestamp)->toBe($initialWarnedAt->timestamp);
});

test('danger notification triggers when warning was already sent but danger was not yet reached', function () {
    Notification::fake();

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $manager = User::factory()->manager($dept->id)->create(['is_active' => true]);

    $warnedAt = Carbon::now('Asia/Jakarta')->subDays(3);
    $snapshot = MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 120.0,
        'burn_index_pct' => 120.0,
        'burn_velocity' => 30.0,
        'burn_zone' => 'ZONE_4_POOR',
        'warned_at' => $warnedAt,
        'danger_at' => null,
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $service = app(BudgetAlertService::class);
    $service->evaluateAndNotify($snapshot);

    $snapshot->refresh();
    expect($snapshot->danger_at)->not->toBeNull()
        ->and($snapshot->warned_at->timestamp)->toBe($warnedAt->timestamp);

    Notification::assertSentTo($manager, BudgetThresholdAlert::class, function (BudgetThresholdAlert $notif) {
        return $notif->alertLevel === 'danger';
    });
});

test('notifications do not leak to managers of other departments or operators', function () {
    Notification::fake();

    $deptTarget = Department::factory()->create(['name' => 'Target Dept', 'is_active' => true]);
    $deptOther = Department::factory()->create(['name' => 'Other Dept', 'is_active' => true]);

    $section = Section::factory()->create(['department_id' => $deptTarget->id, 'is_active' => true]);

    $targetManager = User::factory()->manager($deptTarget->id)->create(['is_active' => true]);
    $otherManager = User::factory()->manager($deptOther->id)->create(['is_active' => true]);
    $operator = User::factory()->user()->create(['department_id' => $deptTarget->id, 'is_active' => true]);

    $snapshot = MonthlyBurnSnapshot::create([
        'department_id' => $deptTarget->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 108.0,
        'burn_index_pct' => 108.0,
        'burn_velocity' => 25.0,
        'burn_zone' => 'ZONE_3_WARNING',
        'warned_at' => null,
        'danger_at' => null,
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $service = app(BudgetAlertService::class);
    $service->evaluateAndNotify($snapshot);

    Notification::assertSentTo($targetManager, BudgetThresholdAlert::class);
    Notification::assertNotSentTo($otherManager, BudgetThresholdAlert::class);
    Notification::assertNotSentTo($operator, BudgetThresholdAlert::class);
});

test('user preference budget_alerts disables notification for that user', function () {
    Notification::fake();

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);

    $optedInManager = User::factory()->manager($dept->id)->create([
        'is_active' => true,
        'preferences' => ['budget_threshold_alert' => true],
    ]);

    $optedOutManager = User::factory()->manager($dept->id)->create([
        'is_active' => true,
        'preferences' => ['notifications' => ['budget_alerts' => false]],
    ]);

    $snapshot = MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 105.0,
        'burn_index_pct' => 105.0,
        'burn_velocity' => 25.0,
        'burn_zone' => 'ZONE_3_WARNING',
        'warned_at' => null,
        'danger_at' => null,
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $service = app(BudgetAlertService::class);
    $service->evaluateAndNotify($snapshot);

    Notification::assertSentTo($optedInManager, BudgetThresholdAlert::class);
    Notification::assertNotSentTo($optedOutManager, BudgetThresholdAlert::class);
});

test('department custom policy threshold override is respected', function () {
    Notification::fake();

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $manager = User::factory()->manager($dept->id)->create(['is_active' => true]);

    // Custom threshold for this department: warning at 90%, danger at 105%
    PolicyThreshold::create([
        'department_id' => $dept->id,
        'burn_warning_pct' => 90.00,
        'burn_danger_pct' => 105.00,
    ]);

    // Snapshot at 92% (below default 100% warning, but above department 90% warning)
    $snapshot = MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 92.0,
        'burn_index_pct' => 92.0,
        'burn_velocity' => 20.0,
        'burn_zone' => 'ZONE_2_GOOD',
        'warned_at' => null,
        'danger_at' => null,
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $service = app(BudgetAlertService::class);
    $service->evaluateAndNotify($snapshot);

    $snapshot->refresh();
    expect($snapshot->warned_at)->not->toBeNull();

    Notification::assertSentTo($manager, BudgetThresholdAlert::class, function (BudgetThresholdAlert $notif) {
        return $notif->alertLevel === 'warning' && $notif->thresholdPct === 90.0;
    });
});

test('recalculate monthly burn snapshot job triggers budget alert evaluation', function () {
    Notification::fake();

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $manager = User::factory()->manager($dept->id)->create(['is_active' => true]);

    // Configure a budget of 50 hours
    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 50.0,
    ]);

    OperationalCalendar::firstOrCreate(
        ['calendar_date' => '2026-09-08'],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );

    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'hourly_rate' => 35000.0,
        'is_active' => true,
    ]);

    // Approved submission with 60 hours (burn index = 120%)
    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-ALERT-JOB-001',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 60.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 60.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000,
        'total_cost_snapshot' => 60.0 * 35000,
        'status' => 'APPROVED',
    ]);

    $job = new RecalculateMonthlyBurnSnapshotJob($section->id, 2026, 9);
    app()->call([$job, 'handle']);

    $snapshot = MonthlyBurnSnapshot::where('section_id', $section->id)
        ->where('fiscal_year', 2026)
        ->where('fiscal_month', 9)
        ->first();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->danger_at)->not->toBeNull();

    Notification::assertSentTo($manager, BudgetThresholdAlert::class, function (BudgetThresholdAlert $notif) {
        return $notif->alertLevel === 'danger';
    });
});

test('subsequent month receives fresh alert when threshold is crossed', function () {
    Notification::fake();

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $manager = User::factory()->manager($dept->id)->create(['is_active' => true]);

    // September snapshot was already warned and closed
    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 110.0,
        'burn_index_pct' => 110.0,
        'burn_velocity' => 25.0,
        'burn_zone' => 'ZONE_3_WARNING',
        'warned_at' => Carbon::now('Asia/Jakarta')->subMonth(),
        'danger_at' => null,
        'last_recalculated_at' => Carbon::now('Asia/Jakarta')->subMonth(),
    ]);

    // October snapshot crosses warning for the first time
    $octoberSnapshot = MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 10,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 104.0,
        'burn_index_pct' => 104.0,
        'burn_velocity' => 25.0,
        'burn_zone' => 'ZONE_3_WARNING',
        'warned_at' => null,
        'danger_at' => null,
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $service = app(BudgetAlertService::class);
    $service->evaluateAndNotify($octoberSnapshot);

    $octoberSnapshot->refresh();
    expect($octoberSnapshot->warned_at)->not->toBeNull();

    Notification::assertSentTo($manager, BudgetThresholdAlert::class, function (BudgetThresholdAlert $notif) {
        return $notif->snapshot->fiscal_month === 10;
    });
});

test('unconfigured budget with zero planned hours does not dispatch alerts', function () {
    Notification::fake();

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    User::factory()->manager($dept->id)->create(['is_active' => true]);

    $snapshot = MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 0.0,
        'cumulative_actual_hours' => 50.0,
        'burn_index_pct' => 0.0,
        'burn_velocity' => 0.0,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'warned_at' => null,
        'danger_at' => null,
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $service = app(BudgetAlertService::class);
    $service->evaluateAndNotify($snapshot);

    Notification::assertNothingSent();
    $snapshot->refresh();
    expect($snapshot->warned_at)->toBeNull()
        ->and($snapshot->danger_at)->toBeNull();
});

test('budget alert notification is accessible via notification controller index', function () {
    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'code' => 'SEC_TEST', 'name' => 'Testing Section', 'is_active' => true]);
    $manager = User::factory()->manager($dept->id)->create(['is_active' => true]);

    $snapshot = MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 118.0,
        'burn_index_pct' => 118.0,
        'burn_velocity' => 25.0,
        'burn_zone' => 'ZONE_4_POOR',
        'warned_at' => null,
        'danger_at' => null,
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $manager->notify(new BudgetThresholdAlert($snapshot, 'danger', 115.0));

    $response = $this->actingAs($manager)->getJson(route('notifications.index'));

    $response->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('notifications.0.data.notification_type', 'budget_threshold')
        ->assertJsonPath('notifications.0.data.alert_level', 'danger')
        ->assertJsonPath('notifications.0.data.section_id', $section->id)
        ->assertJsonPath('notifications.0.data.burn_index_pct', fn ($val) => (float) $val === 118.0);
});
