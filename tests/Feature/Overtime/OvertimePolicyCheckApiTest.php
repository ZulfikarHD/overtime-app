<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;

test('guest is redirected to login when calling overtime policy check endpoint', function () {
    $this->get(route('overtime.policy-check', ['employee_id' => 1]))
        ->assertRedirect(route('login'));
});

test('team leader cannot check policy for employee from unauthorized section', function () {
    $dept = Department::factory()->create();
    $section1 = Section::factory()->create(['department_id' => $dept->id]);
    $section2 = Section::factory()->create(['department_id' => $dept->id]);

    $teamLeader = User::factory()->teamLeader($section1->id, $dept->id)->create();
    $employeeSection2 = Employee::factory()->forDepartmentAndSection($dept, $section2)->create();

    $this->actingAs($teamLeader)
        ->getJson(route('overtime.policy-check', [
            'employee_id' => $employeeSection2->id,
            'additional_hours' => 5.0,
        ]))
        ->assertForbidden()
        ->assertJson(['message' => __('Anda tidak memiliki akses ke data karyawan ini.')]);
});

test('team leader can check policy for employee in their own section', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    $response = $this->actingAs($teamLeader)
        ->getJson(route('overtime.policy-check', [
            'employee_id' => $employee->id,
            'additional_hours' => 22.0,
            'date' => '2026-09-08',
        ]))
        ->assertOk()
        ->assertJsonStructure([
            'level',
            'message',
            'weekly_total',
            'consecutive_weeks',
            'weekly_limit',
            'warning',
        ]);

    expect($response->json('level'))->toBe('warning')
        ->and((float) $response->json('weekly_total'))->toBe(22.0)
        ->and((float) $response->json('weekly_limit'))->toBe(20.0);
});

test('overtime policy check validates required parameters', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $this->actingAs($teamLeader)
        ->getJson(route('overtime.policy-check', []))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_id']);
});

test('submission show response embeds policy warning on each overtime item', function () {
    $dept = Department::factory()->create(['default_hourly_rate' => 30000.0]);
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'hourly_rate' => 35000.0,
    ]);

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    $calendar = OperationalCalendar::whereDate('calendar_date', '2026-09-08')->first();
    if (! $calendar) {
        OperationalCalendar::create([
            'calendar_date' => '2026-09-08',
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-20260908-SEC-999',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $teamLeader->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 24.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 24.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.0,
        'total_cost_snapshot' => 24.0 * 35000.0,
        'status' => 'PENDING',
    ]);

    $response = $this->actingAs($teamLeader)
        ->getJson(route('overtime.submissions.show', $submission))
        ->assertOk()
        ->assertJsonStructure([
            'submission' => [
                'items' => [
                    '*' => [
                        'id',
                        'policy_warning' => [
                            'level',
                            'message',
                            'weekly_total',
                            'consecutive_weeks',
                            'weekly_limit',
                        ],
                    ],
                ],
            ],
        ]);

    $firstItem = $response->json('submission.items.0');
    expect($firstItem['policy_warning']['level'])->toBe('warning')
        ->and((float) $firstItem['policy_warning']['weekly_total'])->toBe(24.0);
});
