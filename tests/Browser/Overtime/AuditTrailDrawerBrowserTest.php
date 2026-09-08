<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeItemAudit;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('manager can open approval modal, click riwayat audit, view chronological timeline with state diff and close drawer', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    if (! OperationalCalendar::whereDate('calendar_date', $today)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $today,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $dept = Department::factory()->create([
        'code' => 'DEPT_DRAWER_'.uniqid(),
        'name' => 'Machining Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_DRAWER_'.uniqid(),
        'name' => 'CNC Line 1',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Ahmad Team Leader',
        'npk' => 'TL-9901',
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Audit Reviewer',
        'email' => 'mgr.audit@factory.com',
        'password' => 'password',
        'npk' => 'MGR-1042',
    ]);

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Slamet Machining',
        'npk' => 'EMP-DRW-001',
        'hourly_rate' => 38000,
        'is_active' => true,
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-DRW-AUD-001',
        'submission_date' => $today,
        'operational_date' => $today,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 3.5,
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 3.5,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 38000,
        'total_cost_snapshot' => 3.5 * 38000,
        'status' => 'PENDING',
        'task_description' => 'Milling tooling adjustments',
        'lock_version' => 1,
    ]);

    // Audit 1: SUBMITTED by Team Leader
    $auditSubmit = OvertimeItemAudit::create([
        'overtime_item_id' => $item->id,
        'action' => 'SUBMITTED',
        'actor_user_id' => $teamLeader->id,
        'previous_state' => null,
        'new_state' => $item->toArray(),
        'notes' => 'Pengajuan lembur diserahkan oleh Team Leader.',
        'ip_address' => '10.30.11.101',
        'created_at' => Carbon::now('Asia/Jakarta')->subHours(2),
    ]);

    // Audit 2: REJECTED by Manager with state diff
    $previousState = $item->toArray();
    $item->update([
        'status' => 'REJECTED',
        'rejection_reason' => 'Target shift tercapai tanpa lembur tooling.',
        'lock_version' => 2,
    ]);

    $auditReject = OvertimeItemAudit::create([
        'overtime_item_id' => $item->id,
        'action' => 'REJECTED',
        'actor_user_id' => $manager->id,
        'previous_state' => $previousState,
        'new_state' => $item->fresh()->toArray(),
        'notes' => 'Target shift tercapai tanpa lembur tooling.',
        'ip_address' => '10.30.11.202',
        'created_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $page = visit('/login')
        ->fill('email', 'mgr.audit@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('[data-test="nav-overtime-approvals"]')
        ->assertPathIs('/overtime/approvals')
        ->assertSee('OT-DRW-AUD-001')
        ->assertNoJavaScriptErrors();

    // 1. Open Approval Modal
    $page->click('[data-test="review-btn-'.$submission->id.'"]')
        ->assertPresent('[data-test="approval-modal"]')
        ->assertSee('Slamet Machining')
        ->assertPresent('[data-test="btn-audit-trail-'.$item->id.'"]')
        ->assertNoJavaScriptErrors();

    // 2. Click "Riwayat" button to open AuditTrailDrawer
    $page->click('[data-test="btn-audit-trail-'.$item->id.'"]')
        ->assertPresent('[data-test="audit-trail-drawer"]')
        ->assertPresent('[data-test="audit-item-summary"]')
        ->assertSee('EMP-DRW-001')
        ->assertSee('Slamet Machining')
        ->assertNoJavaScriptErrors();

    // 3. Verify Timeline Content
    $page->assertPresent('[data-test="audit-event-row-'.$auditReject->id.'"]')
        ->assertPresent('[data-test="audit-event-row-'.$auditSubmit->id.'"]')
        ->assertPresent('[data-test="audit-action-badge-'.$auditReject->id.'"]')
        ->assertPresent('[data-test="audit-action-badge-'.$auditSubmit->id.'"]')
        ->assertSee('Ahmad Team Leader')
        ->assertSee('Manager Audit Reviewer')
        ->assertSee('Target shift tercapai tanpa lembur tooling.')
        ->assertNoJavaScriptErrors();

    // 4. Verify State Diff Section
    $page->assertPresent('[data-test="audit-diff-container-'.$auditReject->id.'"]')
        ->assertNoJavaScriptErrors();

    // 5. Expand Collapsible Technical Metadata
    $page->click('[data-test="toggle-metadata-'.$auditReject->id.'"]')
        ->assertPresent('[data-test="metadata-body-'.$auditReject->id.'"]')
        ->assertSee('10.30.11.202')
        ->assertNoJavaScriptErrors();

    // 6. Close Drawer cleanly
    $page->click('[data-test="btn-close-audit-drawer"]')
        ->assertPresent('[data-test="approval-modal"]')
        ->assertNoJavaScriptErrors();
});
