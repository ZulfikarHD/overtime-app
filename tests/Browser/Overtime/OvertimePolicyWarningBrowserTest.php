<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('timesheet displays real-time advisory yellow warning when weekly limit is exceeded and allows submission', function () {
    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
    ]);

    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_POL1',
        'name' => 'Assembly Plant 1',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_CHASSIS',
        'name' => 'Chassis Assembly',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.chassis@factory.com',
        'password' => 'password',
    ]);

    $emp1 = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-80001',
        'full_name' => 'Andi Wijaya',
        'hourly_rate' => 40000.00,
        'is_active' => true,
    ]);

    visit('/login')
        ->fill('email', 'tl.chassis@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Overtime Entry')
        ->assertPathIs('/overtime/submissions/create')
        ->click('[data-test="btn-empty-add-all"]')
        ->assertSee('EMP-80001')
        ->fill('[data-test="input-prod-'.$emp1->id.'"]', '22.0')
        // Blur to trigger policy check immediately
        ->click('[data-test="row-total-'.$emp1->id.'"]')
        // Assert advisory yellow warning badge appears
        ->waitForText('22.0/20.0')
        ->assertPresent('[data-test="policy-warning-badge-'.$emp1->id.'"]')
        // Ensure non-blocking submission per BR-06
        ->click('[data-test="btn-submit-overtime"]')
        ->assertSee('Overtime Request Submitted Successfully!');

    $this->assertDatabaseHas('overtime_submissions', [
        'section_id' => $section->id,
        'status' => 'SUBMITTED',
    ]);
});

test('timesheet displays red badge for employee with consecutive high workload history', function () {
    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
    ]);

    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_POL2',
        'name' => 'Engine Plant 2',
        'default_hourly_rate' => 38000.00,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_PISTON',
        'name' => 'Piston Line',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.piston@factory.com',
        'password' => 'password',
    ]);

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-80002',
        'full_name' => 'Bambang Hartono',
        'hourly_rate' => 42000.00,
        'is_active' => true,
    ]);

    // Seed 2 past consecutive weeks exceeding 20 hrs
    $w1Date = Carbon::now('Asia/Jakarta')->subWeeks(1)->startOfWeek(Carbon::MONDAY)->toDateString();
    $w2Date = Carbon::now('Asia/Jakarta')->subWeeks(2)->startOfWeek(Carbon::MONDAY)->toDateString();

    foreach ([$w1Date => 22.0, $w2Date => 24.0] as $date => $hours) {
        $calendar = OperationalCalendar::whereDate('calendar_date', $date)->first();
        if (! $calendar) {
            OperationalCalendar::create([
                'calendar_date' => $date,
                'day_type' => 'HKN',
                'is_holiday' => false,
            ]);
        }

        $sub = OvertimeSubmission::create([
            'submission_code' => 'OT-'.str_replace('-', '', $date).'-TEST-'.uniqid(),
            'submission_date' => $date,
            'operational_date' => $date,
            'day_type' => 'HKN',
            'department_id' => $dept->id,
            'section_id' => $section->id,
            'submitted_by_user_id' => $teamLeader->id,
            'status' => 'SUBMITTED',
            'total_hours_cached' => $hours,
        ]);

        OvertimeItem::create([
            'overtime_submission_id' => $sub->id,
            'employee_id' => $emp->id,
            'npk_snapshot' => $emp->npk,
            'hours_production' => $hours,
            'hours_tpm' => 0.0,
            'hours_project' => 0.0,
            'hours_others' => 0.0,
            'hourly_rate_snapshot' => 42000.0,
            'total_cost_snapshot' => $hours * 42000.0,
            'status' => 'PENDING',
        ]);
    }

    visit('/login')
        ->fill('email', 'tl.piston@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Overtime Entry')
        ->assertPathIs('/overtime/submissions/create')
        ->click('[data-test="btn-empty-add-all"]')
        ->assertSee('EMP-80002')
        // Current week input of 21 hrs pushes streak to 3 consecutive weeks
        ->fill('[data-test="input-prod-'.$emp->id.'"]', '21.0')
        ->click('[data-test="row-total-'.$emp->id.'"]')
        ->waitForText('3')
        ->assertPresent('[data-test="policy-warning-badge-'.$emp->id.'"]')
        // Ensure non-blocking submission per BR-06
        ->click('[data-test="btn-submit-overtime"]')
        ->assertSee('Overtime Request Submitted Successfully!');
});

test('submission detail modal surfaces policy warning badge on line item for review', function () {
    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
    ]);

    $dept = Department::factory()->create([
        'code' => 'DEPT_BRW_POL3',
        'name' => 'Paint Department',
        'default_hourly_rate' => 36000.00,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_BRW_BOOTH',
        'name' => 'Spray Booth Line',
        'is_active' => true,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.paint@factory.com',
        'password' => 'password',
    ]);

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-80003',
        'full_name' => 'Cahyo Utomo',
        'hourly_rate' => 36000.00,
        'is_active' => true,
    ]);

    $today = Carbon::now('Asia/Jakarta')->toDateString();
    $calendar = OperationalCalendar::whereDate('calendar_date', $today)->first();
    if (! $calendar) {
        OperationalCalendar::create([
            'calendar_date' => $today,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-'.str_replace('-', '', $today).'-BOOTH-001',
        'submission_date' => $today,
        'operational_date' => $today,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 25.0,
    ]);

    $item = OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 25.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 36000.0,
        'total_cost_snapshot' => 25.0 * 36000.0,
        'status' => 'PENDING',
    ]);

    visit('/login')
        ->fill('email', 'tl.paint@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Overtime Entry')
        ->click('[data-test="tab-history-link"]')
        ->assertPathIs('/overtime/submissions')
        ->assertSee('OT-'.str_replace('-', '', $today).'-BOOTH-001')
        ->click('[data-test="btn-detail-'.$submission->id.'"]')
        ->assertPresent('[data-test="submission-detail-modal"]')
        ->assertPresent('[data-test="detail-policy-warning-'.$item->id.'"]')
        ->assertSee('25.0/20.0');
});
