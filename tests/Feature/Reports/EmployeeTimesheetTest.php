<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function ensureTimesheetCalendarDate(string $date, string $dayType = 'HKN'): void
{
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => $dayType,
            'is_holiday' => false,
        ]);
    }
}

test('team leader can view personal timesheet tab for direct report in their section', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'ISZ-8801',
        'full_name' => 'Bambang Pamungkas',
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create();

    ensureTimesheetCalendarDate('2026-09-02');
    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-TS-01',
        'submission_date' => '2026-09-02',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'operational_date' => '2026-09-02',
        'day_type' => 'HKN',
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 3.5,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'status' => 'APPROVED',
        'task_description' => 'Chassis alignment repair',
    ]);

    $response = $this->actingAs($tl)->get(route('reports.employees.show', [
        'npk' => $employee->npk,
        'tab' => 'timesheet',
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('reports/EmployeeDossier')
        ->where('current_tab', 'timesheet')
        ->has('timesheet.data', 1)
        ->where('timesheet.data.0.status', 'APPROVED')
        ->where('timesheet.data.0.task_description', 'Chassis alignment repair')
        ->where('timesheet.summary.total_items', 1)
        ->where('timesheet.summary.total_hours', 3.5)
        ->where('timesheet.summary.approved_hours', 3.5)
    );
});

test('team leader cannot view timesheet of employee from another section', function () {
    $dept = Department::factory()->create();
    $sectionA = Section::factory()->create(['department_id' => $dept->id]);
    $sectionB = Section::factory()->create(['department_id' => $dept->id]);

    $employeeOther = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sectionB->id,
        'npk' => 'ISZ-8802',
    ]);

    $tlA = User::factory()->teamLeader($sectionA->id, $dept->id)->create();

    $response = $this->actingAs($tlA)->get(route('reports.employees.show', [
        'npk' => $employeeOther->npk,
        'tab' => 'timesheet',
    ]));

    $response->assertForbidden();
});

test('manager can view timesheet of any employee in their department', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'ISZ-8803',
    ]);

    $manager = User::factory()->manager($dept->id)->create();

    ensureTimesheetCalendarDate('2026-09-03');
    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-TS-02',
        'submission_date' => '2026-09-03',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $manager->id,
        'operational_date' => '2026-09-03',
        'day_type' => 'HKN',
        'status' => 'SUBMITTED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 2.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'status' => 'PENDING',
    ]);

    $response = $this->actingAs($manager)->get(route('reports.employees.show', [
        'npk' => $employee->npk,
        'tab' => 'timesheet',
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('reports/EmployeeDossier')
        ->has('timesheet.data', 1)
        ->where('timesheet.data.0.status', 'PENDING')
    );
});

test('user role employee can view their own timesheet but not others', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $ownEmp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP-SELF-01',
    ]);

    $otherEmp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP-OTHER-02',
    ]);

    $user = User::factory()->create([
        'role' => 'user',
        'npk' => $ownEmp->npk,
        'department_id' => $dept->id,
        'section_id' => $section->id,
    ]);

    // Own timesheet access succeeds
    $responseOwn = $this->actingAs($user)->get(route('reports.employees.show', [
        'npk' => $ownEmp->npk,
        'tab' => 'timesheet',
    ]));
    $responseOwn->assertOk();

    // Snooping on other employee timesheet is forbidden (403)
    $responseOther = $this->actingAs($user)->get(route('reports.employees.show', [
        'npk' => $otherEmp->npk,
        'tab' => 'timesheet',
    ]));
    $responseOther->assertForbidden();
});

test('timesheet tab route redirects to show endpoint with tab query param', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'ISZ-REDIR-1',
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create();

    $response = $this->actingAs($tl)->get(route('reports.employees.timesheet', [
        'npk' => $employee->npk,
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertRedirect(route('reports.employees.show', [
        'npk' => $employee->npk,
        'year' => 2026,
        'month' => 9,
        'tab' => 'timesheet',
    ]));
});

test('timesheet items show rejection reason and CapEx project details when rejected', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'ISZ-REJ-01',
    ]);

    $capex = CapexProject::create([
        'department_id' => $dept->id,
        'project_code' => 'CPX-2026-WELD-01',
        'name' => 'Welding JIG Automation',
        'allocated_labor_hours' => 100,
        'allocated_labor_budget_idr' => 5000000,
        'physical_progress_pct' => 10,
        'status' => 'ACTIVE',
        'start_date' => '2026-01-01',
        'target_end_date' => '2026-12-31',
    ]);

    $admin = User::factory()->admin()->create();

    ensureTimesheetCalendarDate('2026-09-04');
    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-TS-03',
        'submission_date' => '2026-09-04',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $admin->id,
        'operational_date' => '2026-09-04',
        'day_type' => 'HLR',
        'status' => 'REJECTED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 4.0,
        'hours_others' => 0.0,
        'capex_project_id' => $capex->id,
        'status' => 'REJECTED',
        'rejection_reason' => 'Salah alokasi CapEx, pekerjaan preventif bukan proyek',
        'rca_category' => 'OTHER',
        'rca_notes' => 'Catatan verifikasi',
        'task_description' => 'Perakitan JIG tambahan',
    ]);

    $response = $this->actingAs($admin)->get(route('reports.employees.show', [
        'npk' => $employee->npk,
        'tab' => 'timesheet',
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('reports/EmployeeDossier')
        ->where('timesheet.data.0.status', 'REJECTED')
        ->where('timesheet.data.0.rejection_reason', 'Salah alokasi CapEx, pekerjaan preventif bukan proyek')
        ->where('timesheet.data.0.is_capex', true)
        ->where('timesheet.data.0.capex_project.project_code', 'CPX-2026-WELD-01')
        ->where('timesheet.data.0.capex_project.name', 'Welding JIG Automation')
        ->where('timesheet.data.0.day_type', 'HLR')
        ->where('timesheet.summary.rejected_hours', fn ($val) => (float) $val === 4.0)
    );
});

