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

test('admin can view correlation tab with 3 kpis, scatter regression, erp quality guard, sweet spot chart, and matrix', function () {
    config(['services.erp.connected' => true]);

    $now = Carbon::now('Asia/Jakarta');

    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $now->toDateString()],
        [
            'day_type' => 'HKN',
            'is_holiday' => false,
            'description' => 'Normal Working Day',
        ]
    );

    $dept = Department::create([
        'code' => 'DEPT_CORR_ASY',
        'name' => 'Assembly Correlation Plant',
        'cost_center_code' => 'CC-CORR-ASY',
        'default_hourly_rate' => 50000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_CORR_TRIM',
        'name' => 'Trim Line Correlation',
        'is_active' => true,
    ]);

    PolicyThreshold::create([
        'department_id' => null,
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.0,
        'burn_danger_pct' => 115.0,
    ]);

    $admin = User::factory()->admin()->create([
        'name' => 'Budi Correlation Admin',
        'email' => 'budi.corr@factory.com',
        'password' => 'password',
        'npk' => 'EMP-CR01',
    ]);

    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'full_name' => 'Bambang Correlation Worker',
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-BRW-CORR-001',
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
        'hours_production' => 4.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 50000.00,
        'status' => 'APPROVED',
    ]);

    visit('/login')
        ->fill('email', 'budi.corr@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->click('[data-test="nav-analytics"]')
        ->assertPathIs('/analytics')
        ->click('[data-test="tab-correlation"]')
        ->assertPresent('[data-test="card-sweet-spot"]')
        ->assertPresent('[data-test="card-peak-efficiency"]')
        ->assertPresent('[data-test="card-warning-threshold"]')
        ->assertPresent('[data-test="overtime-production-scatter-card"]')
        ->assertPresent('[data-test="scatter-correlation-badge"]')
        ->assertPresent('[data-test="quality-scatter-guard-card"]')
        ->assertPresent('[data-test="quality-erp-status-badge"]')
        ->assertPresent('[data-test="optimal-level-zone-card"]')
        ->assertPresent('[data-test="current-avg-marker-badge"]')
        ->assertPresent('[data-test="correlation-matrix-table-card"]')
        ->assertPresent('[data-test="correlation-matrix-table"]');
});

test('manager scoped to department sees correlation analysis with fallback when erp disconnected', function () {
    config(['services.erp.connected' => false]);

    $now = Carbon::now('Asia/Jakarta');

    $dept = Department::create([
        'code' => 'DEPT_CORR_MGR',
        'name' => 'Quality Correlation Plant',
        'cost_center_code' => 'CC-CORR-MGR',
        'default_hourly_rate' => 48000.00,
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Rina Manager Corr',
        'email' => 'rina.corr@factory.com',
        'password' => 'password',
        'npk' => 'EMP-CR02',
    ]);

    visit('/login')
        ->fill('email', 'rina.corr@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->click('[data-test="nav-analytics"]')
        ->assertPathIs('/analytics')
        ->click('[data-test="tab-correlation"]')
        ->assertPresent('[data-test="erp-disconnected-banner"]')
        ->assertSee('N/A — Integrasi data produksi ERP belum terhubung')
        ->assertPresent('[data-test="quality-erp-placeholder"]');
});
