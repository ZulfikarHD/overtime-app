<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $now = Carbon::now('Asia/Jakarta');
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $now->toDateString()],
        [
            'day_type' => 'HKN',
            'is_holiday' => false,
            'description' => 'Normal Working Day',
        ]
    );

    $lastYear = $now->copy()->subYear();
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $lastYear->toDateString()],
        [
            'day_type' => 'HKN',
            'is_holiday' => false,
            'description' => 'Last Year Working Day',
        ]
    );
});

test('admin can view period comparison tab with kpis, charts, and best practice cards', function () {
    $now = Carbon::now('Asia/Jakarta');

    $dept = Department::create([
        'code' => 'DEPT_CMP_01',
        'name' => 'Assembly Stamping Benchmarking',
        'cost_center_code' => 'CC-CMP-01',
        'default_hourly_rate' => 50000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_CMP_01',
        'name' => 'Stamping Sub Line',
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create([
        'name' => 'Agus Comparison Admin',
        'email' => 'agus.comparison@isuzu.co.id',
        'password' => 'password',
        'npk' => 'EMP-CMP-001',
    ]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Slamet Comparison Worker',
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-CMP-SUB-001',
        'submission_date' => $now->toDateString(),
        'operational_date' => $now->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 5.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'total_hours' => 5.0,
        'hourly_rate_snapshot' => 50000.00,
        'total_cost_snapshot' => 250000.00,
        'status' => 'APPROVED',
        'lock_version' => 1,
    ]);

    $page = visit('/login')
        ->fill('email', 'agus.comparison@isuzu.co.id')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->click('[data-test="nav-analytics"]')
        ->assertPathIs('/analytics')
        ->click('[data-test="tab-comparison"]')
        ->assertPresent('[data-test="tab-comparison-content"]')
        ->assertPresent('[data-test="comparison-controls-card"]')
        ->assertPresent('[data-test="card-total-hours"]')
        ->assertPresent('[data-test="card-total-cost"]')
        ->assertPresent('[data-test="card-efficiency"]')
        ->assertPresent('[data-test="card-headcount"]')
        ->assertPresent('[data-test="period-comparison-bar-chart"]')
        ->assertPresent('[data-test="department-benchmark-chart"]')
        ->assertPresent('[data-test="best-practice-section"]')
        ->assertPresent('[data-test="performer-summary-cards"]')
        ->assertPresent('[data-test="strategic-best-practice-cards"]');

    $page->assertNoJavaScriptErrors();
});

test('manager can interact with comparison type switcher and period pickers', function () {
    $now = Carbon::now('Asia/Jakarta');

    $dept = Department::create([
        'code' => 'DEPT_CMP_MGR',
        'name' => 'Machining Line Comparison',
        'cost_center_code' => 'CC-CMP-MGR',
        'default_hourly_rate' => 55000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_CMP_CNC',
        'name' => 'CNC Milling Section',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Dewi Comparison Manager',
        'email' => 'dewi.comparison@isuzu.co.id',
        'password' => 'password',
        'npk' => 'EMP-CMP-002',
    ]);

    $page = visit('/login')
        ->fill('email', 'dewi.comparison@isuzu.co.id')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->click('[data-test="nav-analytics"]')
        ->assertPathIs('/analytics')
        ->click('[data-test="tab-comparison"]')
        ->assertPresent('[data-test="tab-comparison-content"]')
        ->select('[data-test="select-comparison-type"]', 'mom')
        ->click('[data-test="btn-refresh-comparison"]')
        ->assertPresent('[data-test="period-comparison-bar-chart"]')
        ->assertPresent('[data-test="department-benchmark-chart"]');

    $page->assertNoJavaScriptErrors();
});
