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

test('admin can force-unlock approved submission from approval queue via force unlock modal', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    if (! OperationalCalendar::whereDate('calendar_date', $today)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $today,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create([
        'code' => 'DEPT_ADM_'.uniqid(),
        'name' => 'Welding Admin Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_ADM_'.uniqid(),
        'name' => 'Robotics Admin Section',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $admin = User::factory()->admin()->create([
        'name' => 'Super Administrator',
        'email' => 'admin.unlock@factory.com',
        'password' => 'password',
    ]);

    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Rahmat Specialist',
        'npk' => 'EMP-UNL-2001',
        'hourly_rate' => 40000,
        'is_active' => true,
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-UNL-ADM-001',
        'submission_date' => $today,
        'operational_date' => $today,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 4.0,
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 4.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 40000,
        'total_cost_snapshot' => 160000,
        'status' => 'APPROVED',
        'lock_version' => 1,
    ]);

    $page = visit('/login');

    $page->fill('email', 'admin.unlock@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard');

    // Visit approval queue with status=ALL to see the approved submission
    $page->navigate('/overtime/approvals?status=ALL')
        ->assertSee('OT-UNL-ADM-001')
        ->assertPresent('[data-test="unlock-btn-'.$submission->id.'"]')
        ->assertNoJavaScriptErrors();

    // Click "Buka Kunci" button to open modal
    $page->click('[data-test="unlock-btn-'.$submission->id.'"]')
        ->assertPresent('[data-test="force-unlock-modal"]')
        ->assertSee('OT-UNL-ADM-001')
        ->assertPresent('[data-test="input-unlock-reason"]');

    // Confirm button should be disabled until reason is at least 5 chars
    $page->fill('[data-test="input-unlock-reason"]', 'Koreksi NPK operator yang salah catat atas memo HR No. 124/HR/IX/2026')
        ->click('[data-test="btn-confirm-unlock"]');

    // Wait for submission status to become SUBMITTED in database
    $freshSub = null;
    for ($i = 0; $i < 20; $i++) {
        usleep(100000);
        $freshSub = $submission->fresh();
        if ($freshSub->status === 'SUBMITTED') {
            break;
        }
    }

    expect($freshSub->status)->toBe('SUBMITTED');
    expect($item->fresh()->status)->toBe('PENDING');
});
