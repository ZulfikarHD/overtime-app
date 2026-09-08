<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('manager can select multiple submissions, inspect floating bulk bar, confirm bulk approval, and see reactive success toast', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    if (! OperationalCalendar::whereDate('calendar_date', $today)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $today,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_BULK_'.uniqid(),
        'name' => 'Bulk Browser Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_BLK_'.uniqid(),
        'name' => 'Assembly Line Bulk',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    User::factory()->manager($dept->id)->create([
        'name' => 'Manager Bulk Reviewer',
        'email' => 'mgr.bulk@factory.com',
        'password' => 'password',
    ]);

    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Dedi Santoso',
        'npk' => 'EMP-BLK-01',
        'hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $emp2 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Eko Prasetyo',
        'npk' => 'EMP-BLK-02',
        'hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $sub1 = OvertimeSubmission::create([
        'submission_code' => 'OT-BLK-SUB-001',
        'submission_date' => $today,
        'operational_date' => $today,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 3.5,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub1->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'hours_production' => 3.5,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000,
        'total_cost_snapshot' => 3.5 * 35000,
        'status' => 'PENDING',
        'task_description' => 'Assembly overtime job 1',
        'lock_version' => 1,
    ]);

    $sub2 = OvertimeSubmission::create([
        'submission_code' => 'OT-BLK-SUB-002',
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
        'overtime_submission_id' => $sub2->id,
        'employee_id' => $emp2->id,
        'npk_snapshot' => $emp2->npk,
        'hours_production' => 4.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000,
        'total_cost_snapshot' => 4.0 * 35000,
        'status' => 'PENDING',
        'task_description' => 'Assembly overtime job 2',
        'lock_version' => 1,
    ]);

    $page = visit('/login')
        ->fill('email', 'mgr.bulk@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee('OT-BLK-SUB-001')
        ->assertSee('OT-BLK-SUB-002')
        ->assertMissing('[data-test="floating-bulk-bar"]')
        ->assertNoJavaScriptErrors();

    // Check row 1
    $page->check('[data-test="approval-row-checkbox-'.$sub1->id.'"]')
        ->assertPresent('[data-test="floating-bulk-bar"]')
        ->assertSeeIn('[data-test="bulk-selected-count"]', '1');

    // Check row 2
    $page->check('[data-test="approval-row-checkbox-'.$sub2->id.'"]')
        ->assertSeeIn('[data-test="bulk-selected-count"]', '2')
        ->assertSeeIn('[data-test="bulk-hours-stat"]', '7.5');

    // Click Bulk Approve
    $page->click('[data-test="btn-bulk-approve"]')
        ->assertPresent('[data-test="bulk-approval-confirm-modal"]')
        ->assertPresent('[data-test="bulk-modal-title"]')
        ->assertSee('OT-BLK-SUB-001')
        ->assertSee('OT-BLK-SUB-002')
        ->click('[data-test="btn-bulk-confirm"]')
        ->assertPresent('[data-test="bulk-action-result-toast"]')
        ->assertNoJavaScriptErrors();
});

test('manager can bulk reject submissions with mandatory shared rejection reason', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    if (! OperationalCalendar::whereDate('calendar_date', $today)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $today,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    User::factory()->manager($dept->id)->create([
        'email' => 'mgr.bulkreject@factory.com',
        'password' => 'password',
    ]);

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-BLK-REJ-01',
        'hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-BLK-REJ-001',
        'submission_date' => $today,
        'operational_date' => $today,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 2.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 2.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 30000,
        'total_cost_snapshot' => 60000,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    $page = visit('/login')
        ->fill('email', 'mgr.bulkreject@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->check('[data-test="approval-row-checkbox-'.$sub->id.'"]')
        ->click('[data-test="btn-bulk-reject"]')
        ->assertPresent('[data-test="bulk-approval-confirm-modal"]')
        ->assertPresent('[data-test="bulk-modal-title"]')
        ->assertPresent('[data-test="bulk-rejection-reason-container"]')
        ->fill('[data-test="bulk-rejection-reason-input"]', 'Target shift tercapai tanpa lembur')
        ->click('[data-test="btn-bulk-confirm"]')
        ->assertPresent('[data-test="bulk-action-result-toast"]')
        ->assertNoJavaScriptErrors();
});

test('manager can toggle select-all on page and clear bulk selection', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    if (! OperationalCalendar::whereDate('calendar_date', $today)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $today,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    User::factory()->manager($dept->id)->create([
        'email' => 'mgr.selectall@factory.com',
        'password' => 'password',
    ]);

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-SELECTALL-001',
        'submission_date' => $today,
        'operational_date' => $today,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 2.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 2.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 30000,
        'total_cost_snapshot' => 60000,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    visit('/login')
        ->fill('email', 'mgr.selectall@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->check('[data-test="select-all-checkbox"]')
        ->assertPresent('[data-test="floating-bulk-bar"]')
        ->click('[data-test="btn-bulk-clear"]')
        ->assertMissing('[data-test="floating-bulk-bar"]')
        ->assertNoJavaScriptErrors();
});
