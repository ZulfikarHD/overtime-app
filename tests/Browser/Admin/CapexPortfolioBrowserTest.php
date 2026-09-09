<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\PolicyThreshold;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.00,
        'burn_danger_pct' => 115.00,
    ]);
});

test('admin can view capex portfolio table, inspect summary header, sort columns, and filter by status and date range', function () {
    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');

    $dept = Department::create([
        'code' => 'DEPT_PORTFOLIO',
        'name' => 'Tooling & Die Engineering',
        'cost_center_code' => 'CC-PORT-999',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $projectActive = CapexProject::create([
        'project_code' => "CPX-{$year}-TOOL-001",
        'asset_code' => 'AST-TL01',
        'name' => 'Pembuatan Tooling Jig Bumper 5',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 250.0,
        'allocated_labor_budget_idr' => 17500000.0,
        'physical_progress_pct' => 60.0,
        'status' => 'ACTIVE',
        'start_date' => Carbon::create($year, 8, 1)->toDateString(),
        'target_end_date' => Carbon::create($year, 10, 31)->toDateString(),
    ]);

    $projectPlanning = CapexProject::create([
        'project_code' => "CPX-{$year}-TOOL-002",
        'asset_code' => 'AST-TL02',
        'name' => 'Automasi Stamping Feeder 2',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 150.0,
        'allocated_labor_budget_idr' => 10500000.0,
        'physical_progress_pct' => 0.0,
        'status' => 'PLANNING',
        'start_date' => Carbon::create($year, 11, 1)->toDateString(),
        'target_end_date' => Carbon::create($year, 12, 31)->toDateString(),
    ]);

    User::factory()->admin()->create([
        'name' => 'Portfolio Admin Tester',
        'email' => 'portfolio.admin@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'portfolio.admin@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        // Visit CapEx Projects Hub
        ->click('CapEx Projects')
        ->assertPathIs('/admin/capex-projects')
        ->assertVisible('[data-test="department-summary-header"]')
        ->assertSee('Dept Total')
        ->assertVisible('[data-test="capex-projects-table"]')
        ->assertSee("CPX-{$year}-TOOL-001")
        ->assertSee("CPX-{$year}-TOOL-002")
        // Filter by ACTIVE status chip
        ->click('[data-test="filter-chip-active"]')
        ->waitForText("CPX-{$year}-TOOL-001")
        ->assertDontSee("CPX-{$year}-TOOL-002")
        // Filter by PLANNING status chip
        ->click('[data-test="filter-chip-planning"]')
        ->waitForText("CPX-{$year}-TOOL-002")
        ->assertDontSee("CPX-{$year}-TOOL-001")
        // Reset to ALL status
        ->click('[data-test="filter-chip-all"]')
        ->waitForText("CPX-{$year}-TOOL-001")
        ->assertSee("CPX-{$year}-TOOL-002")
        // Sort by project_code
        ->click('[data-test="sort-project-code"]')
        ->waitForText("CPX-{$year}-TOOL-001")
        // Filter by Date Range (October)
        ->fill('[data-test="input-date-from"]', Carbon::create($year, 10, 1)->format('Y-m-d'))
        ->fill('[data-test="input-date-to"]', Carbon::create($year, 10, 31)->format('Y-m-d'))
        ->waitForText("CPX-{$year}-TOOL-001")
        ->assertDontSee("CPX-{$year}-TOOL-002")
        // Click Reset Filters
        ->click('[data-test="btn-reset-filters"]')
        ->waitForText("CPX-{$year}-TOOL-002")
        ->assertSee("CPX-{$year}-TOOL-001");
});
