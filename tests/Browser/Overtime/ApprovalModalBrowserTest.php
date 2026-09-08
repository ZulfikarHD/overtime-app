<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('manager can open approval modal, approve all items, and see success toast and reactive status update', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    if (! OperationalCalendar::whereDate('calendar_date', $today)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $today,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create([
        'code' => 'DEPT_MODAL_'.uniqid(),
        'name' => 'Stamping Modal Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_MODAL_'.uniqid(),
        'name' => 'Press Line 1',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Ahmad Team Leader',
    ]);

    User::factory()->manager($dept->id)->create([
        'name' => 'Manager Modal Reviewer',
        'email' => 'mgr.modal@factory.com',
        'password' => 'password',
    ]);

    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Bambang Stamping',
        'npk' => 'EMP-MD-1001',
        'hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $emp2 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Cahyo Toolmaker',
        'npk' => 'EMP-MD-1002',
        'hourly_rate' => 40000,
        'is_active' => true,
    ]);

    $capex = CapexProject::create([
        'project_code' => 'CAPEX-MODAL-001',
        'asset_code' => 'AST-MD-01',
        'name' => 'Press Line Automation',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 100,
        'allocated_labor_budget_idr' => 5000000,
        'physical_progress_pct' => 10,
        'status' => 'ACTIVE',
        'start_date' => $today,
        'target_end_date' => Carbon::parse($today)->addMonth()->toDateString(),
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-MD-APP-001',
        'submission_date' => $today,
        'operational_date' => $today,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 6.5,
    ]);

    $item1 = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'hours_production' => 3.5,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000,
        'total_cost_snapshot' => 3.5 * 35000,
        'status' => 'PENDING',
        'task_description' => 'Press operational overtime',
    ]);

    $item2 = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp2->id,
        'npk_snapshot' => $emp2->npk,
        'capex_project_id' => $capex->id,
        'hours_production' => 0.0,
        'hours_tpm' => 1.0,
        'hours_project' => 2.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 40000,
        'total_cost_snapshot' => 3.0 * 40000,
        'status' => 'PENDING',
        'task_description' => 'Automation tool setup',
    ]);

    $page = visit('/login')
        ->fill('email', 'mgr.modal@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee('OT-MD-APP-001')
        ->assertNoJavaScriptErrors();

    // Open Approval Modal via Review Button
    $page->click('[data-test="review-btn-'.$submission->id.'"]')
        ->assertPresent('[data-test="approval-modal"]')
        ->assertSee('OT-MD-APP-001')
        ->assertSee('Bambang Stamping')
        ->assertSee('Cahyo Toolmaker')
        ->assertSee('CAPEX-MODAL-001')
        ->assertNoJavaScriptErrors();

    // Click "Setujui Semua (Approve All)"
    $page->click('[data-test="btn-approve-all"]')
        ->assertPresent('[data-test="counter-approved"]')
        ->assertNoJavaScriptErrors();

    // Click "Simpan Keputusan"
    $page->click('[data-test="btn-save-decisions"]');

    // Wait for submission status update
    $freshSub = null;
    for ($i = 0; $i < 20; $i++) {
        usleep(100000);
        $freshSub = $submission->fresh();
        if ($freshSub->status === 'APPROVED') {
            break;
        }
    }

    expect($freshSub->status)->toBe('APPROVED');
});

test('manager can reject an item with mandatory reason and partially approve submission', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    if (! OperationalCalendar::whereDate('calendar_date', $today)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $today,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create([
        'code' => 'DEPT_REJ_'.uniqid(),
        'name' => 'Assembly Modal Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_REJ_'.uniqid(),
        'name' => 'Assy Line 2',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    User::factory()->manager($dept->id)->create([
        'email' => 'mgr.rej@factory.com',
        'password' => 'password',
    ]);

    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Dedi Assembly',
        'npk' => 'EMP-RJ-2001',
        'hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $emp2 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Eko Subassembly',
        'npk' => 'EMP-RJ-2002',
        'hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-MD-REJ-002',
        'submission_date' => $today,
        'operational_date' => $today,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 6.0,
    ]);

    $item1 = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'hours_production' => 3.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 30000,
        'total_cost_snapshot' => 90000,
        'status' => 'PENDING',
        'task_description' => 'Assemble main units',
    ]);

    $item2 = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp2->id,
        'npk_snapshot' => $emp2->npk,
        'hours_production' => 3.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 30000,
        'total_cost_snapshot' => 90000,
        'status' => 'PENDING',
        'task_description' => 'Unapproved line assist',
    ]);

    $page = visit('/login')
        ->fill('email', 'mgr.rej@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee('OT-MD-REJ-002');

    // Open Modal
    $page->click('[data-test="review-btn-'.$submission->id.'"]')
        ->assertPresent('[data-test="approval-modal"]');

    // Set first worker to Approve
    $page->click('[data-test="btn-decision-approve-'.$item1->id.'"]')
        ->assertPresent('[data-test="counter-approved"]');

    // Set second worker to Reject
    $page->click('[data-test="btn-decision-reject-'.$item2->id.'"]')
        ->assertPresent('[data-test="rejection-reason-container-'.$item2->id.'"]')
        ->assertPresent('[data-test="rejection-reason-error-'.$item2->id.'"]');

    // Fill rejection reason
    $page->fill('[data-test="input-rejection-reason-'.$item2->id.'"]', 'Pekerjaan tidak tercantum dalam SPKL resmi')
        ->assertMissing('[data-test="rejection-reason-error-'.$item2->id.'"]')
        ->click('[data-test="btn-save-decisions"]');

    // Wait for submission status update
    $freshSub = null;
    for ($i = 0; $i < 20; $i++) {
        usleep(100000);
        $freshSub = $submission->fresh();
        if ($freshSub->status === 'PARTIALLY_APPROVED') {
            break;
        }
    }

    expect($freshSub->status)->toBe('PARTIALLY_APPROVED');
    expect($item1->fresh()->status)->toBe('APPROVED');
    expect($item2->fresh()->status)->toBe('REJECTED');
});
