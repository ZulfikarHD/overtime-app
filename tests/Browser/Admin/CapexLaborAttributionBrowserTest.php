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

test('admin can navigate to capex attribution tab, view project groups, subtotals, grand totals, and interact with filters', function () {
    $now = Carbon::now('Asia/Jakarta');
    $year = (int) $now->format('Y');

    $dept = Department::create([
        'code' => 'DEPT_ROBOT',
        'name' => 'Robotics & Automation',
        'cost_center_code' => 'CC-RBT-777',
        'default_hourly_rate' => 45000.00,
        'is_active' => true,
    ]);

    $sec = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC-RBT-01',
        'name' => 'Robotic Welding Line',
        'is_active' => true,
    ]);

    $emp1 = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'npk' => 'TECH-RBT-01',
        'full_name' => 'Bambang Sudarsono',
    ]);

    $emp2 = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'npk' => 'TECH-RBT-02',
        'full_name' => 'Dwi Cahyono',
    ]);

    $projectA = CapexProject::create([
        'project_code' => "CPX-{$year}-RBT-001",
        'asset_code' => 'AST-WELD-99',
        'name' => 'Pemasangan Sel Robot Welding 2',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 300.0,
        'allocated_labor_budget_idr' => 25000000.0,
        'physical_progress_pct' => 50.0,
        'status' => 'ACTIVE',
        'start_date' => Carbon::create($year, 9, 1)->toDateString(),
        'target_end_date' => Carbon::create($year, 11, 30)->toDateString(),
    ]);

    $calendarDate = Carbon::create($year, 9, 6)->toDateString();
    if (! OperationalCalendar::where('calendar_date', 'like', "{$calendarDate}%")->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $calendarDate,
            'day_type' => 'HKN',
            'is_holiday' => false,
        ]);
    }

    $admin = User::factory()->admin()->create([
        'name' => 'Financial Audit Admin',
        'email' => 'audit.admin@factory.com',
        'password' => 'password',
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-RBT-2026-01',
        'submission_date' => $calendarDate,
        'operational_date' => $calendarDate,
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'APPROVED',
    ]);

    // Item 1: 5.0 hours
    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'capex_project_id' => $projectA->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 5.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 45000.0,
        'total_cost_snapshot' => 225000.0,
        'status' => 'APPROVED',
        'reviewed_by_user_id' => $admin->id,
        'reviewed_at' => Carbon::create($year, 9, 7, 10, 0, 0)->toDateTimeString(),
    ]);

    // Item 2: 7.0 hours
    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $emp2->id,
        'npk_snapshot' => $emp2->npk,
        'capex_project_id' => $projectA->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 7.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 45000.0,
        'total_cost_snapshot' => 315000.0,
        'status' => 'APPROVED',
        'reviewed_by_user_id' => $admin->id,
        'reviewed_at' => Carbon::create($year, 9, 7, 10, 0, 0)->toDateTimeString(),
    ]);

    visit('/login')
        ->fill('email', 'audit.admin@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard');

    // 1. Visit unified CapEx Project Hub and switch to Tab 2
    visit('/admin/capex-projects')
        ->assertPathIs('/admin/capex-projects')
        ->assertVisible('[data-test="capex-hub-tabs"]')
        ->click('[data-test="tab-attribution"]')
        ->assertVisible('[data-test="capex-attribution-container"]')
        ->assertVisible('[data-test="attribution-toolbar"]')
        ->assertVisible('[data-test="btn-export-attribution-excel"]')
        // Verify Grand Total Card
        ->assertVisible('[data-test="attribution-grand-total-card"]')
        ->assertSee('12,0')
        ->assertSee('Rp 540.000')
        // Verify Project Group & Items
        ->assertVisible('[data-test="project-group-card"]')
        ->assertSee("CPX-{$year}-RBT-001")
        ->assertSee('AST-WELD-99')
        ->assertSee('Bambang Sudarsono')
        ->assertSee('Dwi Cahyono')
        ->assertSee('OT-SUB-RBT-2026-01')
        ->assertVisible('[data-test="project-subtotal-hours"]')
        ->assertVisible('[data-test="project-subtotal-cost"]')
        // Filter by search input
        ->fill('[data-test="input-attribution-search"]', 'Bambang')
        ->waitForText('Bambang Sudarsono')
        ->assertDontSee('Dwi Cahyono')
        // Reset Filter
        ->assertVisible('[data-test="btn-attribution-reset"]')
        ->click('[data-test="btn-attribution-reset"]')
        ->waitForText('Dwi Cahyono')
        ->assertSee('Bambang Sudarsono');

    // 2. Direct Visit with tab=attribution query parameter
    visit('/admin/capex-projects?tab=attribution')
        ->assertPathIs('/admin/capex-projects')
        ->assertVisible('[data-test="capex-attribution-container"]')
        ->assertSee("CPX-{$year}-RBT-001");

    // 3. Test Legacy Route Redirection
    visit('/reports/capex-labor')
        ->assertPathIs('/admin/capex-projects')
        ->assertVisible('[data-test="capex-attribution-container"]');
});
