<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;

test('team leader can switch between form and history via navigation tabs', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_HIST',
        'name' => 'Machining Plant',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_CRANK',
        'name' => 'Crankshaft Line',
        'is_active' => true,
    ]);

    User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.machining@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'tl.machining@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Overtime Entry')
        ->assertPathIs('/overtime/submissions/create')
        ->assertSee('Overtime Entry Form')
        // Switch to history tab
        ->click('[data-test="tab-history-link"]')
        ->assertPathIs('/overtime/submissions')
        ->assertSee('Riwayat Pengajuan')
        // Switch back to form tab
        ->click('[data-test="tab-form-create"]')
        ->assertPathIs('/overtime/submissions/create')
        ->assertSee('Overtime Entry Form');
});

test('team leader can populate roster, input hours, and submit overtime batch', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_WLD',
        'name' => 'Welding Plant',
        'default_hourly_rate' => 32000.00,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_UNDER',
        'name' => 'Underbody Welding Line',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.welding@factory.com',
        'password' => 'password',
    ]);

    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-60001',
        'full_name' => 'Hendro Setiawan',
        'hourly_rate' => 40000.00,
        'is_active' => true,
    ]);

    $emp2 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-60002',
        'full_name' => 'Yusuf Maulana',
        'hourly_rate' => null,
        'is_active' => true,
    ]);

    $capex = CapexProject::create([
        'project_code' => 'CIP-BRW-01',
        'name' => 'Robotic Weld Jig Automation',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 100.00,
        'allocated_labor_budget_idr' => 4000000.00,
        'physical_progress_pct' => 15.00,
        'status' => 'ACTIVE',
        'start_date' => '2026-01-01',
        'target_end_date' => '2026-12-31',
    ]);

    visit('/login')
        ->fill('email', 'tl.welding@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Overtime Entry')
        ->assertPathIs('/overtime/submissions/create')
        // Populate all workers with 1 click
        ->click('[data-test="btn-empty-add-all"]')
        ->assertSee('EMP-60001')
        ->assertSee('EMP-60002')
        ->fill('[data-test="input-prod-'.$emp1->id.'"]', '2.5')
        ->fill('[data-test="input-prod-'.$emp2->id.'"]', '3.0')
        ->assertSee('5.5')
        ->click('[data-test="btn-submit-overtime"]')
        // Assert celebratory success card
        ->assertSee('Overtime Request Submitted Successfully!')
        ->assertSee('SPKL: Pending Attachment (Non-blocking BR-05)');

    $this->assertDatabaseHas('overtime_submissions', [
        'section_id' => $section->id,
        'status' => 'SUBMITTED',
    ]);

    $this->assertDatabaseHas('overtime_items', [
        'employee_id' => $emp1->id,
        'hours_production' => 2.50,
    ]);

    $this->assertDatabaseHas('spkl_documents', [
        'status' => 'PENDING',
    ]);
});

test('form retains entered rows upon validation failure (zero data loss)', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_ERR',
        'name' => 'Assembly Plant',
        'default_hourly_rate' => 30000.00,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_TRIM',
        'name' => 'Trim Line 1',
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.assembly@factory.com',
        'password' => 'password',
    ]);

    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-70001',
        'full_name' => 'Supriadi Kusuma',
    ]);

    visit('/login')
        ->fill('email', 'tl.assembly@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Overtime Entry')
        ->click('[data-test="btn-empty-add-all"]')
        ->assertSee('EMP-70001')
        // Submit with 0 hours (violates BR-01: minimum 0.5 hours)
        ->click('[data-test="btn-submit-overtime"]')
        // Form retains entered worker row
        ->assertSee('EMP-70001')
        ->assertSee('Supriadi Kusuma')
        ->assertSee('BR-01');
});

test('financial cost snapshot is displayed and remains immutable across employee wage updates', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_SNAP',
        'name' => 'Engine Assembly Plant',
        'default_hourly_rate' => 30000.00,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_CYL',
        'name' => 'Cylinder Block Line',
        'is_active' => true,
    ]);

    User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.engine@factory.com',
        'password' => 'password',
    ]);

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-88001',
        'full_name' => 'Bambang Trihatmodjo',
        'hourly_rate' => 40000.00,
        'is_active' => true,
    ]);

    visit('/login')
        ->fill('email', 'tl.engine@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Overtime Entry')
        ->assertPathIs('/overtime/submissions/create')
        ->click('[data-test="btn-empty-add-all"]')
        ->assertSee('EMP-88001')
        ->fill('[data-test="input-prod-'.$emp->id.'"]', '2.5')
        ->assertSee('2.5')
        ->assertSee('Rp 100.000')
        ->click('[data-test="btn-submit-overtime"]')
        ->assertSee('Overtime Request Submitted Successfully!')
        ->assertSee('Rp 100.000')
        // Navigate to history tab
        ->click('[data-test="btn-view-history"]')
        ->assertPathIs('/overtime/submissions')
        ->assertSee('Riwayat Pengajuan')
        ->assertSee('Rp 100.000');

    // Retroactively update the employee's wage to Rp 80.000 / hr
    $emp->update(['hourly_rate' => 80000.00]);

    // Refresh history view: historical snapshot MUST strictly remain Rp 100.000 (not Rp 200.000!)
    visit('/overtime/submissions')
        ->assertPathIs('/overtime/submissions')
        ->assertSee('Rp 100.000')
        ->assertDontSee('Rp 200.000');
});

