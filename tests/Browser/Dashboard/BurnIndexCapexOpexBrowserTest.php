<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('manager can switch to capex-opex tab, inspect kpi cards, charts, and project variance table', function () {
    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');
    $month = (int) $now->format('n');
    $dateStr = Carbon::create($year, $month, 10, 0, 0, 0, 'Asia/Jakarta')->toDateString();

    if (! OperationalCalendar::whereDate('calendar_date', $dateStr)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $dateStr,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::create([
        'code' => 'DEPT_BRW_CPX',
        'name' => 'Stamping & Assembly Dept',
        'cost_center_code' => 'CC-CPX-001',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $sec = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_CPX_TRM',
        'name' => 'Trim & Assembly Line',
        'is_active' => true,
    ]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 200.0,
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 200.0,
        'cumulative_actual_hours' => 100.0,
        'cumulative_opex_hours' => 60.0,
        'cumulative_capex_hours' => 40.0,
        'burn_index_pct' => 50.0,
        'burn_velocity' => 25.0,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'npk' => 'BRW001',
        'hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $project = CapexProject::create([
        'project_code' => 'CPX-2026-ROBOT-01',
        'asset_code' => 'FA-2026-RBT',
        'name' => 'Robotic Stamping Automation',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 100.0,
        'allocated_labor_budget_idr' => 4000000.0,
        'physical_progress_pct' => 65.0,
        'status' => 'ACTIVE',
        'start_date' => Carbon::create($year, $month, 1)->toDateString(),
        'target_end_date' => Carbon::create($year, $month, 28)->toDateString(),
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Capex Opex',
        'email' => 'manager.capex@factory.com',
        'password' => 'password',
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-BRW-01',
        'submission_date' => $dateStr,
        'operational_date' => $dateStr,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $project->id,
        'hours_production' => 60.0,
        'hours_tpm' => 0.0,
        'hours_project' => 40.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.0,
        'total_cost_snapshot' => 3500000.0,
        'status' => 'APPROVED',
    ]);

    visit('/login')
        ->fill('email', 'manager.capex@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-burn-index"]')
        ->assertPathIs('/dashboard/burn-index')
        ->assertSee('Trim & Assembly Line')
        // Switch to CapEx vs OpEx Tab
        ->click('[data-test="tab-capex-opex"]')
        ->assertVisible('[data-test="capex-opex-tab"]')
        ->assertVisible('[data-test="capitalization-kpi-bar"]')
        ->assertSee('40.0')
        ->assertSee('60.0')
        ->assertSee('100.0')
        ->assertSee('CPX-2026-ROBOT-01')
        ->assertSee('Robotic Stamping Automation')
        ->assertSee('-60.0')
        ->assertVisible('[data-test="capex-opex-donut-chart"]')
        ->assertVisible('[data-test="capex-opex-section-bar-chart"]')
        ->assertVisible('[data-test="capex-project-table-container"]')
        // Check date range toolbar and switch to Custom
        ->click('[data-test="btn-range-custom"]')
        ->assertVisible('[data-test="custom-date-inputs"]');
});

test('capex-opex tab displays friendly empty state when no capex hours exist', function () {
    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');
    $month = (int) $now->format('n');

    $dept = Department::create([
        'code' => 'DEPT_EMPTY_CPX',
        'name' => 'Logistics Department',
        'cost_center_code' => 'CC-LOG-001',
        'default_hourly_rate' => 30000.00,
        'is_active' => true,
    ]);

    $sec = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_LOG_WH',
        'name' => 'Warehouse Section',
        'is_active' => true,
    ]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 100.0,
    ]);

    MonthlyBurnSnapshot::create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_budget_hours' => 100.0,
        'cumulative_actual_hours' => 0.0,
        'cumulative_opex_hours' => 0.0,
        'cumulative_capex_hours' => 0.0,
        'burn_index_pct' => 0.0,
        'burn_velocity' => 0.0,
        'burn_zone' => 'ZONE_1_EXCELLENT',
        'last_recalculated_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Empty Capex',
        'email' => 'manager.empty@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.empty@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-burn-index"]')
        ->assertPathIs('/dashboard/burn-index')
        ->assertSee('Warehouse Section')
        ->click('[data-test="tab-capex-opex"]')
        ->assertVisible('[data-test="capex-opex-tab"]')
        ->assertVisible('[data-test="capex-empty-state-banner"]')
        ->assertSee('0.0');
});
