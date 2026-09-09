<?php

use App\Models\Department;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('manager can view daily burn hero chart and section comparison with interactive widgets', function () {
    $dept = Department::create([
        'code' => 'DEPT_DAILY_BROWSER',
        'name' => 'Powertrain Line Dept',
        'cost_center_code' => 'CC-PWR-DAILY',
        'default_hourly_rate' => 38000,
        'is_active' => true,
    ]);

    $section1 = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_ENG_01',
        'name' => 'Engine Assembly Line',
        'is_active' => true,
    ]);

    $section2 = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_TRN_02',
        'name' => 'Transmission Assembly Line',
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
        'section_id' => $section1->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 200.0,
        'planned_amount' => 7600000,
    ]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section2->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 150.0,
        'planned_amount' => 5700000,
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section1->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 200.0,
        'cumulative_actual_hours' => 180.0,
        'burn_index_pct' => 90.0,
        'burn_velocity' => 45.0,
        'burn_zone' => 'ZONE_2_GOOD',
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section2->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 150.0,
        'cumulative_actual_hours' => 180.0,
        'burn_index_pct' => 120.0,
        'burn_velocity' => 45.0,
        'burn_zone' => 'ZONE_4_POOR',
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Charts',
        'email' => 'manager.charts@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.charts@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertPresent('[data-test="daily-burn-line-chart-card"]')
        ->assertPresent('[data-test="section-burn-comparison-card"]')
        ->assertPresent('[data-test="daily-burn-stats-strip"]')
        ->assertPresent('[data-test="section-burn-zone-summary"]')
        ->assertSee('SEC_TRN_02')
        ->assertSee('SEC_ENG_01')
        ->assertSee('120%')
        ->assertSee('90%');
});

test('manager can click a section card and drill down to section burndown page', function () {
    $dept = Department::create([
        'code' => 'DEPT_DRILL_BROWSER',
        'name' => 'Chassis Assembly Dept',
        'cost_center_code' => 'CC-CHS-001',
        'default_hourly_rate' => 38000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_CHS_01',
        'name' => 'Chassis Main Line',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $year = $now->year;
    $month = $now->month;

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 180.0,
        'cumulative_actual_hours' => 120.0,
        'burn_index_pct' => 66.7,
        'burn_velocity' => 30.0,
        'burn_zone' => 'ZONE_1_EXCELLENT',
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Drilldown',
        'email' => 'manager.drill@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.drill@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertPresent('[data-test="section-card-SEC_CHS_01"]')
        ->click('[data-test="section-card-SEC_CHS_01"]')
        ->assertPathIs('/dashboard/burn-index')
        ->assertSee('SEC_CHS_01');
});
