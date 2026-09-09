<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;

function ensureSelfServiceBrowserCalendarDate(string $date, string $dayType = 'HKN'): void
{
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => $dayType,
            'is_holiday' => false,
        ]);
    }
}

test('operator logs in and lands directly on personal self-service dashboard with 3 KPI cards', function () {
    ensureSelfServiceBrowserCalendarDate('2026-09-05', 'HKN');

    $dept = Department::factory()->create([
        'name' => 'Assembly Stamping Plant',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'name' => 'Door Line 1',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Bambang Sudirman',
        'npk' => 'EMP-OP-4091',
        'job_position' => 'Senior Welder',
        'hourly_rate' => 42000.00,
        'is_active' => true,
    ]);

    $operator = User::factory()->user()->create([
        'name' => 'Bambang Sudirman',
        'email' => 'bambang.sudirman@factory.com',
        'npk' => 'EMP-OP-4091',
        'password' => 'password',
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create();

    // Create an approved overtime submission
    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-BMB-001',
        'submission_date' => '2026-09-05',
        'operational_date' => '2026-09-05',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 4.0,
        'hours_tpm' => 2.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 6.0,
        'hourly_rate_snapshot' => 42000.00,
        'total_cost_snapshot' => 252000.00,
        'status' => 'APPROVED',
        'task_description' => 'Welding subframe batch 4A',
    ]);

    visit('/login')
        ->fill('email', 'bambang.sudirman@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/my/dashboard')
        ->waitForText('Bambang Sudirman')
        ->assertSee('Bambang Sudirman')
        ->assertSee('EMP-OP-4091')
        ->assertSee('Senior Welder')
        ->assertSee('Assembly Stamping Plant')
        ->assertSee('6.0')
        ->assertSee('Safe')
        ->assertSee('Welding subframe batch 4A')
        ->assertSee('Approved');
});

test('operator views recent submissions with status badges and inline rejection reason', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $tl = User::factory()->teamLeader($section->id, $dept->id)->create();

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP-OP-5522',
        'full_name' => 'Doni Operator',
        'hourly_rate' => 35000.00,
    ]);

    $operator = User::factory()->user()->create([
        'npk' => 'EMP-OP-5522',
        'name' => 'Doni Operator',
        'email' => 'doni.operator@factory.com',
        'password' => 'password',
    ]);

    ensureSelfServiceBrowserCalendarDate('2026-09-02', 'HKN');
    ensureSelfServiceBrowserCalendarDate('2026-09-03', 'HLR');

    // 1. Pending submission (OvertimeSubmission status is SUBMITTED, OvertimeItem status is PENDING)
    $subPending = OvertimeSubmission::create([
        'submission_code' => 'OT-PEN-001',
        'submission_date' => '2026-09-02',
        'operational_date' => '2026-09-02',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subPending->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 3.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 3.0,
        'hourly_rate_snapshot' => 35000.00,
        'total_cost_snapshot' => 105000.00,
        'status' => 'PENDING',
        'task_description' => 'Material prep shift A',
    ]);

    // 2. Rejected submission
    $subRejected = OvertimeSubmission::create([
        'submission_code' => 'OT-REJ-002',
        'submission_date' => '2026-09-03',
        'operational_date' => '2026-09-03',
        'day_type' => 'HLR',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'REJECTED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subRejected->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 0.0,
        'hours_tpm' => 4.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 4.0,
        'hourly_rate_snapshot' => 35000.00,
        'total_cost_snapshot' => 140000.00,
        'status' => 'REJECTED',
        'task_description' => 'Emergency TPM line conveyor',
        'rejection_reason' => 'Bukan tugas shift yang disetujui supervisor',
    ]);

    visit('/login')
        ->fill('email', 'doni.operator@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/my/dashboard')
        ->waitForText('Doni Operator')
        ->assertSee('Material prep shift A')
        ->assertSee('Pending')
        ->assertSee('Emergency TPM line conveyor')
        ->assertSee('Rejected')
        ->assertSee('Rejection Reason:')
        ->assertSee('Bukan tugas shift yang disetujui supervisor');
});

test('quick navigation buttons link to full timesheet tab and welfare overview', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP-OP-6633',
        'full_name' => 'Siti Aminah',
    ]);

    $operator = User::factory()->user()->create([
        'npk' => 'EMP-OP-6633',
        'name' => 'Siti Aminah',
        'email' => 'siti.aminah@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'siti.aminah@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/my/dashboard')
        ->waitForText('Siti Aminah')
        ->assertSee('View Full Timesheet')
        ->click('[data-test="btn-view-full-timesheet"]')
        ->assertPathIs('/reports/employees/EMP-OP-6633')
        ->assertQueryStringHas('tab', 'timesheet');
});
