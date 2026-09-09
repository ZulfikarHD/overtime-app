<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;

test('team leader can view peer benchmarking panel with CALC-06 variance, distribution chart, and top bottom lists', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_PEER_01',
        'name' => 'Assembly Stamping Plant',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_PEER_01',
        'name' => 'Door Line 1',
        'is_active' => true,
    ]);

    $empA = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Bambang Pamungkas',
        'npk' => 'EMP-PEER-7711',
        'job_position' => 'Senior Stamping Tech',
        'hourly_rate' => 50000.00,
        'is_active' => true,
    ]);

    $empB = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Agus Subordinate',
        'npk' => 'EMP-PEER-7722',
        'job_position' => 'Line Operator',
        'hourly_rate' => 45000.00,
        'is_active' => true,
    ]);

    $empC = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Charlie Operator',
        'npk' => 'EMP-PEER-7733',
        'job_position' => 'Welder Specialist',
        'hourly_rate' => 48000.00,
        'is_active' => true,
    ]);

    // Calendar date
    if (! OperationalCalendar::whereDate('calendar_date', '2026-09-05')->exists()) {
        OperationalCalendar::create([
            'calendar_date' => '2026-09-05',
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Supervisor Hendro',
        'email' => 'hendro.peer.tl@factory.com',
        'password' => 'password',
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-PEER-SUB-01',
        'submission_date' => '2026-09-05',
        'operational_date' => '2026-09-05',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'APPROVED',
    ]);

    // Emp A: 20.0h, Emp B: 10.0h, Emp C: 0h -> Total: 30h, Avg: 10.0h
    // Emp A variance: +10.0h (above)
    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $empA->id,
        'npk_snapshot' => $empA->npk,
        'hours_production' => 15.0,
        'hours_tpm' => 5.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 1000000.0,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $empB->id,
        'npk_snapshot' => $empB->npk,
        'hours_production' => 10.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 45000.0,
        'total_cost_snapshot' => 450000.0,
        'status' => 'APPROVED',
    ]);

    visit('/login')
        ->fill('email', 'hendro.peer.tl@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        // Directly navigate to employee dossier overview
        ->navigate('/reports/employees/EMP-PEER-7711?year=2026&month=9')
        ->assertPathIs('/reports/employees/EMP-PEER-7711')
        ->waitForText('Current Month Hours')
        ->assertSee('Bambang Pamungkas')
        ->assertSee('EMP-PEER-7711')
        // Peer Benchmarking Panel Presence
        ->assertSee('Peer Benchmarking & Section Workload Distribution')
        ->assertSee('Workload comparison against section average (CALC-06)')
        // Metrics
        ->assertSee('Employee Overtime Hours')
        ->assertSee('20.0')
        ->assertSee('Section Average')
        ->assertSee('10.0')
        ->assertSee('Workload Deviation (CALC-06)')
        ->assertSee('+10.0 hours above section average')
        // Section distribution chart components
        ->assertSee('Section Members Overtime Distribution')
        ->assertSee('Selected Employee')
        ->assertSee('Section Peers')
        // Top 5 and Bottom 5 lists
        ->assertSee('Top 5 Highest Hours')
        ->assertSee('Top 5 Lowest Hours')
        ->assertSee('Agus Subordinate')
        ->assertSee('Charlie Operator');
});

test('operator role viewing own dossier sees peer benchmarking with privacy mode anonymization', function () {
    $dept = Department::factory()->create([
        'name' => 'Machining Plant',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'name' => 'Engine Block Line',
        'is_active' => true,
    ]);

    $operatorEmployee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Siti Operator',
        'npk' => 'OP-SITI-88',
        'job_position' => 'Milling Operator',
        'hourly_rate' => 42000.00,
        'is_active' => true,
    ]);

    $peerEmployee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Rahasia Rekan Kerja',
        'npk' => 'OP-RAHASIA-99',
        'job_position' => 'Lathe Specialist',
        'hourly_rate' => 45000.00,
        'is_active' => true,
    ]);

    $operatorUser = User::factory()->create([
        'name' => 'Siti Operator',
        'email' => 'siti.operator@factory.com',
        'password' => 'password',
        'role' => 'user',
        'npk' => 'OP-SITI-88',
        'department_id' => $dept->id,
        'section_id' => $section->id,
    ]);

    // Calendar date
    if (! OperationalCalendar::whereDate('calendar_date', '2026-09-08')->exists()) {
        OperationalCalendar::create([
            'calendar_date' => '2026-09-08',
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-ANON-01',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $operatorUser->id,
        'status' => 'APPROVED',
    ]);

    // Siti: 8.0h, Peer: 14.0h
    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $operatorEmployee->id,
        'npk_snapshot' => $operatorEmployee->npk,
        'hours_production' => 8.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 42000.0,
        'total_cost_snapshot' => 336000.0,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $peerEmployee->id,
        'npk_snapshot' => $peerEmployee->npk,
        'hours_production' => 14.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 45000.0,
        'total_cost_snapshot' => 630000.0,
        'status' => 'APPROVED',
    ]);

    visit('/login')
        ->fill('email', 'siti.operator@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/my/dashboard')
        // Directly navigate to own dossier
        ->navigate('/reports/employees/OP-SITI-88?year=2026&month=9')
        ->assertPathIs('/reports/employees/OP-SITI-88')
        ->waitForText('Current Month Hours')
        ->assertSee('Siti Operator')
        ->assertSee('OP-SITI-88')
        // Anonymization Privacy Badge & Notice
        ->assertSee('Operator Privacy Mode')
        ->assertSee('Co-worker data is anonymized to maintain confidentiality between operators.')
        // Co-worker name and NPK should NOT be visible
        ->assertDontSee('Rahasia Rekan Kerja')
        ->assertDontSee('OP-RAHASIA-99')
        // Instead, masked label should be visible
        ->assertSee('Karyawan #1');
});
