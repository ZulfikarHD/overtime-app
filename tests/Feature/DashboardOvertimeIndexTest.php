<?php

use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use App\Services\Analytics\DashboardKpiService;
use Illuminate\Support\Carbon;

// ============================================================================
// getDailyBurnUpIndex
// ============================================================================

test('getDailyBurnUpIndex returns correct structure with required keys', function () {
    $admin = User::factory()->admin()->create();
    $service = app(DashboardKpiService::class);

    $result = $service->getDailyBurnUpIndex($admin);

    expect($result)->toHaveKeys([
        'labels',
        'plan_index_cumulative',
        'actual_index_cumulative',
        'total_plan_index',
        'total_actual_index',
        'burn_index_pct',
        'cutoff_day',
        'days_in_month',
        'fiscal_year',
        'fiscal_month',
        'month_name',
        'scope',
    ]);
});

test('getDailyBurnUpIndex labels count equals days in the current month', function () {
    $admin = User::factory()->admin()->create();
    $service = app(DashboardKpiService::class);

    $result = $service->getDailyBurnUpIndex($admin);
    $expectedDays = Carbon::now('Asia/Jakarta')->daysInMonth;

    expect($result['labels'])->toHaveCount($expectedDays);
    expect($result['plan_index_cumulative'])->toHaveCount($expectedDays);
    expect($result['actual_index_cumulative'])->toHaveCount($expectedDays);
});

test('getDailyBurnUpIndex plan_index_cumulative is monotonically non-decreasing', function () {
    $admin = User::factory()->admin()->create();
    $section = Section::factory()->create();

    OvertimeBudget::factory()->forSection($section)->create([
        'fiscal_year' => Carbon::now()->year,
        'fiscal_month' => Carbon::now()->month,
        'planned_hours' => 200,
    ]);

    $service = app(DashboardKpiService::class);
    $result = $service->getDailyBurnUpIndex($admin);

    $plan = $result['plan_index_cumulative'];
    for ($i = 1; $i < count($plan); $i++) {
        expect($plan[$i])->toBeGreaterThanOrEqual($plan[$i - 1]);
    }
});

test('getDailyBurnUpIndex applies HKN multiplier (1.5) for normal-day submissions', function () {
    $admin = User::factory()->admin()->create();
    $section = Section::factory()->create();
    $employee = Employee::factory()->forDepartmentAndSection($section->department_id, $section->id)->create();

    $now = Carbon::now('Asia/Jakarta');
    $testDate = $now->copy()->startOfMonth()->toDateString();

    // Seed one HKN calendar entry
    OperationalCalendar::create([
        'calendar_date' => $testDate,
        'day_type' => 'HKN',
        'is_holiday' => false,
        'holiday_name' => null,
        'description' => 'Test HKN day',
    ]);

    $sub = OvertimeSubmission::factory()->forSection($section)->onDate($testDate)->create([
        'day_type' => 'HKN',
        'status' => 'APPROVED',
    ]);

    OvertimeItem::factory()->forEmployee($employee)->approved($admin)->create([
        'overtime_submission_id' => $sub->id,
        'hours_production' => 4.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
    ]);

    $service = app(DashboardKpiService::class);
    $result = $service->getDailyBurnUpIndex($admin);

    // The actual index at day 1 should be 4.0 × 1.5 = 6.0
    $day1Value = $result['actual_index_cumulative'][0];
    expect($day1Value)->toBeFloat()->toEqual(6.0);
});

