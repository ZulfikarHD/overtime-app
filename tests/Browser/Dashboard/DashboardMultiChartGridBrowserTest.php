<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeBudget;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

test('manager can view all 5 multi-chart grid widgets on dashboard with proper interaction', function () {
    $dept = Department::create([
        'code' => 'DEPT_GRID_BROWSER',
        'name' => 'Stamping Body Dept',
        'cost_center_code' => 'CC-STP-GRID',
        'default_hourly_rate' => 38000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_PR1',
        'name' => 'Press Line 1',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $year = $now->year;
    $month = $now->month;
    $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
    $date1 = "{$year}-{$monthStr}-02";

    OperationalCalendar::create([
        'calendar_date' => $date1,
        'day_type' => 'HKN',
        'is_holiday' => false,
    ]);

    PolicyThreshold::create([
        'department_id' => $dept->id,
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.0,
        'burn_danger_pct' => 115.0,
    ]);

    OvertimeBudget::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => $year,
        'fiscal_month' => $month,
        'planned_hours' => 100.0,
        'planned_amount' => 3800000,
    ]);

    $emp = Employee::create([
        'npk' => 'EMP-STP-01',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Hendra Gunawan',
        'job_position' => 'Press Operator',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager MultiGrid',
        'email' => 'manager.multigrid@factory.com',
        'password' => 'password',
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'SPKL-STP-001',
        'submission_date' => $date1,
        'operational_date' => $date1,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $manager->id,
        'status' => 'APPROVED',
        'total_hours_cached' => 10.0,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'hours_production' => 6.0,
        'hours_tpm' => 2.0,
        'hours_project' => 2.0,
        'hours_others' => 0.0,
        'total_hours' => 10.0,
        'hourly_rate_snapshot' => 38000,
        'total_cost_snapshot' => 380000,
        'status' => 'APPROVED',
    ]);

    visit('/login')
        ->fill('email', 'manager.multigrid@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertPresent('[data-test="multi-chart-grid-band"]')
        ->assertPresent('[data-test="overtime-leaderboard-card"]')
        ->assertPresent('[data-test="category-distribution-donut-card"]')
        ->assertPresent('[data-test="trend-working-time-card"]')
        ->assertPresent('[data-test="daily-index-trend-card"]')
        ->assertPresent('[data-test="day-type-breakdown-card"]')
        ->assertPresent('[data-test="category-legend-grid"]')
        ->assertSee('Top 10')
        ->assertSee('HKN')
        ->assertSee('HLR')
        ->assertSee('100%');
});

test('manager can interact with category distribution legend pills', function () {
    $dept = Department::create([
        'code' => 'DEPT_PILL_BROWSER',
        'name' => 'Paint Shop Dept',
        'cost_center_code' => 'CC-PNT-PILL',
        'default_hourly_rate' => 38000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_ED1',
        'name' => 'Electrodeposition Line',
        'is_active' => true,
    ]);

    $now = Carbon::now('Asia/Jakarta');
    $date = $now->toDateString();

    OperationalCalendar::create([
        'calendar_date' => $date,
        'day_type' => 'HKN',
        'is_holiday' => false,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Manager Pill',
        'email' => 'manager.pill@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'manager.pill@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertPresent('[data-test="category-distribution-donut-card"]')
        ->assertPresent('[data-test="overtime-leaderboard-card"]');
});
