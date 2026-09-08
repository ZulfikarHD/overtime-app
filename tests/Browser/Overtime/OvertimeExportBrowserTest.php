<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('manager can interact with export dropdown on approval queue', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    if (! OperationalCalendar::whereDate('calendar_date', $today)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $today,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_EXP',
        'name' => 'Assembly Browser Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_EXP',
        'name' => 'Trim Line Export',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Agus TL Export',
    ]);

    User::factory()->manager($dept->id)->create([
        'name' => 'Manager Export Browser',
        'email' => 'mgr.export@factory.com',
        'password' => 'password',
    ]);

    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Cahyo Operator',
        'npk' => 'EMP-EXP-1001',
        'hourly_rate' => 38000,
        'is_active' => true,
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-BRW-EXP-001',
        'submission_date' => $today,
        'operational_date' => $today,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 4.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 4.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 38000,
        'total_cost_snapshot' => 4.0 * 38000,
        'status' => 'PENDING',
        'task_description' => 'Trim line final check',
    ]);

    $page = visit('/login')
        ->fill('email', 'mgr.export@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee('OT-BRW-EXP-001')
        ->assertPresent('[data-test="export-dropdown-trigger"]')
        ->assertNoJavaScriptErrors();

    // Click export trigger and inspect dropdown
    $page->click('[data-test="export-dropdown-trigger"]')
        ->assertPresent('[data-test="export-dropdown-menu"]')
        ->assertPresent('[data-test="export-csv-option"]')
        ->assertPresent('[data-test="export-xlsx-option"]')
        ->assertSee('CSV')
        ->assertSee('Excel')
        ->assertNoJavaScriptErrors();

    // Click CSV export option
    $page->click('[data-test="export-csv-option"]')
        ->assertNoJavaScriptErrors();
});
