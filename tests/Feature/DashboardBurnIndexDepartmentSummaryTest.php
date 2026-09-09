<?php

use App\Models\Department;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OvertimeBudget;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->deptA = Department::create([
        'code' => 'DEPT_STP',
        'name' => 'Stamping Plant Dept',
        'cost_center_code' => 'CC-STP-01',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $this->deptB = Department::create([
        'code' => 'DEPT_WLD',
        'name' => 'Welding Plant Dept',
        'cost_center_code' => 'CC-WLD-01',
        'default_hourly_rate' => 37000.00,
        'is_active' => true,
    ]);

    // Sections for Dept A
    $this->secA1 = Section::create([
        'department_id' => $this->deptA->id,
        'code' => 'SEC_STP_PRESS',
        'name' => 'Press Stamping 1',
        'is_active' => true,
    ]);

    $this->secA2 = Section::create([
        'department_id' => $this->deptA->id,
        'code' => 'SEC_STP_DIE',
        'name' => 'Die Maintenance',
        'is_active' => true,
    ]);

    // Section for Dept B
    $this->secB1 = Section::create([
        'department_id' => $this->deptB->id,
        'code' => 'SEC_WLD_ROBOT',
        'name' => 'Robotic Welder Line',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $this->year = (int) $now->format('Y');
    $this->month = (int) $now->format('n');

    // Budgets
    OvertimeBudget::create([
        'department_id' => $this->deptA->id,
        'section_id' => $this->secA1->id,
        'fiscal_year' => $this->year,
        'fiscal_month' => $this->month,
        'planned_hours' => 100.0,
    ]);

    OvertimeBudget::create([
        'department_id' => $this->deptA->id,
        'section_id' => $this->secA2->id,
        'fiscal_year' => $this->year,
        'fiscal_month' => $this->month,
        'planned_hours' => 200.0,
    ]);

    OvertimeBudget::create([
        'department_id' => $this->deptB->id,
        'section_id' => $this->secB1->id,
        'fiscal_year' => $this->year,
        'fiscal_month' => $this->month,
        'planned_hours' => 150.0,
    ]);

    // Snapshots: secA1 is Deficit (120%), secA2 is Normal (75%), secB1 is Warning (105%)
    MonthlyBurnSnapshot::create([
        'department_id' => $this->deptA->id,
        'section_id' => $this->secA1->id,
        'fiscal_year' => $this->year,
        'fiscal_month' => $this->month,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 120.0,
        'cumulative_opex_hours' => 80.0,
        'cumulative_capex_hours' => 40.0,
        'burn_index_pct' => 120.0,
        'burn_velocity' => 30.0,
        'burn_zone' => 'ZONE_4_POOR',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $this->deptA->id,
        'section_id' => $this->secA2->id,
        'fiscal_year' => $this->year,
        'fiscal_month' => $this->month,
        'planned_budget_hours' => 200.0,
        'cumulative_actual_hours' => 150.0,
        'cumulative_opex_hours' => 150.0,
        'cumulative_capex_hours' => 0.0,
        'burn_index_pct' => 75.0,
        'burn_velocity' => 37.5,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $this->deptB->id,
        'section_id' => $this->secB1->id,
        'fiscal_year' => $this->year,
        'fiscal_month' => $this->month,
        'planned_budget_hours' => 150.0,
        'cumulative_actual_hours' => 157.5,
        'cumulative_opex_hours' => 157.5,
        'cumulative_capex_hours' => 0.0,
        'burn_index_pct' => 105.0,
        'burn_velocity' => 39.38,
        'burn_zone' => 'ZONE_3_WARNING',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);
});

test('manager can view consolidated department tab with ranked sections table and department macro metrics', function () {
    $managerA = User::factory()->manager($this->deptA->id)->create();

    $response = $this->actingAs($managerA)->get(route('dashboard.burn-index', [
        'year' => $this->year,
        'month' => $this->month,
        'tab' => 'department',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/BurnIndex')
        ->where('current_tab', 'department')
        ->where('selected_department.id', $this->deptA->id)
        ->has('snapshots', 2)
        ->where('summary.total_planned_hours', 300)
        ->where('summary.total_actual_hours', 270)
        ->where('summary.total_remaining_hours', 30)
        ->where('summary.department_burn_index_pct', 90)
        ->where('summary.danger_sections_count', 1)
        ->where('summary.warning_sections_count', 0)
        ->where('summary.configured_sections_count', 2)
        ->where('summary.total_sections_count', 2)
    );
});

test('admin can view plant-wide cross-department view with departments summary and all sections', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('dashboard.burn-index', [
        'year' => $this->year,
        'month' => $this->month,
        'department_id' => 'all',
        'tab' => 'department',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/BurnIndex')
        ->where('current_tab', 'department')
        ->where('selected_department', null)
        ->has('snapshots', 3) // secA1, secA2, secB1
        ->has('departments_summary', 2)
        ->where('departments_summary.0.code', 'DEPT_STP')
        ->where('departments_summary.0.total_planned_hours', 300)
        ->where('departments_summary.0.total_actual_hours', 270)
        ->where('departments_summary.0.danger_sections_count', 1)
        ->where('departments_summary.1.code', 'DEPT_WLD')
        ->where('departments_summary.1.total_planned_hours', 150)
        ->where('departments_summary.1.total_actual_hours', 157.5)
        ->where('departments_summary.1.warning_sections_count', 1)
    );
});

test('manager can download weekly standup pdf report', function () {
    $managerA = User::factory()->manager($this->deptA->id)->create();

    $response = $this->actingAs($managerA)->get(route('dashboard.burn-index.export-pdf', [
        'year' => $this->year,
        'month' => $this->month,
        'type' => 'standup',
    ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->headers->get('Content-Disposition'))->toContain('Laporan-Standup-Burn-Index');
});

test('manager can download full monthly analytical pdf report', function () {
    $managerA = User::factory()->manager($this->deptA->id)->create();

    $response = $this->actingAs($managerA)->get(route('dashboard.burn-index.export-pdf', [
        'year' => $this->year,
        'month' => $this->month,
        'type' => 'monthly',
    ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->headers->get('Content-Disposition'))->toContain('Laporan-Bulanan-Burn-Index');
});

test('admin can download plant-wide standup pdf report', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('dashboard.burn-index.export-pdf', [
        'year' => $this->year,
        'month' => $this->month,
        'department_id' => 'all',
        'type' => 'standup',
    ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('Laporan-Standup-Burn-Index-ALL');
});

test('unauthorized users cannot download burn index pdf report', function () {
    $unauthorized = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($unauthorized)->get(route('dashboard.burn-index.export-pdf', [
        'year' => $this->year,
        'month' => $this->month,
    ]));

    $response->assertForbidden();
});
