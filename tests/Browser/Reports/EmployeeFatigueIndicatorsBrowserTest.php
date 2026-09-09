<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use App\Notifications\FatigueAlertNotification;

function ensureBrowserWelfareCalendar(string $date): void
{
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }
}

test('team leader can view fatigue rolling chart, safety score gauge, and advisory disclaimer on dossier', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_FATIGUE_01',
        'name' => 'Assembly Stamping Plant',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_FATIGUE_01',
        'name' => 'Door Line 1',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Bambang Kelelahan',
        'npk' => 'EMP-FATIGUE-77',
        'job_position' => 'Senior Stamping Tech',
        'hourly_rate' => 50000.00,
        'is_active' => true,
    ]);

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    ensureBrowserWelfareCalendar('2026-09-08');
    ensureBrowserWelfareCalendar('2026-09-01');
    ensureBrowserWelfareCalendar('2026-08-25');

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Supervisor Hendro',
        'email' => 'hendro.fatigue.tl@factory.com',
        'password' => 'password',
    ]);

    // Seed 3 consecutive overloaded weeks
    $subWeek0 = OvertimeSubmission::create([
        'submission_code' => 'OT-FATIGUE-W0',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subWeek0->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 22.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 1100000.0,
        'status' => 'APPROVED',
    ]);

    $subWeek1 = OvertimeSubmission::create([
        'submission_code' => 'OT-FATIGUE-W1',
        'submission_date' => '2026-09-01',
        'operational_date' => '2026-09-01',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subWeek1->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 24.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 1200000.0,
        'status' => 'APPROVED',
    ]);

    $subWeek2 = OvertimeSubmission::create([
        'submission_code' => 'OT-FATIGUE-W2',
        'submission_date' => '2026-08-25',
        'operational_date' => '2026-08-25',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subWeek2->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 25.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 1250000.0,
        'status' => 'APPROVED',
    ]);

    visit('/login')
        ->fill('email', 'hendro.fatigue.tl@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->navigate("/reports/employees/{$employee->npk}?year=2026&month=9")
        ->assertPathIs("/reports/employees/{$employee->npk}")
        // Employee Identity
        ->waitForText('Bambang Kelelahan')
        ->assertSee('EMP-FATIGUE-77')
        // Welfare indicators present
        ->assertSee('Rolling 4-Week Workload Trend')
        ->assertSee('Safety & Fatigue Score')
        ->assertSee('Weekly Limit:')
        ->assertSee('20.0')
        // Safety score 25% (3 of 4 weeks overloaded)
        ->assertSee('25%')
        ->assertSee('3')
        // Soft alert badges
        ->assertSee('Fatigue Risk: 3 consecutive weeks over limit!')
        // Industrial advisory banner (Zero operational roadblocks)
        ->assertSee('Advisory Notice:')
        ->assertSee('does not block emergency overtime');
});

test('team leader receives fatigue alert in notification bell and navigates to dossier', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_BELL_01',
        'name' => 'Machining Dept',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BELL_01',
        'name' => 'Crankshaft Line',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Santoso Alerted',
        'npk' => 'EMP-BELL-123',
        'job_position' => 'CNC Specialist',
        'hourly_rate' => 45000.00,
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Foreman Joko',
        'email' => 'joko.bell.tl@factory.com',
        'password' => 'password',
    ]);

    // Send fatigue notification to team leader
    $teamLeader->notify(new FatigueAlertNotification(
        employee: $employee,
        consecutiveWeeks: 3,
        weeklyHours: 23.5,
        weeklyLimit: 20.0,
        fiscalYear: 2026,
        fiscalMonth: 9,
    ));

    visit('/login')
        ->fill('email', 'joko.bell.tl@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->navigate('/reports/employees')
        ->assertPathIs('/reports/employees')
        // Notification bell should show unread count badge
        ->waitForText('1')
        ->click('[data-test="notification-bell-btn"]')
        ->waitForText('Santoso Alerted')
        ->assertSee('EMP-BELL-123')
        ->assertSee('Open Dossier')
        // Click action button to navigate to dossier
        ->click('[data-test="btn-view-dossier-from-notif"]')
        ->assertPathIs("/reports/employees/{$employee->npk}")
        ->waitForText('Santoso Alerted')
        ->assertSee('EMP-BELL-123');
});