test('timesheet filtering by status, category, date range, and text search works', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'ISZ-FILTER-01',
    ]);

    $admin = User::factory()->admin()->create();

    ensureTimesheetCalendarDate('2026-09-01');
    ensureTimesheetCalendarDate('2026-09-02');
    ensureTimesheetCalendarDate('2026-09-03');

    $sub1 = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-F-01',
        'submission_date' => '2026-09-01',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $admin->id,
        'operational_date' => '2026-09-01',
        'day_type' => 'HKN',
    ]);
    $sub2 = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-F-02',
        'submission_date' => '2026-09-02',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $admin->id,
        'operational_date' => '2026-09-02',
        'day_type' => 'HKN',
    ]);
    $sub3 = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-F-03',
        'submission_date' => '2026-09-03',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $admin->id,
        'operational_date' => '2026-09-03',
        'day_type' => 'HKN',
    ]);

    // Item 1: Production, Approved
    OvertimeItem::create([
        'overtime_submission_id' => $sub1->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 2.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'status' => 'APPROVED',
        'task_description' => 'Fix conveyor motor',
    ]);

    // Item 2: TPM, Pending
    OvertimeItem::create([
        'overtime_submission_id' => $sub2->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 0.0,
        'hours_tpm' => 3.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'status' => 'PENDING',
        'task_description' => 'Monthly lubrication audit',
    ]);

    // Item 3: Project, Rejected
    OvertimeItem::create([
        'overtime_submission_id' => $sub3->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 1.5,
        'hours_others' => 0.0,
        'status' => 'REJECTED',
        'task_description' => 'Robotic arm calibration',
    ]);

    // Test filter by status = APPROVED
    $resApproved = $this->actingAs($admin)->get(route('reports.employees.show', [
        'npk' => $employee->npk,
        'status' => 'APPROVED',
        'year' => 2026,
        'month' => 9,
    ]));
    $resApproved->assertOk()->assertInertia(fn (Assert $p) => $p
        ->where('timesheet.pagination.total', 1)
        ->where('timesheet.data.0.status', 'APPROVED')
    );

    // Test filter by category = tpm
    $resTpm = $this->actingAs($admin)->get(route('reports.employees.show', [
        'npk' => $employee->npk,
        'category' => 'tpm',
        'year' => 2026,
        'month' => 9,
    ]));
    $resTpm->assertOk()->assertInertia(fn (Assert $p) => $p
        ->where('timesheet.pagination.total', 1)
        ->where('timesheet.data.0.hours_tpm', fn ($val) => (float) $val === 3.0)
    );

    // Test text search
    $resSearch = $this->actingAs($admin)->get(route('reports.employees.show', [
        'npk' => $employee->npk,
        'search' => 'conveyor',
        'year' => 2026,
        'month' => 9,
    ]));
    $resSearch->assertOk()->assertInertia(fn (Assert $p) => $p
        ->where('timesheet.pagination.total', 1)
        ->where('timesheet.data.0.task_description', 'Fix conveyor motor')
    );
});

test('csv export streams timesheet with utf-8 bom and correct headers and values', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'ISZ-CSV-99',
        'full_name' => 'Charlie Siregar',
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create();

    ensureTimesheetCalendarDate('2026-09-05');
    $sub = OvertimeSubmission::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'submission_code' => 'OT-SUB-CSV-01',
        'submission_date' => '2026-09-05',
        'operational_date' => '2026-09-05',
        'day_type' => 'HKN',
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 2.5,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 45000.00,
        'total_cost_snapshot' => 112500.00,
        'status' => 'APPROVED',
        'task_description' => 'Shift handover assembly',
    ]);

    $response = $this->actingAs($tl)->get(route('reports.employees.timesheet.export', [
        'npk' => $employee->npk,
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toContain('Timesheet-ISZ-ISZ-CSV-99-');

    $content = $response->streamedContent();

    // Check UTF-8 BOM is present
    expect(substr($content, 0, 3))->toBe(chr(0xEF).chr(0xBB).chr(0xBF));

    // Check CSV headers
    expect($content)->toContain('Kode Pengajuan')
        ->and($content)->toContain('Tanggal (WIB)')
        ->and($content)->toContain('Produksi (Jam)')
        ->and($content)->toContain('Total Jam')
        ->and($content)->toContain('OT-SUB-CSV-01')
        ->and($content)->toContain('2026-09-05')
        ->and($content)->toContain('2.50')
        ->and($content)->toContain('Shift handover assembly');
});

test('csv export enforces authorization scope', function () {
    $dept = Department::factory()->create();
    $secA = Section::factory()->create(['department_id' => $dept->id]);
    $secB = Section::factory()->create(['department_id' => $dept->id]);

    $empInSecB = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $secB->id,
        'npk' => 'ISZ-SEC-B',
    ]);

    $tlInSecA = User::factory()->teamLeader($secA->id, $dept->id)->create();

    $response = $this->actingAs($tlInSecA)->get(route('reports.employees.timesheet.export', [
        'npk' => $empInSecB->npk,
    ]));

    $response->assertForbidden();
});
