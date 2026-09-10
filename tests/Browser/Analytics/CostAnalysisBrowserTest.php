<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('admin can view cost analysis tab with 4 kpis, 3 charts, and breakdown table', function () {
    $now = Carbon::now('Asia/Jakarta');

    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $now->toDateString()],
        [
            'day_type' => 'HKN',
            'is_holiday' => false,
            'description' => 'Normal Working Day',
        ]
    );

    $dept = Department::create([
        'code' => 'DEPT_COST_ASY',
        'name' => 'Assembly Cost Plant',
        'cost_center_code' => 'CC-COST-ASY',
        'default_hourly_rate' => 50000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_COST_TRIM',
        'name' => 'Trim Line Cost',
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create([
        'name' => 'Budi Cost Admin',
        'email' => 'budi.cost@factory.com',
        'password' => 'password',
        'npk' => 'EMP-C9901',
    ]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Bambang Cost Worker',
    ]);

    $fiscalYear = $now->month >= 4 ? $now->year : $now->year - 1;
    $fiscalMonth = $now->month >= 4 ? $now->month - 3 : $now->month + 9;

    OvertimeBudget::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $fiscalYear,
        'fiscal_month' => $fiscalMonth,
        'planned_cost_idr' => 15000000.00,
        'planned_hours' => 200.0,
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-BRW-COST-001',
        'submission_date' => $now->toDateString(),
        'operational_date' => $now->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 2.0,
        'hours_tpm' => 0.0,
        'hours_project' => 2.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.00,
        'total_cost_snapshot' => 200000.00,
        'status' => 'APPROVED',
        'lock_version' => 1,
    ]);

    visit('/login')
        ->fill('email', 'budi.cost@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Analytics & Decision')
        ->assertPathIs('/analytics')
        ->click('[data-test="tab-cost"]')
        ->assertSee('Total Overtime Cost')
        ->assertSee('Assembly Cost Plant')
        ->assertPresent('[data-test="card-total-cost"]')
        ->assertPresent('[data-test="card-remaining-budget"]')
        ->assertPresent('[data-test="card-avg-cost-per-employee"]')
        ->assertPresent('[data-test="card-capex-ratio"]')
        ->assertPresent('[data-test="cost-by-department-chart"]')
        ->assertPresent('[data-test="cost-trend-stacked-chart"]')
        ->assertPresent('[data-test="budget-vs-actual-bar-chart"]')
        ->assertPresent('[data-test="cost-breakdown-table"]');
});

test('manager scoped to department can interact with cost analysis table search', function () {
    $now = Carbon::now('Asia/Jakarta');

    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $now->toDateString()],
        [
            'day_type' => 'HKN',
            'is_holiday' => false,
            'description' => 'Normal Working Day',
        ]
    );

    $dept = Department::create([
        'code' => 'DEPT_COST_MGR',
        'name' => 'Machining Cost Plant',
        'cost_center_code' => 'CC-COST-MGR',
        'default_hourly_rate' => 52000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_COST_CNC',
        'name' => 'CNC Milling Line',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Dewi Manager Machining Cost',
        'email' => 'dewi.costmgr@factory.com',
        'password' => 'password',
        'npk' => 'EMP-C9902',
    ]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Agus Milling Worker',
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-BRW-COST-002',
        'submission_date' => $now->toDateString(),
        'operational_date' => $now->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 3.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 52000.00,
        'total_cost_snapshot' => 156000.00,
        'status' => 'APPROVED',
        'lock_version' => 1,
    ]);

    visit('/login')
        ->fill('email', 'dewi.costmgr@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Analytics & Decision')
        ->assertPathIs('/analytics')
        ->click('[data-test="tab-cost"]')
        ->assertSee('Total Overtime Cost')
        ->assertSee('Machining Cost Plant')
        ->assertPresent('[data-test="card-total-cost"]')
        ->assertPresent('[data-test="cost-breakdown-table"]')
        ->fill('[data-test="search-dept-cost"]', 'Machining')
        ->assertSee('Machining Cost Plant');
});
