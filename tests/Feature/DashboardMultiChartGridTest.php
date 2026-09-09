<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Carbon;

test('supervisory users receive all 5 multi-chart props on dashboard visit', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('leaderboard')
        ->has('leaderboard.items')
        ->has('leaderboard.soft_limit_hours')
        ->has('categoryDistribution')
        ->has('categoryDistribution.categories')
        ->has('categoryDistribution.total_hours')
        ->has('trendWorkingTime')
        ->has('trendWorkingTime.labels')
        ->has('trendWorkingTime.hkn_series')
        ->has('trendWorkingTime.hlr_series')
        ->has('dailyIndexTrend')
        ->has('dailyIndexTrend.labels')
        ->has('dailyIndexTrend.daily_indices')
        ->has('dailyIndexTrend.threshold_pct')
        ->has('dayTypeBreakdown')
        ->has('dayTypeBreakdown.labels')
        ->has('dayTypeBreakdown.hkn_hours')
        ->has('dayTypeBreakdown.hlr_hours')
    );
});

test('leaderboard endpoint returns valid json structure for supervisory users', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson(route('dashboard.charts.leaderboard'));
    $response->assertOk();
    $response->assertJsonStructure([
        'items',
        'fiscal_year',
        'fiscal_month',
        'month_name',
        'soft_limit_hours',
        'scope' => ['department_id', 'section_id'],
    ]);
});

test('category distribution endpoint returns valid json structure for supervisory users', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson(route('dashboard.charts.category'));
    $response->assertOk();
    $response->assertJsonStructure([
        'total_hours',
        'categories' => [
            '*' => ['key', 'label', 'hours', 'percentage', 'color'],
        ],
        'fiscal_year',
        'fiscal_month',
        'month_name',
        'scope' => ['department_id', 'section_id'],
    ]);
});

test('12-month working time trend endpoint returns valid json structure for supervisory users', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson(route('dashboard.charts.trend-working'));
    $response->assertOk();
    $response->assertJsonStructure([
        'labels',
        'hkn_series',
        'hlr_series',
        'total_series',
        'total_hkn',
        'total_hlr',
        'grand_total',
        'fiscal_year',
        'fiscal_month',
        'month_name',
        'scope' => ['department_id', 'section_id'],
    ]);
});

test('daily index trend endpoint returns valid json structure for supervisory users', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson(route('dashboard.charts.daily-index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'labels',
        'daily_indices',
        'daily_hours',
        'planned_daily_pacing_hours',
        'threshold_pct',
        'average_index',
        'cutoff_day',
        'days_in_month',
        'fiscal_year',
        'fiscal_month',
        'month_name',
        'scope' => ['department_id', 'section_id'],
    ]);
});

test('weekly day type breakdown endpoint returns valid json structure for supervisory users', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson(route('dashboard.charts.day-type'));
    $response->assertOk();
    $response->assertJsonStructure([
        'labels',
        'hkn_hours',
        'hlr_hours',
        'total_hkn',
        'total_hlr',
        'grand_total',
        'hlr_ratio_pct',
        'fiscal_year',
        'fiscal_month',
        'month_name',
        'scope' => ['department_id', 'section_id'],
    ]);
});

test('operator user is forbidden from all 5 chart endpoints', function () {
    $user = User::factory()->create(); // operator role
    $this->actingAs($user);

    $this->getJson(route('dashboard.charts.leaderboard'))->assertForbidden();
    $this->getJson(route('dashboard.charts.category'))->assertForbidden();
    $this->getJson(route('dashboard.charts.trend-working'))->assertForbidden();
    $this->getJson(route('dashboard.charts.daily-index'))->assertForbidden();
    $this->getJson(route('dashboard.charts.day-type'))->assertForbidden();
});

test('unauthenticated visitor is redirected or rejected from chart endpoints', function () {
    $this->getJson(route('dashboard.charts.leaderboard'))->assertUnauthorized();
    $this->getJson(route('dashboard.charts.category'))->assertUnauthorized();
    $this->getJson(route('dashboard.charts.trend-working'))->assertUnauthorized();
    $this->getJson(route('dashboard.charts.daily-index'))->assertUnauthorized();
    $this->getJson(route('dashboard.charts.day-type'))->assertUnauthorized();
});

