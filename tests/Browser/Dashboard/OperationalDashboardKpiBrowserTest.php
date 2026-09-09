<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\MonthlyBurnSnapshot;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('manager can view 4 executive kpi cards with sparklines and burn metrics', function () {
    $dept = Department::create([
        'code' => 'DEPT_OPS_KPI',
        'name' => 'Powertrain Dept',
        'cost_center_code' => 'CC-PWR-001',
        'default_hourly_rate' => 38000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_ENG_ASSY',
        'name' => 'Engine Assembly Line',
        'is_active' => true,
    ]);

    Employee::create([
        'npk' => 'ISZ-8801',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Budi Santoso',
        'job_position' => 'Senior Technician',
        'hourly_rate' => 38000,
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
        'planned_budget_hours' => 250.0,
        'cumulative_actual_hours' => 220.0,
        'cumulative_opex_hours' => 170.0,
        'cumulative_capex_hours' => 50.0,
        'burn_index_pct' => 88.0,
        'burn_velocity' => 55.0,
        'burn_zone' => 'ZONE_2_GOOD',
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Ops',
        'email' => 'manager.ops@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.ops@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertSee('Production Volume')
        ->assertSee('Working Days (HKN)')
        ->assertSee('Man Power')
        ->assertSee('Burn Index (BBI)')
        ->assertSee('88%');
});

test('admin can interact with dashboard filters and inspect department scope', function () {
    $dept = Department::create([
        'code' => 'DEPT_STP_KPI',
        'name' => 'Stamping Production Dept',
        'cost_center_code' => 'CC-STP-002',
        'default_hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create([
        'name' => 'Admin KPI',
        'email' => 'admin.kpi@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.kpi@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertSee('Executive Operational Dashboard')
        ->assertSee('Production Volume')
        ->assertPresent('[data-test="department-filter-select"]')
        ->assertPresent('[data-test="date-filter-input"]');
});
