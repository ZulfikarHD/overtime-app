<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Carbon;

test('supervisory users receive dailyBurnChart and sectionBurnComparison props on dashboard visit', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('dailyBurnChart')
        ->has('dailyBurnChart.labels')
        ->has('dailyBurnChart.plan_cumulative')
        ->has('dailyBurnChart.actual_cumulative')
        ->has('dailyBurnChart.ml_projected')
        ->has('dailyBurnChart.planned_hours')
        ->has('dailyBurnChart.burn_zone')
        ->has('sectionBurnComparison')
        ->has('sectionBurnComparison.sections')
        ->has('sectionBurnComparison.total_sections')
    );
});

test('daily-burn endpoint returns json for authenticated supervisory users', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson(route('dashboard.charts.daily-burn'));
    $response->assertOk();
    $response->assertJsonStructure([
        'labels',
        'plan_cumulative',
        'actual_cumulative',
        'ml_projected',
        'planned_hours',
        'current_actual_hours',
        'burn_index_pct',
        'burn_zone',
        'burn_zone_label',
        'cutoff_day',
        'days_in_month',
        'fiscal_year',
        'fiscal_month',
        'month_name',
        'scope' => ['department_id', 'section_id'],
        'available_sections',
    ]);
});

test('section-burn endpoint returns json for authenticated supervisory users', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson(route('dashboard.charts.section-burn'));
    $response->assertOk();
    $response->assertJsonStructure([
        'sections' => [
            '*' => [
                'id',
                'code',
                'name',
                'department_id',
                'department_name',
                'planned_hours',
                'actual_hours',
                'burn_index_pct',
                'zone',
                'zone_label',
            ],
        ],
        'fiscal_year',
        'fiscal_month',
        'month_name',
        'total_sections',
        'critical_sections_count',
        'warning_sections_count',
        'on_track_sections_count',
        'safe_sections_count',
    ]);
});

test('operator role is forbidden from daily-burn and section-burn endpoints', function () {
    $user = User::factory()->create(); // operator role
    $this->actingAs($user);

    $this->getJson(route('dashboard.charts.daily-burn'))->assertForbidden();
    $this->getJson(route('dashboard.charts.section-burn'))->assertForbidden();
});

test('daily-burn chart accurately accumulates approved overtime items up to cutoff date', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $dept = Department::create([
        'code' => 'DEPT_TEST_DAILY',
        'name' => 'Testing Dept Daily',
        'cost_center_code' => 'CC-TST-001',
        'default_hourly_rate' => 40000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_TST_001',
        'name' => 'Testing Section 1',
        'is_active' => true,
    ]);

    $employee = Employee::create([
        'npk' => 'ISZ-9901',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Test Operator',
        'job_position' => 'Technician',
        'hourly_rate' => 40000,
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $year = $now->year;
    $month = $now->month;
    $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

    OperationalCalendar::create([
        'calendar_date' => "{$year}-{$monthStr}-01",
        'day_type' => 'HKN',
        'is_holiday' => false,
    ]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 100.0,
        'planned_amount' => 4000000,
    ]);

    // Create approved overtime submission on day 01
    $sub1 = OvertimeSubmission::create([
        'submission_code' => 'SUB-TST-001',
        'submission_date' => "{$year}-{$monthStr}-01",
        'operational_date' => "{$year}-{$monthStr}-01",
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 8.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub1->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 8.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 8.0,
        'hourly_rate_snapshot' => 40000,
        'total_cost_snapshot' => 320000,
        'status' => 'APPROVED',
        'reviewed_by_user_id' => $admin->id,
        'reviewed_at' => now(),
        'lock_version' => 1,
    ]);

    $response = $this->getJson(route('dashboard.charts.daily-burn', [
        'date' => "{$year}-{$monthStr}-01",
        'department_id' => $dept->id,
        'section_id' => $section->id,
    ]));

    $response->assertOk();
    $data = $response->json();

    expect((float) $data['planned_hours'])->toBe(100.0)
        ->and((float) $data['actual_cumulative'][0])->toBe(8.0)
        ->and((float) $data['plan_cumulative'][0])->toBeGreaterThan(0.0);
});

test('daily-burn chart applies role scoping for manager to assigned department', function () {
    $deptA = Department::create([
        'code' => 'DEPT_MGR_A',
        'name' => 'Manager Dept A',
        'cost_center_code' => 'CC-MGR-A',
        'default_hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $deptB = Department::create([
        'code' => 'DEPT_MGR_B',
        'name' => 'Manager Dept B',
        'cost_center_code' => 'CC-MGR-B',
        'default_hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($deptA->id)->create();
    $this->actingAs($manager);

    // Manager requests Dept B explicitly, but service must scope to Dept A
    $response = $this->getJson(route('dashboard.charts.daily-burn', [
        'department_id' => $deptB->id,
    ]));

    $response->assertOk();
    $data = $response->json();
    expect($data['scope']['department_id'])->toBe($deptA->id);
});

test('section-burn comparison orders sections descending by burn_index_pct', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $dept = Department::create([
        'code' => 'DEPT_ORDER_TEST',
        'name' => 'Ordering Test Dept',
        'cost_center_code' => 'CC-ORD-001',
        'default_hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $sec1 = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_LOW',
        'name' => 'Low Burn Section',
        'is_active' => true,
    ]);

    $sec2 = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_HIGH',
        'name' => 'High Burn Section',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $year = $now->year;
    $month = $now->month;

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $sec1->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 40.0,
        'burn_index_pct' => 40.0,
        'burn_velocity' => 10.0,
        'burn_zone' => 'ZONE_1_EXCELLENT',
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $sec2->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 125.0,
        'burn_index_pct' => 125.0,
        'burn_velocity' => 35.0,
        'burn_zone' => 'ZONE_4_POOR',
    ]);

    $response = $this->getJson(route('dashboard.charts.section-burn', [
        'department_id' => $dept->id,
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data['sections'])->toHaveCount(2)
        ->and($data['sections'][0]['code'])->toBe('SEC_HIGH')
        ->and($data['sections'][0]['zone'])->toBe('danger')
        ->and($data['sections'][1]['code'])->toBe('SEC_LOW')
        ->and($data['sections'][1]['zone'])->toBe('safe')
        ->and($data['critical_sections_count'])->toBe(1)
        ->and($data['safe_sections_count'])->toBe(1);
});

test('daily-burn chart handles zero planned hours gracefully without division by zero', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $dept = Department::create([
        'code' => 'DEPT_ZERO_TEST',
        'name' => 'Zero Budget Dept',
        'cost_center_code' => 'CC-ZERO-001',
        'default_hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_ZERO',
        'name' => 'Zero Section',
        'is_active' => true,
    ]);

    $response = $this->getJson(route('dashboard.charts.daily-burn', [
        'date' => '2026-09-01',
        'department_id' => $dept->id,
        'section_id' => $section->id,
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data['planned_hours'])->toEqual(0)
        ->and($data['burn_index_pct'])->toEqual(0)
        ->and($data['burn_zone'])->toBe('safe')
        ->and($data['plan_cumulative'][0])->toEqual(0);
});
