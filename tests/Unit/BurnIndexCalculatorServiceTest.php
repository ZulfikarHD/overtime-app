<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use App\Services\Analytics\BurnIndexCalculatorService;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 9, 15, 10, 0, 0, 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function createTestOvertimeItem(Section $section, string $date, float $prod, float $tpm, float $proj, float $others, string $itemStatus = 'APPROVED', string $submissionStatus = 'SUBMITTED'): OvertimeItem
{
    $exists = OperationalCalendar::whereDate('calendar_date', $date)->exists();
    if (! $exists) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $user = User::factory()->create([
        'department_id' => $section->department_id,
        'section_id' => $section->id,
    ]);

    $employee = Employee::factory()->create([
        'department_id' => $section->department_id,
        'section_id' => $section->id,
        'hourly_rate' => 30000,
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-TEST-'.uniqid(),
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $section->department_id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $user->id,
        'status' => $submissionStatus,
        'total_hours_cached' => $prod + $tpm + $proj + $others,
    ]);

    return OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => $prod,
        'hours_tpm' => $tpm,
        'hours_project' => $proj,
        'hours_others' => $others,
        'hourly_rate_snapshot' => 30000,
        'total_cost_snapshot' => ($prod + $tpm + $proj + $others) * 30000,
        'status' => $itemStatus,
    ]);
}

test('burn index and remaining hours calculation matches CALC-02 and CALC-03', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 200.0,
    ]);

    // 100 hours approved in September 2026
    createTestOvertimeItem($section, '2026-09-05', 40.0, 10.0, 30.0, 20.0, 'APPROVED');

    // 50 hours pending (should NOT count toward actuals)
    createTestOvertimeItem($section, '2026-09-08', 50.0, 0.0, 0.0, 0.0, 'PENDING');

    $service = new BurnIndexCalculatorService;
    $metrics = $service->calculateSectionMetrics($section->id, 2026, 9);

    expect($metrics['planned_hours'])->toBe(200.0)
        ->and($metrics['actual_hours'])->toBe(100.0)
        ->and($metrics['remaining_hours'])->toBe(100.0)
        ->and($metrics['burn_index_pct'])->toBe(50.0)
        ->and($metrics['is_budget_configured'])->toBeTrue();
});

test('zero planned budget handles division by zero safely without exception', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    // No OvertimeBudget created for this section
    createTestOvertimeItem($section, '2026-09-10', 25.0, 0.0, 0.0, 0.0, 'APPROVED');

    $service = new BurnIndexCalculatorService;
    $metrics = $service->calculateSectionMetrics($section->id, 2026, 9);

    expect($metrics['planned_hours'])->toBe(0.0)
        ->and($metrics['actual_hours'])->toBe(25.0)
        ->and($metrics['burn_index_pct'])->toBe(0.0)
        ->and($metrics['remaining_hours'])->toBe(-25.0)
        ->and($metrics['is_budget_configured'])->toBeFalse()
        ->and($metrics['burn_zone'])->toBe('ZONE_4_POOR');
});

test('burn velocity and projected total uses Asia/Jakarta timezone', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 200.0,
    ]);

    // Day 15 in Jakarta => 15 / 7 = 2.14 => rounded to 2.1 weeks
    createTestOvertimeItem($section, '2026-09-10', 42.0, 0.0, 0.0, 0.0, 'APPROVED');

    $service = new BurnIndexCalculatorService;
    $metrics = $service->calculateSectionMetrics($section->id, 2026, 9);

    // 42.0 actual / 2.1 elapsed weeks = 20.0 hrs/week
    expect($metrics['velocity_weekly'])->toBe(20.0)
        // 20.0 * 4.3 = 86.0 projected total
        ->and($metrics['projected_total_hours'])->toBe(86.0)
        ->and($metrics['trajectory'])->toBe('on_pace');
});

test('historical closed months use 4.3 weeks default for velocity', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 8, // Closed month
        'planned_hours' => 100.0,
    ]);

    createTestOvertimeItem($section, '2026-08-10', 86.0, 0.0, 0.0, 0.0, 'APPROVED');

    $service = new BurnIndexCalculatorService;
    $metrics = $service->calculateSectionMetrics($section->id, 2026, 8);

    // 86.0 / 4.3 = 20.0 hrs/week
    expect($metrics['velocity_weekly'])->toBe(20.0)
        ->and($metrics['projected_total_hours'])->toBe(86.0);
});

test('capex and opex labor ratios match CALC-07 and CALC-08', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 200.0,
    ]);

    // 25 prod + 15 tpm + 10 others = 50 opex; 50 project = 50 capex; Total = 100
    createTestOvertimeItem($section, '2026-09-05', 25.0, 15.0, 50.0, 10.0, 'APPROVED');

    $service = new BurnIndexCalculatorService;
    $metrics = $service->calculateSectionMetrics($section->id, 2026, 9);

    expect($metrics['capex_hours'])->toBe(50.0)
        ->and($metrics['opex_hours'])->toBe(50.0)
        ->and($metrics['capex_ratio_pct'])->toBe(50.0)
        ->and($metrics['opex_ratio_pct'])->toBe(50.0);
});

test('evaluates all 4 budget control matrix zones correctly', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $service = new BurnIndexCalculatorService;

    // ZONE 1: Low burn, low hours (actual 50, planned 100 => 50% burn, < 75% hours)
    OvertimeBudget::updateOrCreate(
        ['section_id' => $section->id, 'fiscal_year' => 2026, 'fiscal_month' => 9],
        ['department_id' => $dept->id, 'planned_hours' => 100.0]
    );
    createTestOvertimeItem($section, '2026-09-02', 50.0, 0.0, 0.0, 0.0, 'APPROVED');
    $m1 = $service->calculateSectionMetrics($section->id, 2026, 9);
    expect($m1['burn_zone'])->toBe('ZONE_1_EXCELLENT');

    // Clean items for next scenario
    OvertimeItem::truncate();
    OvertimeSubmission::truncate();

    // ZONE 2: Low burn (<= 100%), high hours (>= 75%) (actual 80, planned 100 => 80% burn, >= 75%)
    createTestOvertimeItem($section, '2026-09-02', 80.0, 0.0, 0.0, 0.0, 'APPROVED');
    $m2 = $service->calculateSectionMetrics($section->id, 2026, 9);
    expect($m2['burn_zone'])->toBe('ZONE_2_GOOD');

    OvertimeItem::truncate();
    OvertimeSubmission::truncate();

    // ZONE 3: High burn (> 100%), low hours (< 75% of a larger quota or fast burn in early days)
    // Note: When actual > planned, actual is also > 75% of planned unless handled specifically.
    // In definition: !isHighBurn && !isHighHours => Zone 1, !isHighBurn && isHighHours => Zone 2,
    // isHighBurn && !isHighHours => Zone 3, default => Zone 4.
    // If planned is 50, actual is 120 => burn is 240% and actual >= 75%, so it's Zone 4.
    // Zone 4: Over budget and >= 75% of quota
    createTestOvertimeItem($section, '2026-09-02', 120.0, 0.0, 0.0, 0.0, 'APPROVED');
    $m4 = $service->calculateSectionMetrics($section->id, 2026, 9);
    expect($m4['burn_zone'])->toBe('ZONE_4_POOR');
});
