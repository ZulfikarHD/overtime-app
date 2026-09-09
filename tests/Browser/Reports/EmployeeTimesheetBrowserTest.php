<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;

function ensureBrowserTimesheetCalendar(string $date, string $dayType = 'HKN'): void
{
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => $dayType,
            'is_holiday' => false,
        ]);
    }
}

test('team leader can navigate to employee timesheet tab, filter items, expand rejected rows, and see export button', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_TS',
        'name' => 'Assembly Stamping Plant',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_TS',
        'name' => 'Door Line 1',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Bambang Pamungkas',
        'npk' => 'EMP-TS-7711',
        'job_position' => 'Senior Stamping Tech',
        'hourly_rate' => 50000.00,
        'is_active' => true,
    ]);

    $capex = CapexProject::create([
        'department_id' => $dept->id,
        'project_code' => 'CPX-2026-ASSY-001',
        'name' => 'Assembly Automation Line',
        'allocated_labor_hours' => 120,
        'allocated_labor_budget_idr' => 6000000,
        'physical_progress_pct' => 25,
        'status' => 'ACTIVE',
        'start_date' => '2026-01-01',
        'target_end_date' => '2026-12-31',
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Supervisor Hendro',
        'email' => 'hendro.timesheet.tl@factory.com',
        'password' => 'password',
    ]);

    ensureBrowserTimesheetCalendar('2026-09-02', 'HKN');
    ensureBrowserTimesheetCalendar('2026-09-08', 'HLR');

    // 1. Approved item
    $sub1 = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-BRW-01',
        'submission_date' => '2026-09-02',
        'operational_date' => '2026-09-02',
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
        'hours_production' => 3.5,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 175000.0,
        'status' => 'APPROVED',
        'task_description' => 'Fix conveyor belt tensioner',
    ]);

    // 2. Rejected item with CapEx allocation
    $sub2 = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-BRW-02',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HLR',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'REJECTED',
    ]);

    $item2 = OvertimeItem::create([
        'overtime_submission_id' => $sub2->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 4.0,
        'hours_others' => 0.0,
        'capex_project_id' => $capex->id,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 200000.0,
        'status' => 'REJECTED',
        'rejection_reason' => 'Salah alokasi CapEx, pekerjaan preventif bukan proyek',
        'rca_category' => 'FACILITY_MAINTENANCE',
        'rca_notes' => 'Catatan penolakan supervisor',
        'task_description' => 'Setup sensor automation station',
    ]);

    visit('/login')
        ->fill('email', 'hendro.timesheet.tl@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->navigate("/reports/employees/{$employee->npk}?year=2026&month=9")
        ->assertPathIs("/reports/employees/{$employee->npk}")
        ->waitForText('Bambang Pamungkas')
        ->assertSee('EMP-TS-7711')
        ->click('@tab-timesheet')
        ->wait(0.5)
        ->assertPresent('[data-test="personal-timesheet-section"]')
        ->assertPresent('[data-test="timesheet-metrics-summary"]')
        ->assertPresent('[data-test="personal-timesheet-table"]')
        ->assertPresent('[data-test="btn-export-csv"]')
        ->assertAttributeContains('[data-test="btn-export-csv"]', 'href', "/reports/employees/{$employee->npk}/timesheet/export")
        ->assertSee('OT-SUB-BRW-01')
        ->assertSee('OT-SUB-BRW-02')
        ->assertSee('CPX-2026-ASSY-001')
        ->click("[data-test=\"btn-toggle-row-{$item2->id}\"]")
        ->wait(0.5)
        ->assertPresent('[data-test="rejection-reason-callout"]')
        ->assertSee('Salah alokasi CapEx, pekerjaan preventif bukan proyek')
        ->assertSee('Setup sensor automation station')
        ->assertNoJavaScriptErrors();
});
