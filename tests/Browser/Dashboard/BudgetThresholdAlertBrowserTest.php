<?php

use App\Models\Department;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\Section;
use App\Models\User;
use App\Notifications\BudgetThresholdAlert;
use Carbon\Carbon;

beforeEach(function () {
    $todayWib = Carbon::now('Asia/Jakarta')->format('Y-m-d');
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $todayWib],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );
});

test('manager sees budget alert badge in topbar, inspects popover, and deep-links directly to section drawer (User Journey 4)', function () {
    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');
    $month = (int) $now->format('n');

    $dept = Department::create([
        'code' => 'DEPT_ALERT_BRW',
        'name' => 'Assembly Plant',
        'cost_center_code' => 'CC-ALT-01',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_BODY_ALT',
        'name' => 'Body Assembly',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'email' => 'manager.alert@factory.com',
        'password' => 'password',
    ]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 100.0,
    ]);

    $snapshot = MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 118.4,
        'cumulative_opex_hours' => 118.4,
        'cumulative_capex_hours' => 0.0,
        'burn_index_pct' => 118.4,
        'burn_velocity' => 30.0,
        'burn_zone' => 'ZONE_4_POOR',
        'warned_at' => Carbon::now('Asia/Jakarta')->subHours(1),
        'danger_at' => Carbon::now('Asia/Jakarta'),
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $manager->notify(new BudgetThresholdAlert($snapshot, 'danger', 115.0));

    visit('/login')
        ->fill('email', 'manager.alert@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        // Notification bell badge has count 1
        ->assertSee('1')
        // Open notification popover
        ->click('[data-test="notification-bell-btn"]')
        ->waitForText('Body Assembly')
        ->assertPresent('[data-test="badge-budget-danger"]')
        ->assertPresent('[data-test="btn-view-burn-from-notif"]')
        ->assertSee('118.4%')
        // Click view burn index action inside notification
        ->click('[data-test="btn-view-burn-from-notif"]')
        ->assertPathIs('/dashboard/burn-index')
        // Section Burndown drawer opens automatically for Body Assembly
        ->waitForText('Body Assembly')
        ->assertSee('118.4%');
});

test('section burn cards display pulsing border animations when crossing warning and danger thresholds', function () {
    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');
    $month = (int) $now->format('n');

    $dept = Department::create([
        'code' => 'DEPT_PULSE_BRW',
        'name' => 'Stamping Plant',
        'cost_center_code' => 'CC-PLS-01',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $secSafe = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_SAFE',
        'name' => 'Safe Section',
        'is_active' => true,
    ]);

    $secWarning = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_WARN',
        'name' => 'Warning Section',
        'is_active' => true,
    ]);

    $secDanger = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_DANGR',
        'name' => 'Danger Section',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'email' => 'manager.pulse@factory.com',
        'password' => 'password',
    ]);

    // Set budgets
    foreach ([$secSafe, $secWarning, $secDanger] as $s) {
        OvertimeBudget::create([
            'department_id' => $dept->id,
            'section_id' => $s->id,
            'fiscal_year' => $year,
            'fiscal_month' => $month,
            'planned_hours' => 100.0,
        ]);
    }

    // Safe snapshot (50%)
    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $secSafe->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 50.0,
        'cumulative_opex_hours' => 50.0,
        'cumulative_capex_hours' => 0.0,
        'burn_index_pct' => 50.0,
        'burn_velocity' => 15.0,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    // Warning snapshot (105%)
    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $secWarning->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 105.0,
        'cumulative_opex_hours' => 105.0,
        'cumulative_capex_hours' => 0.0,
        'burn_index_pct' => 105.0,
        'burn_velocity' => 25.0,
        'burn_zone' => 'ZONE_3_WARNING',
        'warned_at' => Carbon::now('Asia/Jakarta'),
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    // Danger snapshot (120%)
    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $secDanger->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 120.0,
        'cumulative_opex_hours' => 120.0,
        'cumulative_capex_hours' => 0.0,
        'burn_index_pct' => 120.0,
        'burn_velocity' => 30.0,
        'burn_zone' => 'ZONE_4_POOR',
        'warned_at' => Carbon::now('Asia/Jakarta'),
        'danger_at' => Carbon::now('Asia/Jakarta'),
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    visit('/login')
        ->fill('email', 'manager.pulse@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard');

    visit('/dashboard/burn-index?tab=sections')
        ->waitForText('Warning Section')
        ->assertSee('Safe Section')
        ->assertSee('Warning Section')
        ->assertSee('Danger Section')
        // Check for pulsing card indicators
        ->assertPresent('[data-test="burn-card-SEC_WARN"].burn-card--warning')
        ->assertPresent('[data-test="burn-card-SEC_DANGR"].burn-card--danger');
});
