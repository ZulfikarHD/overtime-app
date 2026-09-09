<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use App\Services\Policy\OvertimePolicyEvaluator;
use Carbon\Carbon;

function seedSubmissionWithItem(
    Employee $employee,
    string $date,
    float $hours,
    string $submissionStatus = 'SUBMITTED',
    string $itemStatus = 'PENDING',
): OvertimeSubmission {
    $dept = $employee->department;
    $section = $employee->section;
    $user = User::first() ?? User::factory()->admin()->create();

    $calendar = OperationalCalendar::whereDate('calendar_date', $date)->first();
    if (! $calendar) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => Carbon::parse($date)->isWeekend() ? 'HLR' : 'HKN',
            'is_holiday' => false,
        ]);
    }

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-'.str_replace('-', '', $date).'-'.uniqid(),
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $user->id,
        'status' => $submissionStatus,
        'total_hours_cached' => $hours,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => $hours,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 30000.0,
        'total_cost_snapshot' => $hours * 30000.0,
        'status' => $itemStatus,
    ]);

    return $submission;
}

test('evaluateEmployee returns level none when hours are within weekly limit', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    // Plant default weekly limit is 20.0
    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    /** @var OvertimePolicyEvaluator $evaluator */
    $evaluator = app(OvertimePolicyEvaluator::class);

    $warning = $evaluator->evaluateEmployee($employee->id, additionalHours: 10.0, date: '2026-09-08');

    expect($warning->level)->toBe('none')
        ->and($warning->weeklyTotal)->toBe(10.0)
        ->and($warning->consecutiveWeeks)->toBe(0)
        ->and($warning->weeklyLimit)->toBe(20.0);
});

test('evaluateEmployee returns level warning when cumulative hours exceed weekly limit', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    // Monday 2026-09-07: 15 hrs
    seedSubmissionWithItem($employee, '2026-09-07', 15.0);

    /** @var OvertimePolicyEvaluator $evaluator */
    $evaluator = app(OvertimePolicyEvaluator::class);

    // Tuesday 2026-09-08: adding 7.0 hrs -> 15 + 7 = 22.0 hrs > 20.0
    $warning = $evaluator->evaluateEmployee($employee->id, additionalHours: 7.0, date: '2026-09-08');

    expect($warning->level)->toBe('warning')
        ->and($warning->weeklyTotal)->toBe(22.0)
        ->and($warning->consecutiveWeeks)->toBe(1)
        ->and($warning->message)->toContain('22.0/20.0');
});

test('evaluateEmployee returns level danger when consecutive weeks alert threshold is reached', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    // Target week: 2026-09-08 (week 37)
    // 1 week ago: 2026-09-01 (Tuesday) - 22 hrs
    seedSubmissionWithItem($employee, '2026-09-01', 22.0);

    // 2 weeks ago: 2026-08-25 (Tuesday) - 25 hrs
    seedSubmissionWithItem($employee, '2026-08-25', 25.0);

    /** @var OvertimePolicyEvaluator $evaluator */
    $evaluator = app(OvertimePolicyEvaluator::class);

    // Target week current addition: 21 hrs -> target week is also over limit (consecutive weeks = 3)
    $warning = $evaluator->evaluateEmployee($employee->id, additionalHours: 21.0, date: '2026-09-08');

    expect($warning->level)->toBe('danger')
        ->and($warning->consecutiveWeeks)->toBe(3)
        ->and($warning->weeklyTotal)->toBe(21.0)
        ->and($warning->message)->toContain('3');
});

test('evaluateEmployee respects department threshold override over plant default', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    // Plant default is 20 hrs
    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    // Department override is 25 hrs
    PolicyThreshold::create([
        'department_id' => $dept->id,
        'weekly_soft_limit_hours' => 25.0,
        'consecutive_weeks_alert' => 4,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.0,
        'burn_danger_pct' => 115.0,
    ]);

    /** @var OvertimePolicyEvaluator $evaluator */
    $evaluator = app(OvertimePolicyEvaluator::class);

    // 22 hrs would be over plant limit (20), but is within department override (25)
    $warning = $evaluator->evaluateEmployee($employee->id, additionalHours: 22.0, date: '2026-09-08');

    expect($warning->level)->toBe('none')
        ->and($warning->weeklyLimit)->toBe(25.0)
        ->and($warning->weeklyTotal)->toBe(22.0);
});

