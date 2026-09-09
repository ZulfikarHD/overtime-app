<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;

test('team leader can view employee dossier overview with KPI cards, category donut, and day-type breakdown', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_E02',
        'name' => 'Assembly Stamping Plant',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_E02',
        'name' => 'Door Line 1',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Bambang Pamungkas',
        'npk' => 'EMP-7711',
        'job_position' => 'Senior Stamping Tech',
        'hourly_rate' => 50000.00,
        'is_active' => true,
    ]);

    $coworker = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Agus Subordinate',
        'npk' => 'EMP-7722',
        'job_position' => 'Line Operator',
        'is_active' => true,
    ]);

    // Calendar dates
    if (! OperationalCalendar::whereDate('calendar_date', '2026-09-05')->exists()) {
        OperationalCalendar::create([
            'calendar_date' => '2026-09-05',
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    if (! OperationalCalendar::whereDate('calendar_date', '2026-09-12')->exists()) {
        OperationalCalendar::create([
            'calendar_date' => '2026-09-12',
            'day_type' => 'HLR',
            'is_holiday' => false,
        ]);
    }

    // Section Budget: 100h planned for 2 active employees = 50h planned each
    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 100.0,
        'planned_cost_idr' => 5000000.0,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Supervisor Hendro',
        'email' => 'hendro.tl@factory.com',
        'password' => 'password',
    ]);

    // Submissions and items for employee: 10h HKN + 8h HLR = 18h
    $sub1 = OvertimeSubmission::create([
        'submission_code' => 'OT-BRW-001',
        'submission_date' => '2026-09-05',
        'operational_date' => '2026-09-05',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub1->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 6.0,
        'hours_tpm' => 4.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 500000.0,
        'status' => 'APPROVED',
    ]);

    $sub2 = OvertimeSubmission::create([
        'submission_code' => 'OT-BRW-002',
        'submission_date' => '2026-09-12',
        'operational_date' => '2026-09-12',
        'day_type' => 'HLR',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub2->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 6.0,
        'hours_others' => 2.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 400000.0,
        'status' => 'APPROVED',
    ]);

    visit('/login')
        ->fill('email', 'hendro.tl@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        // Navigate to employee reports
        ->click('[data-test="nav-employee-reports"]')
        ->assertPathIs('/reports/employees')
        ->assertSee('Dossier Hub')
        ->assertSee('Bambang Pamungkas')
        // Search and select Bambang
        ->fill('[data-test="employee-search-input"]', 'Bambang')
        ->waitForText('Senior Stamping Tech')
        ->click('[data-test="employee-search-item"]')
        ->assertPathIs('/reports/employees/EMP-7711')
        ->waitForText('Current Month Hours')
        ->assertSee('Bambang Pamungkas')
        ->assertSee('EMP-7711')
        // 4 KPI Cards
        ->assertSee('Current Month Hours')
        ->assertSee('18.0')
        ->assertSee('Year-to-Date Hours (YTD)')
        ->assertSee('Individual Burn Index')
        ->assertSee('36.0%')
        ->assertSee('Section Workload Rank')
        // Financial Cost Snapshot
        ->assertSee('Total Estimated Overtime Cost')
        ->assertSee('Rp 900.000')
        // Category Donut Card
        ->assertSee('Overtime Category Distribution')
        ->assertSee('Production')
        ->assertSee('TPM')
        ->assertSee('CapEx')
        ->assertSee('Others')
        // Day-Type Breakdown Card
        ->assertSee('Workday & Holiday Distribution (HKN vs HLR)')
        ->assertSee('Normal Workday (HKN)')
        ->assertSee('Rest / Holiday Day (HLR)')
        ->assertSee('10.0')
        ->assertSee('8.0');
});

test('team leader viewing employee dossier for month with no overtime sees zeroed metrics and empty donut state', function () {
    $dept = Department::factory()->create([
        'name' => 'Machining Plant',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'name' => 'Crankshaft Line',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Dedi Machining',
        'npk' => 'EMP-9900',
        'job_position' => 'CNC Specialist',
        'hourly_rate' => 55000.00,
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Leader Budi',
        'email' => 'leader.budi@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'leader.budi@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        // Directly navigate to employee dossier with query for month 10 (no data)
        ->navigate('/reports/employees/EMP-9900?year=2026&month=10')
        ->assertPathIs('/reports/employees/EMP-9900')
        ->waitForText('Current Month Hours')
        ->assertSee('Dedi Machining')
        ->assertSee('EMP-9900')
        // Zero Hours KPI
        ->assertSee('Current Month Hours')
        ->assertSee('0.0')
        ->assertSee('Year-to-Date Hours (YTD)')
        ->assertSee('Total Estimated Overtime Cost')
        ->assertSee('Rp 0')
        ->assertSee('Total This Month')
        ->assertSee('approved hours');
});