test('chart endpoints calculate and aggregate data accurately with approved overtime items', function () {
    $dept = Department::create([
        'code' => 'DEPT_MCG',
        'name' => 'Machining Dept',
        'cost_center_code' => 'CC-MCG-01',
        'default_hourly_rate' => 40000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_MCG_01',
        'name' => 'CNC Milling Line',
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create();
    $teamLeader = User::factory()->teamLeader($dept->id, $section->id)->create();

    $emp1 = Employee::create([
        'npk' => 'EMP-MCG-01',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Budi Santoso',
        'job_position' => 'CNC Operator',
        'is_active' => true,
    ]);

    $emp2 = Employee::create([
        'npk' => 'EMP-MCG-02',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Agus Pratama',
        'job_position' => 'Setup Technician',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $year = $now->year;
    $month = $now->month;
    $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
    $date1 = "{$year}-{$monthStr}-05";
    $date2 = "{$year}-{$monthStr}-12";

    OperationalCalendar::create([
        'calendar_date' => $date1,
        'day_type' => 'HKN',
        'is_holiday' => false,
    ]);

    OperationalCalendar::create([
        'calendar_date' => $date2,
        'day_type' => 'HLR',
        'is_holiday' => true,
    ]);

    PolicyThreshold::create([
        'department_id' => $dept->id,
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.0,
        'burn_danger_pct' => 115.0,
    ]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 100.0,
        'planned_amount' => 4000000,
    ]);

    // Submission 1: HKN
    $sub1 = OvertimeSubmission::create([
        'submission_code' => 'SPKL-MCG-001',
        'submission_date' => $date1,
        'operational_date' => $date1,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 12.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub1->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'hours_production' => 5.0,
        'hours_tpm' => 2.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 7.0,
        'hourly_rate_snapshot' => 40000,
        'total_cost_snapshot' => 280000,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub1->id,
        'employee_id' => $emp2->id,
        'npk_snapshot' => $emp2->npk,
        'hours_production' => 3.0,
        'hours_tpm' => 0.0,
        'hours_project' => 2.0,
        'hours_others' => 0.0,
        'total_hours' => 5.0,
        'hourly_rate_snapshot' => 40000,
        'total_cost_snapshot' => 200000,
        'status' => 'APPROVED',
    ]);

    // Submission 2: HLR
    $sub2 = OvertimeSubmission::create([
        'submission_code' => 'SPKL-MCG-002',
        'submission_date' => $date2,
        'operational_date' => $date2,
        'day_type' => 'HLR',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 8.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub2->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 8.0,
        'hours_others' => 0.0,
        'total_hours' => 8.0,
        'hourly_rate_snapshot' => 40000,
        'total_cost_snapshot' => 320000,
        'status' => 'APPROVED',
    ]);

    $this->actingAs($admin);

    // 1. Leaderboard check
    $lbResponse = $this->getJson(route('dashboard.charts.leaderboard', ['date' => $date1]));
    $lbResponse->assertOk();
    $items = $lbResponse->json('items');
    expect($items)->toHaveCount(2)
        ->and($items[0]['employee_id'])->toBe($emp1->id)
        ->and($items[0]['total_hours'])->toEqual(15.0)
        ->and($items[1]['employee_id'])->toBe($emp2->id)
        ->and($items[1]['total_hours'])->toEqual(5.0);

    // 2. Category distribution check
    $catResponse = $this->getJson(route('dashboard.charts.category', ['date' => $date1]));
    $catResponse->assertOk();
    expect($catResponse->json('total_hours'))->toEqual(20.0);
    $categories = collect($catResponse->json('categories'))->keyBy('key');
    expect($categories['production']['hours'])->toEqual(8.0)
        ->and($categories['tpm']['hours'])->toEqual(2.0)
        ->and($categories['project']['hours'])->toEqual(10.0)
        ->and($categories['others']['hours'])->toEqual(0.0);

    // 3. Trend working time check
    $trendResponse = $this->getJson(route('dashboard.charts.trend-working', ['date' => $date1]));
    $trendResponse->assertOk();
    expect($trendResponse->json('total_hkn'))->toEqual(12.0)
        ->and($trendResponse->json('total_hlr'))->toEqual(8.0)
        ->and($trendResponse->json('grand_total'))->toEqual(20.0);

    // 4. Day type breakdown check
    $dayTypeResponse = $this->getJson(route('dashboard.charts.day-type', ['date' => $date1]));
    $dayTypeResponse->assertOk();
    expect($dayTypeResponse->json('total_hkn'))->toEqual(12.0)
        ->and($dayTypeResponse->json('total_hlr'))->toEqual(8.0)
        ->and($dayTypeResponse->json('grand_total'))->toEqual(20.0)
        ->and($dayTypeResponse->json('hlr_ratio_pct'))->toEqual(40.0);

    // 5. Daily index trend check
    $dailyIndexResponse = $this->getJson(route('dashboard.charts.daily-index', ['date' => $date1]));
    $dailyIndexResponse->assertOk();
    expect($dailyIndexResponse->json('threshold_pct'))->toEqual(100.0)
        ->and($dailyIndexResponse->json('planned_daily_pacing_hours'))->toBeGreaterThan(0.0);
});

test('manager is scoped to their department automatically on chart endpoints', function () {
    $deptA = Department::create([
        'code' => 'DEPT_A',
        'name' => 'Dept Alpha',
        'cost_center_code' => 'CC-A',
        'default_hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $deptB = Department::create([
        'code' => 'DEPT_B',
        'name' => 'Dept Beta',
        'cost_center_code' => 'CC-B',
        'default_hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $secA = Section::create([
        'department_id' => $deptA->id,
        'code' => 'SEC_A',
        'name' => 'Section Alpha',
        'is_active' => true,
    ]);

    $secB = Section::create([
        'department_id' => $deptB->id,
        'code' => 'SEC_B',
        'name' => 'Section Beta',
        'is_active' => true,
    ]);

    $managerA = User::factory()->manager($deptA->id)->create();

    $empA = Employee::create([
        'npk' => 'EMP-A',
        'department_id' => $deptA->id,
        'section_id' => $secA->id,
        'full_name' => 'Worker A',
        'job_position' => 'Operator',
        'is_active' => true,
    ]);

    $empB = Employee::create([
        'npk' => 'EMP-B',
        'department_id' => $deptB->id,
        'section_id' => $secB->id,
        'full_name' => 'Worker B',
        'job_position' => 'Operator',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $date = $now->toDateString();

    OperationalCalendar::create([
        'calendar_date' => $date,
        'day_type' => 'HKN',
        'is_holiday' => false,
    ]);

    $subA = OvertimeSubmission::create([
        'submission_code' => 'SPKL-A',
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $deptA->id,
        'section_id' => $secA->id,
        'submitted_by_user_id' => $managerA->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 6.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subA->id,
        'employee_id' => $empA->id,
        'npk_snapshot' => $empA->npk,
        'hours_production' => 6.0,
        'total_hours' => 6.0,
        'hourly_rate_snapshot' => 35000,
        'total_cost_snapshot' => 210000,
        'status' => 'APPROVED',
    ]);

    $subB = OvertimeSubmission::create([
        'submission_code' => 'SPKL-B',
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $deptB->id,
        'section_id' => $secB->id,
        'submitted_by_user_id' => $managerA->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 10.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subB->id,
        'employee_id' => $empB->id,
        'npk_snapshot' => $empB->npk,
        'hours_production' => 10.0,
        'total_hours' => 10.0,
        'hourly_rate_snapshot' => 35000,
        'total_cost_snapshot' => 350000,
        'status' => 'APPROVED',
    ]);

    $this->actingAs($managerA);

    $lbResponse = $this->getJson(route('dashboard.charts.leaderboard'));
    $lbResponse->assertOk();
    $items = $lbResponse->json('items');
    expect($items)->toHaveCount(1)
        ->and($items[0]['employee_id'])->toBe($empA->id)
        ->and($items[0]['total_hours'])->toEqual(6.0);
});