test('team leader can view history, use filters, and inspect detail modal', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_MODAL',
        'name' => 'Die Casting Plant',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_CAST',
        'name' => 'HPDC Line 1',
        'is_active' => true,
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.diecast@factory.com',
        'password' => 'password',
    ]);

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-99001',
        'full_name' => 'Danang Wijaya',
        'hourly_rate' => 40000.00,
        'is_active' => true,
    ]);

    $date = '2026-09-08';
    $calendar = OperationalCalendar::whereDate('calendar_date', $date)->first();
    if (! $calendar) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-20260908-CAST-001',
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 3.0,
        'submission_notes' => 'Pekerjaan die repair mendesak',
    ]);

    $submission->items()->create([
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 3.0,
        'hourly_rate_snapshot' => 40000.00,
        'total_cost_snapshot' => 120000.00,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    $submission->spklDocument()->create([
        'status' => 'PENDING',
        'due_date' => '2026-09-11',
    ]);

    visit('/login')
        ->fill('email', 'tl.diecast@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard');

    visit('/overtime/submissions')
        ->assertPathIs('/overtime/submissions')
        ->assertSee('OT-20260908-CAST-001')
        ->assertSee('Rp 120.000')
        // Open Detail Modal
        ->click('[data-test="btn-detail-'.$submission->id.'"]')
        ->assertSee('OT-20260908-CAST-001')
        ->assertSee('Pekerjaan die repair mendesak')
        ->assertSee('EMP-99001')
        ->assertSee('Danang Wijaya')
        // Close Detail Modal
        ->click('[data-test="btn-close-modal"]')
        ->assertDontSee('Pekerjaan die repair mendesak');
});

test('team leader can edit submitted overtime while approved submission displays locked badge', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_EDIT',
        'name' => 'Paint Plant',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_TOP',
        'name' => 'Top Coat Line',
        'is_active' => true,
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.paint@factory.com',
        'password' => 'password',
    ]);

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-55001',
        'full_name' => 'Rahmat Hidayat',
        'hourly_rate' => 36000.00,
        'is_active' => true,
    ]);

    $date = '2026-09-08';
    $calendar = OperationalCalendar::whereDate('calendar_date', $date)->first();
    if (! $calendar) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    // Submission 1: SUBMITTED (Editable)
    $subEditable = OvertimeSubmission::create([
        'submission_code' => 'OT-20260908-TOP-001',
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 1.5,
    ]);

    $subEditable->items()->create([
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 1.5,
        'hourly_rate_snapshot' => 36000.00,
        'total_cost_snapshot' => 54000.00,
        'status' => 'PENDING',
        'lock_version' => 1,
    ]);

    $subEditable->spklDocument()->create([
        'status' => 'PENDING',
        'due_date' => '2026-09-11',
    ]);

    // Submission 2: APPROVED (Locked)
    $subLocked = OvertimeSubmission::create([
        'submission_code' => 'OT-20260908-TOP-002',
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 2.0,
    ]);

    $subLocked->items()->create([
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 2.0,
        'hourly_rate_snapshot' => 36000.00,
        'total_cost_snapshot' => 72000.00,
        'status' => 'APPROVED',
        'lock_version' => 1,
    ]);

    visit('/login')
        ->fill('email', 'tl.paint@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard');

    visit('/overtime/submissions')
        ->assertPathIs('/overtime/submissions')
        // Verify locked indicator on approved submission
        ->assertSee('Locked')
        // Click edit on submitted submission
        ->click('[data-test="btn-edit-'.$subEditable->id.'"]')
        ->assertPathIs('/overtime/submissions/'.$subEditable->id.'/edit')
        ->assertSee('OT-20260908-TOP-001')
        ->assertSee('EMP-55001')
        // Submit changes
        ->click('[data-test="btn-submit-overtime"]')
        ->assertPathIs('/overtime/submissions')
        ->assertSee('OT-20260908-TOP-001');
});
