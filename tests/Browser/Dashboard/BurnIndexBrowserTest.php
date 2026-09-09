<?php

use App\Models\Department;
use App\Models\MlModel;
use App\Models\MlPrediction;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OvertimeBudget;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('manager can navigate to burn index dashboard and inspect section cards and indicators', function () {
    $dept = Department::create([
        'code' => 'DEPT_BRW_BURN',
        'name' => 'Stamping Production Dept',
        'cost_center_code' => 'CC-STP-BRW',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $secConfigured = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_STP_PRESS',
        'name' => 'Press Line 1',
        'is_active' => true,
    ]);

    $secUnconfigured = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_STP_DIE',
        'name' => 'Die Maintenance',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');
    $month = (int) $now->format('n');

    // Budget configured for Press Line 1
    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $secConfigured->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 200.0,
    ]);

    // Snapshot pre-computed for Press Line 1
    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $secConfigured->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 200.0,
        'cumulative_actual_hours' => 150.0,
        'cumulative_opex_hours' => 100.0,
        'cumulative_capex_hours' => 50.0,
        'burn_index_pct' => 75.0,
        'burn_velocity' => 37.5,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Stamping',
        'email' => 'manager.stamping@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.stamping@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-burn-index"]')
        ->assertPathIs('/dashboard/burn-index')
        ->assertSee('Press Line 1')
        ->assertSee('SEC_STP_PRESS')
        ->assertSee('75.0%')
        ->assertSee('150.0')
        ->assertSee('200.0')
        ->assertSee('37.5')
        ->assertSee('hrs/wk')
        ->assertSee('161.3')
        ->assertSee('Die Maintenance')
        ->assertSee('SEC_STP_DIE');
});

test('burn index card displays burn velocity trajectory indicator and ml forecast comparison', function () {
    $dept = Department::create([
        'code' => 'DEPT_BRW_PRED',
        'name' => 'Machining Department',
        'cost_center_code' => 'CC-MCH-PRED',
        'default_hourly_rate' => 36000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_MCH_CNC',
        'name' => 'CNC Milling Line',
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
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 200.0,
        'cumulative_actual_hours' => 140.0,
        'cumulative_opex_hours' => 100.0,
        'cumulative_capex_hours' => 40.0,
        'burn_index_pct' => 70.0,
        'burn_velocity' => 35.0,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $mlModel = MlModel::create([
        'model_key' => 'demand-ridge-brw',
        'model_type' => 'DEMAND_FORECAST',
        'version' => '1.0.0',
        'algorithm_name' => 'RidgeRegression',
        'is_active' => true,
        'trained_at' => now(),
    ]);

    MlPrediction::create([
        'ml_model_id' => $mlModel->id,
        'target_type' => 'SECTION',
        'target_id' => $section->id,
        'prediction_horizon' => 'MONTH_END',
        'predicted_value' => 175.00,
        'confidence_interval_lower' => 163.00,
        'confidence_interval_upper' => 187.00,
        'risk_score' => 0.1200,
        'risk_level' => 'LOW',
        'fallback_used' => false,
        'created_at' => now(),
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Machining',
        'email' => 'manager.machining@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.machining@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-burn-index"]')
        ->assertPathIs('/dashboard/burn-index')
        ->assertSee('CNC Milling Line')
        ->assertSee('SEC_MCH_CNC')
        ->assertSee('35.0')
        ->assertSee('hrs/wk')
        ->assertSee('150.5')
        ->assertSee('175.0');
});

test('burn index card displays critical will overrun trajectory for high burn velocity', function () {
    $dept = Department::create([
        'code' => 'DEPT_BRW_OVER',
        'name' => 'Assembly Department',
        'cost_center_code' => 'CC-ASSY-OVER',
        'default_hourly_rate' => 37000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_ASSY_OVER',
        'name' => 'Final Assembly Line',
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
        'planned_hours' => 100.0,
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 130.0,
        'cumulative_opex_hours' => 110.0,
        'cumulative_capex_hours' => 20.0,
        'burn_index_pct' => 130.0,
        'burn_velocity' => 65.0,
        'burn_zone' => 'ZONE_4_POOR',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Assembly',
        'email' => 'manager.assembly@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.assembly@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-burn-index"]')
        ->assertPathIs('/dashboard/burn-index')
        ->assertSee('Final Assembly Line')
        ->assertSee('SEC_ASSY_OVER')
        ->assertSee('65.0')
        ->assertSee('279.5')
        ->assertSee('Will Overrun');
});

test('admin can switch department on burn index dashboard', function () {
    $dept1 = Department::create([
        'code' => 'DEPT_BRW_ADM1',
        'name' => 'Welding Department',
        'cost_center_code' => 'CC-WLD-BRW',
        'default_hourly_rate' => 38000.00,
        'is_active' => true,
    ]);

    $sec1 = Section::create([
        'department_id' => $dept1->id,
        'code' => 'SEC_WLD_ROBOT',
        'name' => 'Robot Welding Cell',
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create([
        'name' => 'Plant Admin',
        'email' => 'plant.admin@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'plant.admin@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-burn-index"]')
        ->assertPathIs('/dashboard/burn-index')
        ->assertSee('DEPT_BRW_ADM1');
});
