<?php

use App\Models\Department;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OvertimeBudget;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('manager can click section card to open 5-week burndown drawer and inspect charts and tables', function () {
    $dept = Department::create([
        'code' => 'DEPT_BRW_E0502',
        'name' => 'Powertrain Manufacturing',
        'cost_center_code' => 'CC-PWR-001',
        'default_hourly_rate' => 38000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_PWR_ENG',
        'name' => 'Engine Assembly Line',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');
    $month = (int) $now->format('n');

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 200.0,
        'week1_planned_hours' => 40.0,
        'week2_planned_hours' => 40.0,
        'week3_planned_hours' => 40.0,
        'week4_planned_hours' => 40.0,
        'week5_planned_hours' => 40.0,
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 200.0,
        'cumulative_actual_hours' => 80.0,
        'cumulative_opex_hours' => 60.0,
        'cumulative_capex_hours' => 20.0,
        'burn_index_pct' => 40.0,
        'burn_velocity' => 20.0,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Powertrain',
        'email' => 'manager.powertrain@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.powertrain@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-burn-index"]')
        ->assertPathIs('/dashboard/burn-index')
        ->assertSee('Engine Assembly Line')
        ->assertSee('SEC_PWR_ENG')
        // Click the card action button to open burndown drawer
        ->click('[data-test="btn-open-burndown"]')
        ->assertVisible('[data-test="section-burndown-sheet"]')
        ->assertSee('Engine Assembly Line')
        ->assertSee('SEC_PWR_ENG')
        ->assertSee('Powertrain Manufacturing')
        // Check 5-Week Burndown line chart container is rendered
        ->assertVisible('[data-test="burndown-line-chart-container"]')
        // Check Weekly Breakdown Table is rendered
        ->assertVisible('[data-test="weekly-breakdown-table-container"]')
        ->assertVisible('[data-test="weekly-row-1"]')
        // Check 4-Quadrant Budget Control Matrix Scatter container is rendered
        ->assertVisible('[data-test="budget-matrix-scatter-container"]')
        ->assertVisible('[data-test="scatter-current-zone-badge"]')
        // Close drawer with Close button
        ->click('[data-test="btn-close-sheet"]');
});

test('manager can deep link to section burndown drawer via query parameter', function () {
    $dept = Department::create([
        'code' => 'DEPT_BRW_DEEP',
        'name' => 'Chassis Manufacturing',
        'cost_center_code' => 'CC-CHS-001',
        'default_hourly_rate' => 38000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_CHS_WELD',
        'name' => 'Chassis Welding Line',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');
    $month = (int) $now->format('n');

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 150.0,
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 150.0,
        'cumulative_actual_hours' => 60.0,
        'cumulative_opex_hours' => 50.0,
        'cumulative_capex_hours' => 10.0,
        'burn_index_pct' => 40.0,
        'burn_velocity' => 20.0,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Chassis',
        'email' => 'manager.chassis@factory.com',
        'password' => 'password',
    ]);

    // Log in first
    visit('/login')
        ->fill('email', 'manager.chassis@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard');

    // Deep link directly to ?tab=sections&section={id}
    visit('/dashboard/burn-index?tab=sections&section='.$section->id)
        ->assertVisible('[data-test="section-burndown-sheet"]')
        ->assertSee('Chassis Welding Line')
        ->assertSee('SEC_CHS_WELD')
        ->assertVisible('[data-test="burndown-line-chart-container"]')
        ->assertVisible('[data-test="weekly-breakdown-table-container"]')
        ->assertVisible('[data-test="budget-matrix-scatter-container"]');
});

test('burndown drawer displays unconfigured budget alert when budget is missing', function () {
    $dept = Department::create([
        'code' => 'DEPT_BRW_NOCONFIG',
        'name' => 'Quality Control Dept',
        'cost_center_code' => 'CC-QC-001',
        'default_hourly_rate' => 38000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_QC_INSP',
        'name' => 'Final Inspection Line',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');
    $month = (int) $now->format('n');

    // No OvertimeBudget created -> unconfigured

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager QC',
        'email' => 'manager.qc@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.qc@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-burn-index"]')
        ->assertPathIs('/dashboard/burn-index')
        ->assertSee('Final Inspection Line')
        ->assertSee('SEC_QC_INSP')
        // Deep link or click card to open sheet
        ->click('[data-test="burn-card-SEC_QC_INSP"]')
        ->assertVisible('[data-test="section-burndown-sheet"]')
        ->assertVisible('[data-test="sheet-unconfigured-budget-banner"]');
});
