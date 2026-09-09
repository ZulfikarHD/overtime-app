<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function ensureSelfServiceCalendarDate(string $date, string $dayType = 'HKN'): void
{
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => $dayType,
            'is_holiday' => false,
        ]);
    }
}

test('user role is redirected to personal self-service dashboard when accessing /dashboard', function () {
    $user = User::factory()->user()->create([
        'npk' => 'EMP-OP-001',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('my.dashboard'));
});

test('supervisory roles access the operational dashboard without redirection', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $admin = User::factory()->admin()->create();
    $manager = User::factory()->manager($dept->id)->create();
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));

    $this->actingAs($teamLeader)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
});

test('operator receives their own profile, 3-card summary, and welfare status on self-service dashboard', function () {
    ensureSelfServiceCalendarDate('2026-09-05', 'HKN');

    $dept = Department::factory()->create(['name' => 'Manufacturing Dept']);
    $section = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Welding Section']);
    $tl = User::factory()->teamLeader($section->id, $dept->id)->create();

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP-OP-778',
        'full_name' => 'Ahmad Dahlan',
        'job_position' => 'Welding Operator',
        'hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $operator = User::factory()->user()->create([
        'npk' => 'EMP-OP-778',
        'name' => 'Ahmad Dahlan',
    ]);

    // Create an approved overtime submission for current month
    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-OP-001',
        'submission_date' => '2026-09-05',
        'operational_date' => '2026-09-05',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 4.0,
        'hours_tpm' => 1.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 5.0,
        'hourly_rate_snapshot' => 35000.00,
        'total_cost_snapshot' => 175000.00,
        'status' => 'APPROVED',
        'task_description' => 'Body welding chassis support',
    ]);

    $response = $this->actingAs($operator)->get(route('my.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/EmployeeSelfService')
        ->where('employee.npk', 'EMP-OP-778')
        ->where('employee.full_name', 'Ahmad Dahlan')
        ->where('employee.job_position', 'Welding Operator')
        ->where('employee.department.name', 'Manufacturing Dept')
        ->where('employee.section.name', 'Welding Section')
        ->where('summary.current_month_hours', 5)
        ->where('summary.total_cost_idr', 175000)
        ->where('summary.ytd_hours', 5)
        ->has('welfare_status')
        ->where('welfare_status.employee_id', $employee->id)
        ->has('recent_timesheet', 1)
        ->where('recent_timesheet.0.status', 'APPROVED')
        ->where('recent_timesheet.0.total_hours', 5)
        ->where('recent_timesheet.0.task_description', 'Body welding chassis support')
        ->where('auth.user.employee_id', $employee->id)
    );
});

test('self-service dashboard displays maximum 5 recent submissions with rejection reason if rejected', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $tl = User::factory()->teamLeader($section->id, $dept->id)->create();

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP-OP-999',
    ]);

    $operator = User::factory()->user()->create([
        'npk' => 'EMP-OP-999',
    ]);

    // Create 7 submissions across different dates
    for ($i = 1; $i <= 7; $i++) {
        $date = sprintf('2026-09-%02d', $i);
        ensureSelfServiceCalendarDate($date, 'HKN');

        $isRejected = ($i === 7);
        $status = $isRejected ? 'REJECTED' : 'APPROVED';

        $sub = OvertimeSubmission::create([
            'submission_code' => sprintf('OT-SUB-%03d', $i),
            'submission_date' => $date,
            'operational_date' => $date,
            'day_type' => 'HKN',
            'department_id' => $dept->id,
            'section_id' => $section->id,
            'submitted_by_user_id' => $tl->id,
            'status' => $status,
        ]);

        OvertimeItem::create([
            'overtime_submission_id' => $sub->id,
            'employee_id' => $employee->id,
            'npk_snapshot' => $employee->npk,
            'hours_production' => 2.0,
            'hours_tpm' => 0.0,
            'hours_project' => 0.0,
            'hours_others' => 0.0,
            'total_hours' => 2.0,
            'hourly_rate_snapshot' => 30000.00,
            'total_cost_snapshot' => 60000.00,
            'status' => $status,
            'task_description' => "Shift job {$i}",
            'rejection_reason' => $isRejected ? 'Kelebihan alokasi jam kerja shift' : null,
        ]);
    }

    $response = $this->actingAs($operator)->get(route('my.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/EmployeeSelfService')
        ->has('recent_timesheet', 5)
        // Most recent item should be date 2026-09-07 (rejected item)
        ->where('recent_timesheet.0.submission_code', 'OT-SUB-007')
        ->where('recent_timesheet.0.status', 'REJECTED')
        ->where('recent_timesheet.0.rejection_reason', 'Kelebihan alokasi jam kerja shift')
    );
});

test('unlinked operator account gracefully displays empty profile state', function () {
    // User without linked employee NPK
    $operator = User::factory()->user()->create([
        'npk' => null,
    ]);

    $response = $this->actingAs($operator)->get(route('my.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/EmployeeSelfService')
        ->where('employee', null)
        ->where('summary', null)
        ->where('welfare_status', null)
        ->where('recent_timesheet', [])
        ->where('auth.user.employee_id', null)
    );
});
