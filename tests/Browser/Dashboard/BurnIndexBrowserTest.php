<?php

use App\Models\Department;
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
        ->assertSee('Die Maintenance')
        ->assertSee('SEC_STP_DIE');
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
