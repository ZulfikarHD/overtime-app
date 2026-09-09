<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OperationalCalendar;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Carbon;

test('supervisory users receive kpiCards props on dashboard visit', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('kpiCards')
        ->has('kpiCards.production_volume')
        ->has('kpiCards.working_days')
        ->has('kpiCards.man_power')
        ->has('kpiCards.burn_index')
        ->has('departments')
    );
});

test('kpi-cards endpoint returns json for authenticated supervisory users', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson(route('dashboard.kpi-cards'));
    $response->assertOk();
    $response->assertJsonStructure([
        'production_volume' => ['erp_connected', 'message', 'target_volume', 'unit'],
        'working_days' => ['total_hkn_days', 'completed_hkn_days', 'remaining_hkn_days', 'progress_pct'],
        'man_power' => ['total_active_employees', 'active_shifts_count', 'sections'],
        'burn_index' => ['plan_pct', 'actual_pct', 'planned_hours', 'actual_hours', 'burn_zone', 'burn_zone_label'],
        'scope' => ['selected_date', 'fiscal_year', 'fiscal_month'],
    ]);
});

test('operator role is forbidden from kpi-cards json endpoint', function () {
    $user = User::factory()->create(); // operator role
    $this->actingAs($user);

    $response = $this->getJson(route('dashboard.kpi-cards'));
    $response->assertForbidden();
});

test('working days card correctly counts hkn days from operational_calendars', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $now = Carbon::now('Asia/Jakarta');
    $year = $now->year;
    $month = str_pad((string) $now->month, 2, '0', STR_PAD_LEFT);

    // Create 3 HKN and 1 HLR calendar days
    OperationalCalendar::create([
        'calendar_date' => "{$year}-{$month}-01",
        'day_type' => 'HKN',
        'is_holiday' => false,
    ]);
    OperationalCalendar::create([
        'calendar_date' => "{$year}-{$month}-02",
        'day_type' => 'HKN',
        'is_holiday' => false,
    ]);
    OperationalCalendar::create([
        'calendar_date' => "{$year}-{$month}-03",
        'day_type' => 'HKN',
        'is_holiday' => false,
    ]);
    OperationalCalendar::create([
        'calendar_date' => "{$year}-{$month}-04",
        'day_type' => 'HLR',
        'is_holiday' => true,
        'holiday_name' => 'Test Holiday',
    ]);

    $response = $this->getJson(route('dashboard.kpi-cards', ['date' => "{$year}-{$month}-02"]));
    $response->assertOk();

    $workingDays = $response->json('working_days');
    expect($workingDays['total_hkn_days'])->toBe(3)
        ->and($workingDays['completed_hkn_days'])->toBe(2)
        ->and($workingDays['remaining_hkn_days'])->toBe(1);
});

test('manpower card reflects active employees and section distribution', function () {
    $dept = Department::create([
        'code' => 'DEPT_TEST_MANPOWER',
        'name' => 'Assembly Dept',
        'cost_center_code' => 'CC-ASY-01',
        'default_hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $sec1 = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_ASY_1',
        'name' => 'Trim Line',
        'is_active' => true,
    ]);

    $sec2 = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_ASY_2',
        'name' => 'Chassis Line',
        'is_active' => true,
    ]);

    Employee::create([
        'npk' => 'ISZ-9001',
        'department_id' => $dept->id,
        'section_id' => $sec1->id,
        'full_name' => 'Active Emp 1',
        'job_position' => 'Operator',
        'hourly_rate' => 35000,
        'is_active' => true,
    ]);

    Employee::create([
        'npk' => 'ISZ-9002',
        'department_id' => $dept->id,
        'section_id' => $sec2->id,
        'full_name' => 'Active Emp 2',
        'job_position' => 'Operator',
        'hourly_rate' => 35000,
        'is_active' => true,
    ]);

    Employee::create([
        'npk' => 'ISZ-9003',
        'department_id' => $dept->id,
        'section_id' => $sec2->id,
        'full_name' => 'Inactive Emp 3',
        'job_position' => 'Operator',
        'hourly_rate' => 35000,
        'is_active' => false,
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson(route('dashboard.kpi-cards', ['department_id' => $dept->id]));
    $response->assertOk();

    $manPower = $response->json('man_power');
    expect($manPower['total_active_employees'])->toBe(2);

    $sections = collect($manPower['sections']);
    expect($sections->firstWhere('code', 'SEC_ASY_1')['count'])->toBe(1)
        ->and($sections->firstWhere('code', 'SEC_ASY_2')['count'])->toBe(1);
});

test('burn index card reflects snapshot metrics and zone evaluation', function () {
    $dept = Department::create([
        'code' => 'DEPT_TEST_BURN',
        'name' => 'Welding Dept',
        'cost_center_code' => 'CC-WLD-01',
        'default_hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $sec = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_WLD_1',
        'name' => 'Robotics Cell',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $year = $now->year;
    $month = $now->month;

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 92.5,
        'cumulative_opex_hours' => 70.0,
        'cumulative_capex_hours' => 22.5,
        'burn_index_pct' => 92.5,
        'burn_velocity' => 23.1,
        'burn_zone' => 'ZONE_2_GOOD',
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson(route('dashboard.kpi-cards', ['department_id' => $dept->id]));
    $response->assertOk();

    $burnIndex = $response->json('burn_index');
    expect($burnIndex['plan_pct'])->toEqual(100.0)
        ->and($burnIndex['actual_pct'])->toEqual(92.5)
        ->and($burnIndex['planned_hours'])->toEqual(100.0)
        ->and($burnIndex['actual_hours'])->toEqual(92.5)
        ->and($burnIndex['burn_zone'])->toBe('on_track');
});

test('production volume card gracefully degrades when erp is not connected', function () {
    config(['services.erp.connected' => false]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson(route('dashboard.kpi-cards'));
    $response->assertOk();

    $production = $response->json('production_volume');
    expect($production['erp_connected'])->toBeFalse()
        ->and($production['current_volume'])->toBeNull()
        ->and($production['target_volume'])->toBe(1450)
        ->and($production['message'])->toContain('ERP belum terhubung');
});

test('production volume card returns 14 day sparkline progression when erp is connected', function () {
    config(['services.erp.connected' => true]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->getJson(route('dashboard.kpi-cards'));
    $response->assertOk();

    $production = $response->json('production_volume');
    expect($production['erp_connected'])->toBeTrue()
        ->and($production['current_volume'])->toBeGreaterThan(0)
        ->and(count($production['sparkline_14d']))->toBe(14)
        ->and(count($production['labels']))->toBe(14);
});

test('malformed date query does not crash the dashboard', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->get(route('dashboard', ['date' => 'invalid-date-string']));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Dashboard')->has('kpiCards'));
});
