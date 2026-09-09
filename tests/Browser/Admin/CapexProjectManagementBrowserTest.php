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

test('admin can navigate capex hub, create a new project via drawer, and transition status', function () {
    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');

    $dept = Department::create([
        'code' => 'DEPT_CPX_TEST',
        'name' => 'Manufacturing Engineering Dept',
        'cost_center_code' => 'CC-CPX-777',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    $existingProject = CapexProject::create([
        'project_code' => "CPX-{$year}-ENG-101",
        'asset_code' => 'AST-8821',
        'name' => 'Retrofit Stamping Press Sensor',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 300.0,
        'allocated_labor_budget_idr' => 21000000.0,
        'physical_progress_pct' => 25.0,
        'status' => 'PLANNING',
        'start_date' => Carbon::create($year, 9, 1)->toDateString(),
        'target_end_date' => Carbon::create($year, 12, 31)->toDateString(),
    ]);

    User::factory()->admin()->create([
        'name' => 'CapEx Admin Test',
        'email' => 'admin.capex@factory.com',
        'password' => 'password',
    ]);

    $newProjectCode = "CPX-{$year}-ASSY-202";

    visit('/login')
        ->fill('email', 'admin.capex@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        // Navigate via sidebar
        ->click('CapEx Projects')
        ->assertPathIs('/admin/capex-projects')
        ->assertSee('CapEx Project & Portfolio Management')
        ->assertSee("CPX-{$year}-ENG-101")
        ->assertSee('Retrofit Stamping Press Sensor')
        ->assertVisible('[data-test="capex-kpi-bar"]')
        // Open Create Drawer
        ->click('[data-test="btn-create-capex"]')
        ->assertVisible('[data-test="capex-project-drawer"]')
        ->fill('#project_code', $newProjectCode)
        ->fill('#name', 'Instalasi Line Robot Welding 3')
        ->fill('#asset_code', 'AST-9932')
        ->select('#department_id', (string) $dept->id)
        ->fill('#allocated_labor_hours', '400')
        ->fill('#allocated_labor_budget_idr', '28000000')
        ->fill('#start_date', Carbon::create($year, 10, 1)->format('Y-m-d'))
        ->fill('#target_end_date', Carbon::create($year, 12, 31)->format('Y-m-d'))
        ->click('[data-test="btn-submit-drawer"]')
        // Verify created project appears
        ->waitForText($newProjectCode)
        ->assertSee('Instalasi Line Robot Welding 3')
        // Open Status Transition Modal for the existing project
        ->click('[data-test="btn-status-'.$existingProject->id.'"]')
        ->assertVisible('[data-test="project-status-modal"]')
        ->assertSee("CPX-{$year}-ENG-101")
        ->click('[data-test="radio-status-active"]')
        ->click('[data-test="btn-confirm-status"]')
        // Verify status pill updated to ACTIVE
        ->waitForText('ACTIVE')
        // Navigate to Show Cockpit View
        ->click('[data-test="btn-detail-'.$existingProject->id.'"]')
        ->assertPathIs('/admin/capex-projects/'.$existingProject->id)
        ->assertVisible('[data-test="detail-kpi-cockpit"]')
        ->assertSee('Labor Hours')
        ->assertSee('Capitalized Cost')
        ->assertSee('CapEx Burn Index')
        // Back to Hub
        ->click('[data-test="link-back-to-hub"]')
        ->assertPathIs('/admin/capex-projects')
        // Switch to Tab 2
        ->click('[data-test="tab-attribution"]')
        ->assertVisible('[data-test="attribution-tab-content"]')
        ->assertSee('CapEx Financial Attribution Report');
});
