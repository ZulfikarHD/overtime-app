<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\MlModel;
use App\Models\MlPrediction;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

function ensureCalendarDate(string $date): void
{
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => Carbon::parse($date)->isWeekend() ? 'HLR' : 'HKN',
            'is_holiday' => false,
        ]);
    }
}

test('guest is redirected to login when accessing section burndown endpoint', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $this->getJson(route('dashboard.burn-index.show', ['section' => $section->id]))
        ->assertUnauthorized();
});

test('operator is forbidden from accessing section burndown endpoint', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $operator = User::factory()->user()->create();

    $this->actingAs($operator)
        ->getJson(route('dashboard.burn-index.show', ['section' => $section->id]))
        ->assertForbidden();
});

test('manager cannot access section burndown of another department', function () {
    $deptA = Department::factory()->create(['name' => 'Dept A']);
    $deptB = Department::factory()->create(['name' => 'Dept B']);

    $sectionB = Section::factory()->create(['department_id' => $deptB->id]);
    $managerA = User::factory()->manager($deptA->id)->create();

    $this->actingAs($managerA)
        ->getJson(route('dashboard.burn-index.show', ['section' => $sectionB->id]))
        ->assertForbidden();
});

test('team leader can only access their assigned section burndown', function () {
    $dept = Department::factory()->create();
    $section1 = Section::factory()->create(['department_id' => $dept->id]);
    $section2 = Section::factory()->create(['department_id' => $dept->id]);

    $teamLeader = User::factory()->teamLeader($section1->id, $dept->id)->create();

    // Access own section -> OK
    $this->actingAs($teamLeader)
        ->getJson(route('dashboard.burn-index.show', ['section' => $section1->id]))
        ->assertOk();

    // Access other section in same department -> Forbidden
    $this->actingAs($teamLeader)
        ->getJson(route('dashboard.burn-index.show', ['section' => $section2->id]))
        ->assertForbidden();
});

test('admin can access burndown for any section', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson(route('dashboard.burn-index.show', ['section' => $section->id]))
        ->assertOk();
});

test('browser navigation to show route redirects to dashboard hub with section query parameter', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('dashboard.burn-index.show', [
        'section' => $section->id,
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertRedirect(route('dashboard.burn-index', [
        'tab' => 'sections',
        'section' => $section->id,
        'year' => 2026,
        'month' => 9,
        'department_id' => $dept->id,
    ]));
});

test('weekly burndown endpoint returns accurate planned, actual, HKN, and HLR calculations', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $manager = User::factory()->manager($dept->id)->create();

    $year = 2026;
    $month = 9;

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 200.0,
        'week1_planned_hours' => 40.0,
        'week2_planned_hours' => 40.0,
        'week3_planned_hours' => 40.0,
        'week4_planned_hours' => 40.0,
        'week5_planned_hours' => 40.0,
    ]);

    // Create approved items in Week 1 (Day 1..7)
    // 2026-09-02 (HKN) -> 10.0 hrs
    // 2026-09-05 (HLR) -> 6.0 hrs
    ensureCalendarDate('2026-09-02');
    ensureCalendarDate('2026-09-05');

    $employee = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $section->id]);

    $sub1 = OvertimeSubmission::create([
        'submission_code' => 'SPKL-TEST-W1-HKN',
        'submission_date' => '2026-09-02',
        'operational_date' => '2026-09-02',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 10.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub1->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 10.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 10.0,
        'status' => 'APPROVED',
        'lock_version' => 1,
    ]);

    $sub2 = OvertimeSubmission::create([
        'submission_code' => 'SPKL-TEST-W1-HLR',
        'submission_date' => '2026-09-05',
        'operational_date' => '2026-09-05',
        'day_type' => 'HLR',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 6.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub2->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 6.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 6.0,
        'status' => 'APPROVED',
        'lock_version' => 1,
    ]);

    // Create an optional ML Prediction
    $mlModel = MlModel::create([
        'model_key' => 'RF_BURNDOWN_V1',
        'model_type' => 'BURN_TRAJECTORY',
        'version' => 'v1.0.0',
        'algorithm_name' => 'RandomForestRegressor',
        'hyperparameters' => [],
        'metrics' => [],
        'trained_at' => Carbon::now('Asia/Jakarta'),
        'is_active' => true,
    ]);

    MlPrediction::create([
        'ml_model_id' => $mlModel->id,
        'target_type' => 'SECTION',
        'target_id' => $section->id,
        'prediction_horizon' => 'MONTH_END',
        'predicted_value' => 180.0,
        'confidence_interval_lower' => 170.0,
        'confidence_interval_upper' => 190.0,
        'risk_score' => 0.35,
        'risk_level' => 'MEDIUM',
        'fallback_used' => false,
        'created_at' => Carbon::now('Asia/Jakarta'),
    ]);

    $response = $this->actingAs($manager)->getJson(route('dashboard.burn-index.show', [
        'section' => $section->id,
        'year' => $year,
        'month' => $month,
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data)->toHaveKeys(['section', 'fiscal_year', 'fiscal_month', 'is_budget_configured', 'summary', 'weeks', 'ml_trajectory', 'ml_forecast', 'scatter_plot'])
        ->and($data['is_budget_configured'])->toBeTrue()
        ->and((float) $data['summary']['planned_hours'])->toEqual(200.0)
        ->and((float) $data['summary']['actual_hours'])->toEqual(16.0)
        ->and($data['weeks'])->toHaveCount(5);

    // Week 1 checks
    $w1 = $data['weeks'][0];
    expect($w1['week_number'])->toBe(1)
        ->and((float) $w1['planned_hours'])->toEqual(40.0)
        ->and((float) $w1['actual_hours'])->toEqual(16.0)
        ->and((float) $w1['hkn_hours'])->toEqual(10.0)
        ->and((float) $w1['hlr_hours'])->toEqual(6.0)
        ->and((float) $w1['cumulative_planned_hours'])->toEqual(40.0)
        ->and((float) $w1['cumulative_actual_hours'])->toEqual(16.0)
        ->and((float) $w1['burn_pct'])->toEqual(40.0)
        ->and((float) $w1['deviation_hours'])->toEqual(-24.0); // 16 - 40 = -24

    // ML Forecast checks
    expect($data['ml_forecast'])->not->toBeNull()
        ->and((float) $data['ml_forecast']['predicted_value'])->toEqual(180.0)
        ->and((float) $data['ml_forecast']['confidence_delta'])->toEqual(10.0)
        ->and($data['ml_trajectory'])->toHaveCount(5);

    // Scatter plot checks
    expect((float) $data['scatter_plot']['current_burn_pct'])->toEqual(8.0) // 16 / 200 * 100
        ->and((float) $data['scatter_plot']['cumulative_actual_hours'])->toEqual(16.0)
        ->and((float) $data['scatter_plot']['planned_budget_hours'])->toEqual(200.0)
        ->and((float) $data['scatter_plot']['threshold_hours_75_pct'])->toEqual(150.0);
});

test('unconfigured budget returns 0 planned hours without division by zero', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $manager = User::factory()->manager($dept->id)->create();

    $response = $this->actingAs($manager)->getJson(route('dashboard.burn-index.show', [
        'section' => $section->id,
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data['is_budget_configured'])->toBeFalse()
        ->and((float) $data['summary']['planned_hours'])->toEqual(0.0)
        ->and((float) $data['summary']['burn_index_pct'])->toEqual(0.0)
        ->and((float) $data['scatter_plot']['planned_budget_hours'])->toEqual(0.0);
});
