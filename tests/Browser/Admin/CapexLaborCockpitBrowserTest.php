<?php

use App\Models\CapexProject;
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
    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.00,
        'burn_danger_pct' => 115.00,
    ]);
});

test('admin can view capex labor cockpit, update physical progress in-place, and see milestone prompt', function () {
    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');

    $dept = Department::create([
        'code' => 'DEPT_CPX_COCKPIT',
        'name' => 'Assembly Stamping Division',
        'cost_center_code' => 'CC-CPX-888',
        'default_hourly_rate' => 40000.00,
        'is_active' => true,
    ]);

    $sec = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_TOOL',
        'name' => 'Tooling & Die Maintenance',
    ]);

    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'npk' => 'TECH-8899',
        'full_name' => 'Agus Priyono',
    ]);

    OperationalCalendar::firstOrCreate(
        ['calendar_date' => Carbon::create($year, 9, 5)->toDateString()],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );

    $project = CapexProject::create([
        'project_code' => "CPX-{$year}-TOOL-303",
        'asset_code' => 'AST-7744',
        'name' => 'Upgrading Mesin Press Tandem 500T',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 200.0,
        'allocated_labor_budget_idr' => 18000000.0,
        'physical_progress_pct' => 50.0,
        'status' => 'ACTIVE',
        'start_date' => Carbon::create($year, 9, 1)->toDateString(),
        'target_end_date' => Carbon::create($year, 11, 30)->toDateString(),
    ]);

    $admin = User::factory()->admin()->create([
        'name' => 'Cockpit Admin Test',
        'email' => 'admin.cockpit@factory.com',
        'password' => 'password',
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-COCKPIT-01',
        'submission_date' => Carbon::create($year, 9, 5)->toDateString(),
        'operational_date' => Carbon::create($year, 9, 5)->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $project->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 50.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 40000,
        'total_cost_snapshot' => 2000000,
        'status' => 'APPROVED',
    ]);

    visit('/login')
        ->fill('email', 'admin.cockpit@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard');

    visit("/admin/capex-projects/{$project->id}")
        ->assertPathIs("/admin/capex-projects/{$project->id}")
        ->assertSee("CPX-{$year}-TOOL-303")
        ->assertSee('Upgrading Mesin Press Tandem 500T')
        // Verify 4 KPI Macro Cards
        ->assertVisible('[data-test="capex-burn-index-panel"]')
        ->assertVisible('[data-test="kpi-burn-index-value"]')
        ->assertVisible('[data-test="kpi-physical-progress-value"]')
        // Verify Timeline Burndown Chart & Team Contribution Roster
        ->assertVisible('[data-test="capex-labor-timeline-chart-card"]')
        ->assertVisible('[data-test="capex-team-contribution-card"]')
        ->assertSee('TECH-8899')
        ->assertSee('Agus Priyono')
        // Verify In-Place Physical Progress Editor
        ->assertVisible('[data-test="inline-progress-editor-card"]')
        ->assertSee('50.0%')
        ->click('[data-test="btn-edit-progress"]')
        ->assertVisible('[data-test="input-progress-number"]')
        ->fill('[data-test="input-progress-number"]', '100')
        ->click('[data-test="btn-save-progress"]')
        // Verify in-place update reflects 100.0% and completion banner appears
        ->waitForText('100.0%')
        ->assertVisible('[data-test="completion-milestone-banner"]')
        // Click action button in milestone banner to open status transition modal
        ->click('[data-test="btn-transition-completed"]')
        ->assertVisible('[data-test="project-status-modal"]');
});