test('evaluateEmployee excludes rejected overtime items and rejected submissions', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    // Rejected item on 2026-09-07: 15 hrs
    seedSubmissionWithItem($employee, '2026-09-07', 15.0, submissionStatus: 'SUBMITTED', itemStatus: 'REJECTED');

    // Rejected submission on 2026-09-07: 10 hrs
    seedSubmissionWithItem($employee, '2026-09-07', 10.0, submissionStatus: 'REJECTED', itemStatus: 'APPROVED');

    /** @var OvertimePolicyEvaluator $evaluator */
    $evaluator = app(OvertimePolicyEvaluator::class);

    // Additional 5 hrs -> should be 5.0 total (both rejected are excluded)
    $warning = $evaluator->evaluateEmployee($employee->id, additionalHours: 5.0, date: '2026-09-08');

    expect($warning->level)->toBe('none')
        ->and($warning->weeklyTotal)->toBe(5.0);
});

test('evaluateEmployee excludes specified submission id when excludeSubmissionId is provided', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    // Existing submission with 18 hrs
    $sub = seedSubmissionWithItem($employee, '2026-09-08', 18.0);

    /** @var OvertimePolicyEvaluator $evaluator */
    $evaluator = app(OvertimePolicyEvaluator::class);

    // If excluded (e.g. updating the submission with 10 hrs):
    $warning = $evaluator->evaluateEmployee(
        employeeId: $employee->id,
        additionalHours: 10.0,
        date: '2026-09-08',
        excludeSubmissionId: $sub->id,
    );

    expect($warning->level)->toBe('none')
        ->and($warning->weeklyTotal)->toBe(10.0);
});

test('getEmployeeWelfareStatus returns safe status and 100% safety score when within limits', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    // Week 0: 10 hrs approved
    seedSubmissionWithItem($employee, '2026-09-08', 10.0, submissionStatus: 'APPROVED', itemStatus: 'APPROVED');

    /** @var OvertimePolicyEvaluator $evaluator */
    $evaluator = app(OvertimePolicyEvaluator::class);

    $welfare = $evaluator->getEmployeeWelfareStatus($employee->id, '2026-09-08');

    expect($welfare->alertLevel)->toBe('safe')
        ->and($welfare->currentWeekHours)->toBe(10.0)
        ->and($welfare->weeklyLimit)->toBe(20.0)
        ->and($welfare->consecutiveWeeks)->toBe(0)
        ->and($welfare->exceededWeeksCount)->toBe(0)
        ->and($welfare->safetyScorePct)->toBe(100.0)
        ->and($welfare->isAdvisory)->toBeTrue()
        ->and($welfare->rollingWeeks)->toHaveCount(4);
});

test('getEmployeeWelfareStatus returns warning status when current week exceeds weekly limit', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    // Current week: 22 hrs approved
    seedSubmissionWithItem($employee, '2026-09-08', 22.0, submissionStatus: 'APPROVED', itemStatus: 'APPROVED');

    /** @var OvertimePolicyEvaluator $evaluator */
    $evaluator = app(OvertimePolicyEvaluator::class);

    $welfare = $evaluator->getEmployeeWelfareStatus($employee->id, '2026-09-08');

    expect($welfare->alertLevel)->toBe('warning')
        ->and($welfare->currentWeekHours)->toBe(22.0)
        ->and($welfare->exceededWeeksCount)->toBe(1)
        ->and($welfare->safetyScorePct)->toBe(75.0)
        ->and($welfare->consecutiveWeeks)->toBe(1);
});

test('getEmployeeWelfareStatus returns danger status and calculates correct safety score for consecutive overloads', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    // Current week (2026-09-08): 21 hrs
    seedSubmissionWithItem($employee, '2026-09-08', 21.0, submissionStatus: 'APPROVED', itemStatus: 'APPROVED');
    // Week -1 (2026-09-01): 24 hrs
    seedSubmissionWithItem($employee, '2026-09-01', 24.0, submissionStatus: 'APPROVED', itemStatus: 'APPROVED');
    // Week -2 (2026-08-25): 23 hrs
    seedSubmissionWithItem($employee, '2026-08-25', 23.0, submissionStatus: 'APPROVED', itemStatus: 'APPROVED');
    // Week -3 (2026-08-18): 12 hrs (within limit)
    seedSubmissionWithItem($employee, '2026-08-18', 12.0, submissionStatus: 'APPROVED', itemStatus: 'APPROVED');

    /** @var OvertimePolicyEvaluator $evaluator */
    $evaluator = app(OvertimePolicyEvaluator::class);

    $welfare = $evaluator->getEmployeeWelfareStatus($employee->id, '2026-09-08');

    // 3 out of 4 weeks exceeded limit -> safety score = 100 - (3/4 * 100) = 25.0%
    // consecutive weeks = 3 >= 3 -> danger level
    expect($welfare->alertLevel)->toBe('danger')
        ->and($welfare->consecutiveWeeks)->toBe(3)
        ->and($welfare->exceededWeeksCount)->toBe(3)
        ->and($welfare->safetyScorePct)->toBe(25.0);

    $dangerBadge = collect($welfare->badges)->firstWhere('type', 'danger');
    expect($dangerBadge)->not->toBeNull()
        ->and($dangerBadge['message'])->toContain('3');
});
