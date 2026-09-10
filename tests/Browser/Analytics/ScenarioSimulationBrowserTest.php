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

beforeEach(function () {
    $now = Carbon::now('Asia/Jakarta');
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $now->toDateString()],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 14,
    ]);
});

test('manager can interact with scenario simulation cockpit in browser', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_SCEN_BRW',
        'name' => 'Machining Plant Scenario',
        'cost_center_code' => 'CC-SCEN-01',
        'default_hourly_rate' => 55000.00,
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_SCEN_01',
        'name' => 'CNC Milling Line 1',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Bambang Scenario Mgr',
        'email' => 'bambang.scen@isuzu.co.id',
        'password' => 'password',
        'npk' => 'EMP-SCEN-01',
    ]);

    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'hourly_rate' => 55000,
    ]);

    $now = Carbon::now('Asia/Jakarta');

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-TEST-SCEN-001',
        'submission_date' => $now->toDateString(),
        'operational_date' => $now->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'employee_name_snapshot' => $emp->name,
        'hourly_rate_snapshot' => 55000,
        'start_time' => '17:00:00',
        'end_time' => '21:00:00',
        'break_minutes' => 0,
        'hours_production' => 4.00,
        'hours_tpm' => 0.00,
        'hours_project' => 0.00,
        'hours_others' => 0.00,
        'labor_cost' => 220000.00,
    ]);

    visit('/login')
        ->fill('email', 'bambang.scen@isuzu.co.id')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->click('[data-test="nav-analytics"]')
        ->click('[data-test="tab-scenario"]')
        ->assertPresent('[data-test="tab-scenario-content"]')
        ->assertPresent('[data-test="production-calculator-panel"]')
        ->assertPresent('[data-test="scenario-builder-panel"]')
        ->assertPresent('[data-test="scenario-comparison-chart"]')
        ->assertPresent('[data-test="btn-open-saved-drawer"]')
        ->assertPresent('[data-test="input-target-volume"]')
        ->assertPresent('[data-test="btn-run-calculator"]')
        ->assertPresent('[data-test="slider-overtime-change"]')
        ->click('[data-test="btn-preset-plus-25"]')
        ->assertPresent('[data-test="btn-run-scenario"]')
        ->click('[data-test="btn-run-scenario"]')
        ->assertPresent('[data-test="result-cost-impact"]')
        ->assertPresent('[data-test="result-volume-impact"]')
        ->assertPresent('[data-test="result-burn-index"]')
        ->assertPresent('[data-test="result-safety-risk"]')
        ->click('[data-test="btn-trigger-save-dialog"]')
        ->assertPresent('[data-test="save-scenario-dialog"]')
        ->click('[data-test="btn-open-saved-drawer"]')
        ->assertPresent('[data-test="saved-scenarios-drawer"]');
});
