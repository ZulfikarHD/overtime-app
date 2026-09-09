<?php

use App\Models\Department;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OvertimeBudget;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('manager can switch to department consolidated tab, inspect ranked table, and open detail drawer', function () {
    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');
    $month = (int) $now->format('n');

    $dept = Department::create([
        'code' => 'DEPT_BRW_DPT1',
        'name' => 'Stamping Department Alpha',
        'cost_center_code' => 'CC-STP-A1',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $sec1 = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_A1_PRESS',
        'name' => 'Heavy Press Line 1',
        'is_active' => true,
    ]);

    $sec2 = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_A1_DIE',
        'name' => 'Die Assembly Unit',
        'is_active' => true,
    ]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $sec1->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 150.0,
    ]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $sec2->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 200.0,
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $sec1->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 150.0,
        'cumulative_actual_hours' => 180.0,
        'cumulative_opex_hours' => 120.0,
        'cumulative_capex_hours' => 60.0,
        'burn_index_pct' => 120.0,
        'burn_velocity' => 45.0,
        'burn_zone' => 'ZONE_4_POOR',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $sec2->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 200.0,
        'cumulative_actual_hours' => 140.0,
        'cumulative_opex_hours' => 140.0,
        'cumulative_capex_hours' => 0.0,
        'burn_index_pct' => 70.0,
        'burn_velocity' => 35.0,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Alpha',
        'email' => 'manager.alpha@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.alpha@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-burn-index"]')
        ->assertPathIs('/dashboard/burn-index')
        // Switch to Department Consolidated Tab (E05-05)
        ->click('[data-test="tab-department"]')
        ->assertVisible('[data-test="tab-department-content"]')
        ->assertVisible('[data-test="table-ranked-sections"]')
        ->assertSee('Heavy Press Line 1')
        ->assertSee('Die Assembly Unit')
        // Open Section Burndown sheet via detail click
        ->click('[data-test="btn-detail-SEC_A1_PRESS"]')
        ->assertVisible('[data-test="section-burndown-sheet"]')
        // Close Sheet
        ->click('[data-test="btn-close-sheet"]')
        // Test PDF export dropdown presence
        ->assertVisible('[data-test="btn-pdf-export"]')
        ->click('[data-test="btn-pdf-export"]')
        ->assertVisible('[data-test="option-pdf-standup"]')
        ->assertVisible('[data-test="option-pdf-monthly"]');
});

test('admin can see cross department comparison cards and drill down to section details', function () {
    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');
    $month = (int) $now->format('n');

    $deptA = Department::create([
        'code' => 'DEPT_ADM_A',
        'name' => 'Engine Machining Dept',
        'cost_center_code' => 'CC-ENG-01',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $deptB = Department::create([
        'code' => 'DEPT_ADM_B',
        'name' => 'Chassis Assembly Dept',
        'cost_center_code' => 'CC-CHS-01',
        'default_hourly_rate' => 36000.00,
        'is_active' => true,
    ]);

    $secA = Section::create([
        'department_id' => $deptA->id,
        'code' => 'SEC_ENG_BLOCK',
        'name' => 'Cylinder Block Line',
        'is_active' => true,
    ]);

    $secB = Section::create([
        'department_id' => $deptB->id,
        'code' => 'SEC_CHS_FRAME',
        'name' => 'Frame Assembly Line',
        'is_active' => true,
    ]);

    OvertimeBudget::create([
        'department_id' => $deptA->id,
        'section_id' => $secA->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 100.0,
    ]);

    OvertimeBudget::create([
        'department_id' => $deptB->id,
        'section_id' => $secB->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 120.0,
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $deptA->id,
        'section_id' => $secA->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 90.0,
        'cumulative_opex_hours' => 90.0,
        'cumulative_capex_hours' => 0.0,
        'burn_index_pct' => 90.0,
        'burn_velocity' => 22.5,
        'burn_zone' => 'ZONE_2_GOOD',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $deptB->id,
        'section_id' => $secB->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 120.0,
        'cumulative_actual_hours' => 140.0,
        'cumulative_opex_hours' => 140.0,
        'cumulative_capex_hours' => 0.0,
        'burn_index_pct' => 116.67,
        'burn_velocity' => 35.0,
        'burn_zone' => 'ZONE_4_POOR',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $admin = User::factory()->admin()->create([
        'name' => 'Admin Controller',
        'email' => 'admin.controller@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.controller@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-burn-index"]')
        ->assertPathIs('/dashboard/burn-index')
        // Switch to Department tab
        ->click('[data-test="tab-department"]')
        ->assertVisible('[data-test="tab-department-content"]')
        ->assertVisible('[data-test="cross-department-comparison-container"]')
        ->assertVisible('[data-test="dept-card-DEPT_ADM_A"]')
        ->assertVisible('[data-test="dept-card-DEPT_ADM_B"]')
        ->assertSee('Engine Machining Dept')
        ->assertSee('Chassis Assembly Dept');
});
