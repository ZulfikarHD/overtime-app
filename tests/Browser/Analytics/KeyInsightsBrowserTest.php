<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\MonthlyBurnSnapshot;
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
        'burn_warning_pct' => 85,
        'burn_danger_pct' => 100,
        'weekly_soft_limit_hours' => 14,
    ]);
});

test('manager can view key insights panel, anomaly chart, and action items table', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_INSIGHT_01',
        'name' => 'Assembly Plant Insights',
        'cost_center_code' => 'CC-INS-01',
        'default_hourly_rate' => 50000.00,
        'is_active' => true,
    ]);

    $sec = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC-INS-01',
        'name' => 'Main Line Section',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Farhan Insights Manager',
        'email' => 'farhan.insights@isuzu.co.id',
        'password' => 'password',
        'npk' => 'EMP-INS-001',
    ]);

    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
    ]);

    $now = Carbon::now('Asia/Jakarta');

    // Create an overrun snapshot to trigger critical risk indicator & high priority action item
    MonthlyBurnSnapshot::create([
        'fiscal_year' => $now->year,
        'fiscal_month' => $now->month,
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'budget_amount' => 10000000.0,
        'actual_spend' => 11500000.0,
        'burn_index_pct' => 115.0,
        'snapshot_date' => $now->toDateString(),
    ]);

    // Create overtime submission and item for 30-day anomaly baseline
    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-BRW-INS-001',
        'submission_date' => $now->toDateString(),
        'operational_date' => $now->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 12.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 12.0,
        'hourly_rate_snapshot' => 50000.0,
        'total_cost_snapshot' => 600000.0,
        'status' => 'APPROVED',
    ]);

    visit('/login')
        ->fill('email', 'farhan.insights@isuzu.co.id')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->click('[data-test="nav-analytics"]')
        ->assertPathIs('/analytics')
        ->click('[data-test="tab-insights"]')
        ->assertPresent('[data-test="tab-insights-content"]')
        ->assertPresent('[data-test="risk-indicators-panel"]')
        ->assertPresent('[data-test="anomaly-detection-chart"]')
        ->assertPresent('[data-test="stat-card-mean"]')
        ->assertPresent('[data-test="stat-card-std-dev"]')
        ->assertPresent('[data-test="stat-card-normal-range"]')
        ->assertPresent('[data-test="stat-card-anomalies"]')
        ->assertPresent('[data-test="management-action-table"]')
        ->assertPresent('[data-test="btn-export-action-plan"]')
        ->assertPresent('[data-test="action-status-badge"]');
});

test('user can open action item status modal and update status with resolution remarks', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_INSIGHT_02',
        'name' => 'Cabin Stamping Plant',
        'cost_center_code' => 'CC-INS-02',
        'default_hourly_rate' => 52000.00,
        'is_active' => true,
    ]);

    $sec = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC-INS-02',
        'name' => 'Stamping Line Alpha',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Dewi Action Manager',
        'email' => 'dewi.action@isuzu.co.id',
        'password' => 'password',
        'npk' => 'EMP-INS-002',
    ]);

    $now = Carbon::now('Asia/Jakarta');

    // Create risk snapshot to ensure action item exists
    MonthlyBurnSnapshot::create([
        'fiscal_year' => $now->year,
        'fiscal_month' => $now->month,
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'budget_amount' => 5000000.0,
        'actual_spend' => 6000000.0,
        'burn_index_pct' => 120.0,
        'snapshot_date' => $now->toDateString(),
    ]);

    $page = visit('/login')
        ->fill('email', 'dewi.action@isuzu.co.id')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->click('[data-test="nav-analytics"]')
        ->assertPathIs('/analytics')
        ->click('[data-test="tab-insights"]')
        ->assertPresent('[data-test="tab-insights-content"]')
        ->assertPresent('[data-test="action-item-row"]');

    $page->assertNoJavaScriptErrors();

    $page->script("document.querySelector('[data-test=\"action-status-badge\"]').click()");

    $page->assertPresent('[data-test="action-item-status-modal"]')
        ->click('@status-radio-in_progress')
        ->fill('[data-test="textarea-resolution-note"]', 'Investigasi alokasi shift dan re-evaluasi rencana produksi.')
        ->click('[data-test="btn-submit-status-update"]')
        ->assertMissing('[data-test="action-item-status-modal"]');
});