test('getDailyBurnUpIndex applies HLR multiplier (2.0) for holiday submissions', function () {
    $admin = User::factory()->admin()->create();
    $section = Section::factory()->create();
    $employee = Employee::factory()->forDepartmentAndSection($section->department_id, $section->id)->create();

    $now = Carbon::now('Asia/Jakarta');
    $testDate = $now->copy()->startOfMonth()->toDateString();

    OperationalCalendar::create([
        'calendar_date' => $testDate,
        'day_type' => 'HLR',
        'is_holiday' => true,
        'holiday_name' => 'Test Holiday',
        'description' => 'Test HLR day',
    ]);

    $sub = OvertimeSubmission::factory()->forSection($section)->onDate($testDate)->create([
        'day_type' => 'HLR',
        'status' => 'APPROVED',
    ]);

    OvertimeItem::factory()->forEmployee($employee)->approved($admin)->create([
        'overtime_submission_id' => $sub->id,
        'hours_production' => 4.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
    ]);

    $service = app(DashboardKpiService::class);
    $result = $service->getDailyBurnUpIndex($admin);

    // 4.0 × 2.0 = 8.0
    $day1Value = $result['actual_index_cumulative'][0];
    expect($day1Value)->toBeFloat()->toEqual(8.0);
});

test('getDailyBurnUpIndex returns null for future days beyond cutoff', function () {
    $admin = User::factory()->admin()->create();
    $service = app(DashboardKpiService::class);

    $result = $service->getDailyBurnUpIndex($admin);

    $daysInMonth = Carbon::now('Asia/Jakarta')->daysInMonth;
    $cutoff = $result['cutoff_day'];

    if ($cutoff < $daysInMonth) {
        expect($result['actual_index_cumulative'][$cutoff])->toBeNull();
    }

    // Past days should have numeric values
    if ($cutoff > 0) {
        expect($result['actual_index_cumulative'][0])->not->toBeNull();
    }
});

test('getDailyBurnUpIndex burn_index_pct is zero when no planned hours', function () {
    $admin = User::factory()->admin()->create();
    $service = app(DashboardKpiService::class);

    // No budget seeded → plan = 0, burn_index_pct should be 0
    $result = $service->getDailyBurnUpIndex($admin);

    if ($result['total_plan_index'] === 0.0) {
        expect($result['burn_index_pct'])->toEqual(0.0);
    }
});

// ============================================================================
// getCategoryDistribution — Plan vs Actual
// ============================================================================

test('getCategoryDistribution now includes total_planned and per-category planned_hours', function () {
    $admin = User::factory()->admin()->create();
    $service = app(DashboardKpiService::class);

    $result = $service->getCategoryDistribution($admin);

    expect($result)->toHaveKey('total_planned');
    expect($result['categories'][0])->toHaveKey('planned_hours');
    expect($result['categories'][0])->toHaveKey('percentage_of_plan');
});

test('getCategoryDistribution planned_hours come from OvertimeBudget category columns', function () {
    $admin = User::factory()->admin()->create();
    $section = Section::factory()->create();

    OvertimeBudget::factory()->forSection($section)->create([
        'fiscal_year' => Carbon::now()->year,
        'fiscal_month' => Carbon::now()->month,
        'planned_production_hours' => 100.0,
        'planned_tpm_hours' => 20.0,
        'planned_project_hours' => 50.0,
        'planned_others_hours' => 30.0,
    ]);

    $service = app(DashboardKpiService::class);
    $result = $service->getCategoryDistribution($admin);

    $byKey = collect($result['categories'])->keyBy('key');
    expect($byKey['production']['planned_hours'])->toBeGreaterThanOrEqual(100.0);
    expect($byKey['tpm']['planned_hours'])->toBeGreaterThanOrEqual(20.0);
});

test('getCategoryDistribution percentage_of_plan is 0 when no actual hours', function () {
    $admin = User::factory()->admin()->create();
    $section = Section::factory()->create();

    OvertimeBudget::factory()->forSection($section)->create([
        'fiscal_year' => Carbon::now()->year,
        'fiscal_month' => Carbon::now()->month,
        'planned_production_hours' => 100.0,
        'planned_tpm_hours' => 10.0,
        'planned_project_hours' => 50.0,
        'planned_others_hours' => 40.0,
    ]);

    $service = app(DashboardKpiService::class);
    $result = $service->getCategoryDistribution($admin);

    // No OvertimeItems exist for this admin's scope with data → planned is set but actual is 0
    foreach ($result['categories'] as $cat) {
        if ($cat['hours'] === 0.0 && $cat['planned_hours'] > 0) {
            expect($cat['percentage_of_plan'])->toEqual(0.0);
        }
    }
});

