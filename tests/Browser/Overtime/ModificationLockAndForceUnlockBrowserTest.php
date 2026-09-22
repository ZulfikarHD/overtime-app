<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('team leader sees locked indicator without edit button for approved submission in history and detail modal', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    if (! OperationalCalendar::whereDate('calendar_date', $today)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $today,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create([
        'code' => 'DEPT_LCK_'.uniqid(),
        'name' => 'Stamping Lock Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_LCK_'.uniqid(),
        'name' => 'Press Lock Section',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Bambang Team Leader',
        'email' => 'tl.locked@factory.com',
        'password' => 'password',
    ]);

    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Kurniawan Worker',
        'npk' => 'EMP-LCK-1001',
        'hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-LCK-001-'.uniqid(),
        'submission_date' => $today,
        'operational_date' => $today,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 3.0,
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 3.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000,
        'total_cost_snapshot' => 105000,
        'status' => 'APPROVED',
        'task_description' => 'Shift overtime work',
    ]);

    $page = visit('/login');

    $page->fill('email', 'tl.locked@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard');

    $page->navigate('/overtime/submissions')
        ->assertPathIs('/overtime/submissions')
        ->assertSee($submission->submission_code)
        ->assertPresent('[data-test="btn-locked-'.$submission->id.'"]')
        ->assertMissing('[data-test="btn-edit-'.$submission->id.'"]')
        ->assertSee('Locked');

    // Open detail modal and verify locked notice
    $page->click('[data-test="btn-detail-'.$submission->id.'"]')
        ->assertPresent('[data-test="submission-detail-modal"]')
        ->assertPresent('[data-test="modal-locked-notice"]')
        ->assertMissing('[data-test="btn-modal-edit"]');
});

test('admin can visit approval queue and page loads correctly', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Super Administrator',
        'email' => 'admin.unlock.brw@factory.com',
        'password' => 'password',
    ]);

    $page = visit('/login');

    $page->fill('email', 'admin.unlock.brw@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard');

    $page->navigate('/overtime/approvals')
        ->assertPathIs('/overtime/approvals')
        ->assertNoJavaScriptErrors();
});
