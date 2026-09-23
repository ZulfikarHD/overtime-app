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
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertPresent('[data-test="dashboard-filter-bar"]')
        ->assertPresent('[data-slot="kpi-card-production"]')
        ->assertSee('Volume Produksi')
        ->assertSee('Target Bulanan Plant')
        ->assertSee('unit')
        ->assertSee('Hari Kerja (HKN)')
        ->assertSee('Tenaga Kerja (Man Power)')
        ->assertSee('Index Burn Up (Day to Date)')
        ->assertPresent('[data-test="burn-index-title"]')
        ->assertPresent('[data-test="burn-index-pct"]')
        ->assertPresent('[data-test="burn-index-meta"]')
        ->assertPresent('[data-test="dashboard-live-clock"]')
        ->assertPresent('[data-test="dashboard-active-shift"]')
        ->assertSee('88%')
        ->assertNoJavaScriptErrors();
});

test('admin can interact with dashboard filters and inspect department scope', function () {
    $dept = Department::create([
        'code' => 'DEPT_STP_KPI',
        'name' => 'Stamping Production Dept',
        'cost_center_code' => 'CC-STP-002',
        'default_hourly_rate' => 35000,
        'is_active' => true,
    ]);

    Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_STP_01',
        'name' => 'Stamping Line 1',
        'is_active' => true,
    ]);

    Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_STP_02',
        'name' => 'Stamping Line 2',
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
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertPresent('[data-test="dashboard-filter-bar"]')
        ->assertSee('Dashboard Operasional Eksekutif')
        ->assertSee('Volume Produksi')
        ->assertSee('Target Bulanan Plant')
        ->assertPresent('[data-test="department-filter-select"]')
        ->assertPresent('[data-test="date-filter-input"]')
        ->assertPresent('[data-test="section-filter-select"]')
        ->assertSee('Semua Seksi (Departemen)')
        ->assertNoJavaScriptErrors();
});

test('manager can filter operational dashboard by section then reset to department scope', function () {
    $dept = Department::create([
        'code' => 'DEPT_MGR_SEC',
        'name' => 'Manager Section Dept',
        'cost_center_code' => 'CC-MGR-SEC',
        'default_hourly_rate' => 38000,
        'is_active' => true,
    ]);

    $section1 = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_MGR_01',
        'name' => 'Manager Line One',
        'is_active' => true,
    ]);

    Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_MGR_02',
        'name' => 'Manager Line Two',
        'is_active' => true,
    ]);

    User::factory()->manager($dept->id)->create([
        'name' => 'Manager Section Filter',
        'email' => 'manager.section.filter@factory.com',
        'password' => 'password',
    ]);

    $page = visit('/login')
        ->fill('email', 'manager.section.filter@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertPresent('[data-test="section-filter-select"]')
        ->assertSee('Semua Seksi (Departemen)')
        ->assertSee('Manager Line One');

    $page->select('[data-test="section-filter-select"]', (string) $section1->id)
        ->assertQueryStringHas('section_id', (string) $section1->id)
        ->assertNoJavaScriptErrors();

    $page->select('[data-test="section-filter-select"]', 'all')
        ->assertSee('Semua Seksi (Departemen)')
        ->assertNoJavaScriptErrors();
});