// ============================================================================
// getYtdOvertimeIndex
// ============================================================================

test('getYtdOvertimeIndex returns 12 months of data for the fiscal year', function () {
    $admin = User::factory()->admin()->create();
    $service = app(DashboardKpiService::class);

    $result = $service->getYtdOvertimeIndex($admin);

    expect($result['labels'])->toHaveCount(12);
    expect($result['plan_index'])->toHaveCount(12);
    expect($result['actual_index'])->toHaveCount(12);
    expect($result['man_power'])->toHaveCount(12);
});

test('getYtdOvertimeIndex future months have zero actual_index', function () {
    $admin = User::factory()->admin()->create();
    $service = app(DashboardKpiService::class);

    $result = $service->getYtdOvertimeIndex($admin);
    $currentMonth = Carbon::now('Asia/Jakarta')->month;

    // Months after current month should have no actual data
    for ($m = $currentMonth + 1; $m <= 12; $m++) {
        expect($result['actual_index'][$m - 1])->toEqual(0.0);
    }
});

test('getYtdOvertimeIndex plan_index is non-zero for months with budget', function () {
    $admin = User::factory()->admin()->create();
    $section = Section::factory()->create();

    OvertimeBudget::factory()->forSection($section)->create([
        'fiscal_year' => Carbon::now()->year,
        'fiscal_month' => 1,
        'planned_hours' => 200,
    ]);

    $service = app(DashboardKpiService::class);
    $result = $service->getYtdOvertimeIndex($admin, Carbon::now()->toDateString());

    expect($result['plan_index'][0])->toBeGreaterThan(0.0);
});

test('getYtdOvertimeIndex actual_index uses correct multipliers based on day_type', function () {
    $admin = User::factory()->admin()->create();
    $section = Section::factory()->create();
    $employee = Employee::factory()->forDepartmentAndSection($section->department_id, $section->id)->create();

    // Create one HKN item in January 2026 = 4h × 1.5 = 6.0 index
    $sub = OvertimeSubmission::factory()->forSection($section)->onDate('2026-01-05')->create([
        'day_type' => 'HKN',
        'status' => 'APPROVED',
    ]);
    OvertimeItem::factory()->forEmployee($employee)->approved($admin)->create([
        'overtime_submission_id' => $sub->id,
        'hours_production' => 4.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
    ]);

    $service = app(DashboardKpiService::class);
    $result = $service->getYtdOvertimeIndex($admin);

    // January actual index should be ≥ 6.0 (4.0 × 1.5)
    expect($result['actual_index'][0])->toBeGreaterThanOrEqual(6.0);
});

// ============================================================================
// Dashboard page props include new keys
// ============================================================================

test('dashboard page includes dailyBurnUpIndex and ytdOvertimeIndex props', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('dailyBurnUpIndex')
        ->has('dailyBurnUpIndex.labels')
        ->has('dailyBurnUpIndex.plan_index_cumulative')
        ->has('dailyBurnUpIndex.actual_index_cumulative')
        ->has('dailyBurnUpIndex.total_plan_index')
        ->has('dailyBurnUpIndex.total_actual_index')
        ->has('ytdOvertimeIndex')
        ->has('ytdOvertimeIndex.labels')
        ->has('ytdOvertimeIndex.plan_index')
        ->has('ytdOvertimeIndex.actual_index')
        ->has('categoryDistribution.total_planned')
    );
});

test('OvertimeBudget model accepts per-category planned hour columns', function () {
    $section = Section::factory()->create();

    $budget = OvertimeBudget::factory()->forSection($section)->create([
        'planned_hours' => 200.0,
        'planned_production_hours' => 110.0,
        'planned_tpm_hours' => 10.0,
        'planned_project_hours' => 50.0,
        'planned_others_hours' => 30.0,
    ]);

    expect($budget->planned_production_hours)->toEqual('110.00');
    expect($budget->planned_tpm_hours)->toEqual('10.00');
    expect($budget->planned_project_hours)->toEqual('50.00');
    expect($budget->planned_others_hours)->toEqual('30.00');
});
