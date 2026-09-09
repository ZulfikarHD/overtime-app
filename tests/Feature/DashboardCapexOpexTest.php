<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function ensureCalendarDateForCapex(string $date = '2026-09-15'): void
{
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => Carbon::parse($date)->isWeekend() ? 'HLR' : 'HKN',
            'is_holiday' => false,
        ]);
    }
}

test('guest is redirected to login when accessing burn index capex-opex tab', function () {
    $this->get(route('dashboard.burn-index', ['tab' => 'capex-opex']))
        ->assertRedirect(route('login'));
});

test('legacy reports capex-opex route redirects to dashboard burn index capex-opex tab', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)
        ->get('/reports/capex-opex')
        ->assertRedirect('/dashboard/burn-index?tab=capex-opex');
});

test('operator is forbidden from accessing burn index capex-opex tab', function () {
    $operator = User::factory()->user()->create();

    $this->actingAs($operator)
        ->get(route('dashboard.burn-index', ['tab' => 'capex-opex']))
        ->assertForbidden();
});

test('manager sees capex opex metrics strictly scoped to their department', function () {
    ensureCalendarDateForCapex('2026-09-10');

    $deptA = Department::factory()->create(['name' => 'Assembly Dept', 'is_active' => true]);
    $deptB = Department::factory()->create(['name' => 'Machining Dept', 'is_active' => true]);

    $secA = Section::factory()->create(['department_id' => $deptA->id, 'name' => 'Trim Line', 'is_active' => true]);
    $secB = Section::factory()->create(['department_id' => $deptB->id, 'name' => 'CNC Milling', 'is_active' => true]);

    $empA = Employee::factory()->create(['department_id' => $deptA->id, 'section_id' => $secA->id]);
    $empB = Employee::factory()->create(['department_id' => $deptB->id, 'section_id' => $secB->id]);

    $submitter = User::factory()->create();

    $projA = CapexProject::create([
        'project_code' => 'CPX-2026-ASSY-001',
        'asset_code' => 'FA-ASSY-001',
        'name' => 'Assembly Line Automation',
        'department_id' => $deptA->id,
        'allocated_labor_hours' => 100.0,
        'allocated_labor_budget_idr' => 5000000.0,
        'physical_progress_pct' => 60.0,
        'status' => 'ACTIVE',
        'start_date' => '2026-09-01',
        'target_end_date' => '2026-09-30',
    ]);

    $projB = CapexProject::create([
        'project_code' => 'CPX-2026-MCH-001',
        'asset_code' => 'FA-MCH-001',
        'name' => 'CNC Coolant Overhaul',
        'department_id' => $deptB->id,
        'allocated_labor_hours' => 80.0,
        'allocated_labor_budget_idr' => 4000000.0,
        'physical_progress_pct' => 40.0,
        'status' => 'ACTIVE',
        'start_date' => '2026-09-01',
        'target_end_date' => '2026-09-30',
    ]);

    $subA = OvertimeSubmission::create([
        'submission_code' => 'OT-ASSY-001',
        'submission_date' => '2026-09-10',
        'operational_date' => '2026-09-10',
        'day_type' => 'HKN',
        'department_id' => $deptA->id,
        'section_id' => $secA->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subA->id,
        'employee_id' => $empA->id,
        'npk_snapshot' => $empA->npk,
        'capex_project_id' => $projA->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 40.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.0,
        'total_cost_snapshot' => 1400000.0,
        'status' => 'APPROVED',
    ]);

    $subB = OvertimeSubmission::create([
        'submission_code' => 'OT-MCH-001',
        'submission_date' => '2026-09-10',
        'operational_date' => '2026-09-10',
        'day_type' => 'HKN',
        'department_id' => $deptB->id,
        'section_id' => $secB->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subB->id,
        'employee_id' => $empB->id,
        'npk_snapshot' => $empB->npk,
        'capex_project_id' => $projB->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 25.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.0,
        'total_cost_snapshot' => 875000.0,
        'status' => 'APPROVED',
    ]);

    $managerA = User::factory()->manager($deptA->id)->create();

    $response = $this->actingAs($managerA)->get(route('dashboard.burn-index', [
        'tab' => 'capex-opex',
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/BurnIndex')
        ->where('current_tab', 'capex-opex')
        ->where('capex_opex.summary.capex_hours', fn ($val) => (float) $val === 40.0)
        ->where('capex_opex.summary.total_hours', fn ($val) => (float) $val === 40.0)
        ->where('capex_opex.summary.capex_ratio_pct', fn ($val) => (float) $val === 100.0)
        ->has('capex_opex.sections', 1)
        ->where('capex_opex.sections.0.section_id', $secA->id)
        ->has('capex_opex.projects', 1)
        ->where('capex_opex.projects.0.project_code', 'CPX-2026-ASSY-001')
    );
});

test('team leader sees capex opex metrics strictly scoped to their assigned section', function () {
    ensureCalendarDateForCapex('2026-09-12');

    $dept = Department::factory()->create(['name' => 'Powertrain', 'is_active' => true]);
    $sec1 = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Engine Line', 'is_active' => true]);
    $sec2 = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Transmission Line', 'is_active' => true]);

    $emp1 = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec1->id]);
    $emp2 = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec2->id]);

    $submitter = User::factory()->create();

    $proj = CapexProject::create([
        'project_code' => 'CPX-2026-PWR-001',
        'name' => 'Jig Alignment Upgrade',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 50.0,
        'allocated_labor_budget_idr' => 2000000.0,
        'physical_progress_pct' => 50.0,
        'status' => 'ACTIVE',
        'start_date' => '2026-09-01',
        'target_end_date' => '2026-09-30',
    ]);

    // Section 1 submission (approved)
    $sub1 = OvertimeSubmission::create([
        'submission_code' => 'OT-PWR-SEC1',
        'submission_date' => '2026-09-12',
        'operational_date' => '2026-09-12',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec1->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub1->id,
        'employee_id' => $emp1->id,
        'npk_snapshot' => $emp1->npk,
        'capex_project_id' => $proj->id,
        'hours_production' => 10.0,
        'hours_tpm' => 0.0,
        'hours_project' => 15.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 30000.0,
        'total_cost_snapshot' => 750000.0,
        'status' => 'APPROVED',
    ]);

    // Section 2 submission (approved)
    $sub2 = OvertimeSubmission::create([
        'submission_code' => 'OT-PWR-SEC2',
        'submission_date' => '2026-09-12',
        'operational_date' => '2026-09-12',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec2->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub2->id,
        'employee_id' => $emp2->id,
        'npk_snapshot' => $emp2->npk,
        'capex_project_id' => $proj->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 20.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 30000.0,
        'total_cost_snapshot' => 600000.0,
        'status' => 'APPROVED',
    ]);

    $teamLeader = User::factory()->teamLeader($sec1->id, $dept->id)->create();

    $response = $this->actingAs($teamLeader)->get(route('dashboard.burn-index', [
        'tab' => 'capex-opex',
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/BurnIndex')
        // Team Leader should see only Section 1 hours: 15h capex, 10h opex, 25h total
        ->where('capex_opex.summary.capex_hours', fn ($val) => (float) $val === 15.0)
        ->where('capex_opex.summary.opex_hours', fn ($val) => (float) $val === 10.0)
        ->where('capex_opex.summary.total_hours', fn ($val) => (float) $val === 25.0)
        ->where('capex_opex.summary.capex_ratio_pct', fn ($val) => (float) $val === 60.0)
        ->where('capex_opex.summary.opex_ratio_pct', fn ($val) => (float) $val === 40.0)
        ->has('capex_opex.sections', 1)
        ->where('capex_opex.sections.0.section_id', $sec1->id)
    );
});

test('admin can view capex opex metrics and select specific department', function () {
    ensureCalendarDateForCapex('2026-09-14');

    $dept1 = Department::factory()->create(['code' => 'DEPT_ADM_1', 'is_active' => true]);
    $dept2 = Department::factory()->create(['code' => 'DEPT_ADM_2', 'is_active' => true]);

    $sec1 = Section::factory()->create(['department_id' => $dept1->id, 'is_active' => true]);
    $sec2 = Section::factory()->create(['department_id' => $dept2->id, 'is_active' => true]);

    $emp2 = Employee::factory()->create(['department_id' => $dept2->id, 'section_id' => $sec2->id]);
    $submitter = User::factory()->create();

    $proj2 = CapexProject::create([
        'project_code' => 'CPX-DEPT2-01',
        'name' => 'Die Stamping Refit',
        'department_id' => $dept2->id,
        'allocated_labor_hours' => 120.0,
        'allocated_labor_budget_idr' => 6000000.0,
        'physical_progress_pct' => 75.0,
        'status' => 'ACTIVE',
        'start_date' => '2026-09-01',
        'target_end_date' => '2026-09-30',
    ]);

    $sub2 = OvertimeSubmission::create([
        'submission_code' => 'OT-DEPT2',
        'submission_date' => '2026-09-14',
        'operational_date' => '2026-09-14',
        'day_type' => 'HKN',
        'department_id' => $dept2->id,
        'section_id' => $sec2->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub2->id,
        'employee_id' => $emp2->id,
        'npk_snapshot' => $emp2->npk,
        'capex_project_id' => $proj2->id,
        'hours_production' => 20.0,
        'hours_tpm' => 10.0,
        'hours_project' => 30.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 32000.0,
        'total_cost_snapshot' => 1920000.0,
        'status' => 'APPROVED',
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('dashboard.burn-index', [
        'tab' => 'capex-opex',
        'year' => 2026,
        'month' => 9,
        'department_id' => $dept2->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/BurnIndex')
        ->where('selected_department.id', $dept2->id)
        ->where('capex_opex.summary.capex_hours', fn ($val) => (float) $val === 30.0)
        ->where('capex_opex.summary.opex_hours', fn ($val) => (float) $val === 30.0)
        ->where('capex_opex.summary.total_hours', fn ($val) => (float) $val === 60.0)
        ->where('capex_opex.summary.capex_ratio_pct', fn ($val) => (float) $val === 50.0)
        ->where('capex_opex.summary.opex_ratio_pct', fn ($val) => (float) $val === 50.0)
    );
});

test('capex and opex ratios are accurately calculated using CALC-07 from approved items', function () {
    ensureCalendarDateForCapex('2026-09-16');

    $dept = Department::factory()->create(['is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec->id]);
    $submitter = User::factory()->create();

    $proj = CapexProject::create([
        'project_code' => 'CPX-CALC-07',
        'name' => 'Welding Fixture Installation',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 200.0,
        'allocated_labor_budget_idr' => 8000000.0,
        'physical_progress_pct' => 25.0,
        'status' => 'ACTIVE',
        'start_date' => '2026-09-01',
        'target_end_date' => '2026-09-30',
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-CALC-SUB',
        'submission_date' => '2026-09-16',
        'operational_date' => '2026-09-16',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    // 25 hrs CapEx, 75 hrs OpEx (50 prod + 20 tpm + 5 others) -> Total: 100 hrs -> CapEx ratio: 25.0%, OpEx ratio: 75.0%
    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $proj->id,
        'hours_production' => 50.0,
        'hours_tpm' => 20.0,
        'hours_project' => 25.0,
        'hours_others' => 5.0,
        'hourly_rate_snapshot' => 40000.0,
        'total_cost_snapshot' => 4000000.0,
        'status' => 'APPROVED',
    ]);

    $manager = User::factory()->manager($dept->id)->create();

    $response = $this->actingAs($manager)->get(route('dashboard.burn-index', [
        'tab' => 'capex-opex',
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('capex_opex.summary.total_hours', fn ($val) => (float) $val === 100.0)
        ->where('capex_opex.summary.capex_hours', fn ($val) => (float) $val === 25.0)
        ->where('capex_opex.summary.opex_hours', fn ($val) => (float) $val === 75.0)
        ->where('capex_opex.summary.capex_ratio_pct', fn ($val) => (float) $val === 25.0)
        ->where('capex_opex.summary.opex_ratio_pct', fn ($val) => (float) $val === 75.0)
        ->where('capex_opex.summary.capex_cost_idr', fn ($val) => (float) $val === 1000000.0) // 25h * 40k
        ->where('capex_opex.summary.opex_cost_idr', fn ($val) => (float) $val === 3000000.0) // 75h * 40k
        ->where('capex_opex.summary.total_cost_idr', fn ($val) => (float) $val === 4000000.0)
    );
});

test('date range filtering works for month, ytd, and custom date range', function () {
    ensureCalendarDateForCapex('2026-08-15');
    ensureCalendarDateForCapex('2026-09-05');
    ensureCalendarDateForCapex('2026-09-25');

    $dept = Department::factory()->create(['is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec->id]);
    $submitter = User::factory()->create();

    $proj = CapexProject::create([
        'project_code' => 'CPX-RANGE-01',
        'name' => 'Press Automation',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 300.0,
        'allocated_labor_budget_idr' => 12000000.0,
        'physical_progress_pct' => 50.0,
        'status' => 'ACTIVE',
        'start_date' => '2026-01-01',
        'target_end_date' => '2026-12-31',
    ]);

    // August item: 30 hrs CapEx
    $subAug = OvertimeSubmission::create([
        'submission_code' => 'OT-AUG',
        'submission_date' => '2026-08-15',
        'operational_date' => '2026-08-15',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subAug->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $proj->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 30.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.0,
        'total_cost_snapshot' => 1050000.0,
        'status' => 'APPROVED',
    ]);

    // September 5 item: 20 hrs CapEx
    $subSep1 = OvertimeSubmission::create([
        'submission_code' => 'OT-SEP1',
        'submission_date' => '2026-09-05',
        'operational_date' => '2026-09-05',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subSep1->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $proj->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 20.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.0,
        'total_cost_snapshot' => 700000.0,
        'status' => 'APPROVED',
    ]);

    // September 25 item: 15 hrs CapEx
    $subSep2 = OvertimeSubmission::create([
        'submission_code' => 'OT-SEP2',
        'submission_date' => '2026-09-25',
        'operational_date' => '2026-09-25',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $subSep2->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $proj->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 15.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.0,
        'total_cost_snapshot' => 525000.0,
        'status' => 'APPROVED',
    ]);

    $manager = User::factory()->manager($dept->id)->create();

    // 1. Month filter (September 2026) -> should include Sep 5 + Sep 25 = 35 hrs
    $resMonth = $this->actingAs($manager)->get(route('dashboard.burn-index', [
        'tab' => 'capex-opex',
        'year' => 2026,
        'month' => 9,
        'range_type' => 'month',
    ]));

    $resMonth->assertOk();
    $resMonth->assertInertia(fn (Assert $page) => $page
        ->where('capex_opex.summary.capex_hours', fn ($val) => (float) $val === 35.0)
        ->where('capex_opex.summary.range_type', 'month')
    );

    // 2. YTD filter (Jan 2026 through Sep 2026) -> should include Aug + Sep = 65 hrs
    $resYtd = $this->actingAs($manager)->get(route('dashboard.burn-index', [
        'tab' => 'capex-opex',
        'year' => 2026,
        'month' => 9,
        'range_type' => 'ytd',
    ]));

    $resYtd->assertOk();
    $resYtd->assertInertia(fn (Assert $page) => $page
        ->where('capex_opex.summary.capex_hours', fn ($val) => (float) $val === 65.0)
        ->where('capex_opex.summary.range_type', 'ytd')
    );

    // 3. Custom filter (2026-09-01 to 2026-09-10) -> should only include Sep 5 = 20 hrs
    $resCustom = $this->actingAs($manager)->get(route('dashboard.burn-index', [
        'tab' => 'capex-opex',
        'year' => 2026,
        'month' => 9,
        'range_type' => 'custom',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-10',
    ]));

    $resCustom->assertOk();
    $resCustom->assertInertia(fn (Assert $page) => $page
        ->where('capex_opex.summary.capex_hours', fn ($val) => (float) $val === 20.0)
        ->where('capex_opex.summary.range_type', 'custom')
        ->where('capex_opex.summary.start_date', '2026-09-01')
        ->where('capex_opex.summary.end_date', '2026-09-10')
    );
});

test('capex project variance is accurately computed against allocated labor hours', function () {
    ensureCalendarDateForCapex('2026-09-18');

    $dept = Department::factory()->create(['is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec->id]);
    $submitter = User::factory()->create();

    // Project 1: Allocated 150h, Logged 120h -> Variance: -30.0h (under budget)
    $projUnder = CapexProject::create([
        'project_code' => 'CPX-VAR-UNDER',
        'name' => 'Stamping Robot Setup',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 150.0,
        'allocated_labor_budget_idr' => 6000000.0,
        'physical_progress_pct' => 80.0,
        'status' => 'ACTIVE',
        'start_date' => '2026-09-01',
        'target_end_date' => '2026-09-30',
    ]);

    // Project 2: Allocated 100h, Logged 115h -> Variance: +15.0h (over budget)
    $projOver = CapexProject::create([
        'project_code' => 'CPX-VAR-OVER',
        'name' => 'Conveyor Line Extension',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 100.0,
        'allocated_labor_budget_idr' => 4000000.0,
        'physical_progress_pct' => 95.0,
        'status' => 'ACTIVE',
        'start_date' => '2026-09-01',
        'target_end_date' => '2026-09-30',
    ]);

    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-VAR-SUB',
        'submission_date' => '2026-09-18',
        'operational_date' => '2026-09-18',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $submitter->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $projUnder->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 120.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.0,
        'total_cost_snapshot' => 4200000.0,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $projOver->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 115.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.0,
        'total_cost_snapshot' => 4025000.0,
        'status' => 'APPROVED',
    ]);

    $manager = User::factory()->manager($dept->id)->create();

    $response = $this->actingAs($manager)->get(route('dashboard.burn-index', [
        'tab' => 'capex-opex',
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('capex_opex.projects.0.project_code', 'CPX-VAR-OVER')
        ->where('capex_opex.projects.0.variance_hours', fn ($val) => (float) $val === 15.0)
        ->where('capex_opex.projects.0.cumulative_logged_hours', fn ($val) => (float) $val === 115.0)
        ->where('capex_opex.projects.0.allocated_labor_hours', fn ($val) => (float) $val === 100.0)
        ->where('capex_opex.projects.1.project_code', 'CPX-VAR-UNDER')
        ->where('capex_opex.projects.1.variance_hours', fn ($val) => (float) $val === -30.0)
        ->where('capex_opex.projects.1.cumulative_logged_hours', fn ($val) => (float) $val === 120.0)
        ->where('capex_opex.projects.1.allocated_labor_hours', fn ($val) => (float) $val === 150.0)
    );
});

test('empty state values returned when period has no approved capex items', function () {
    $dept = Department::factory()->create(['is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);

    $manager = User::factory()->manager($dept->id)->create();

    $response = $this->actingAs($manager)->get(route('dashboard.burn-index', [
        'tab' => 'capex-opex',
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('capex_opex.summary.total_hours', fn ($val) => (float) $val === 0.0)
        ->where('capex_opex.summary.capex_hours', fn ($val) => (float) $val === 0.0)
        ->where('capex_opex.summary.opex_hours', fn ($val) => (float) $val === 0.0)
        ->where('capex_opex.summary.capex_ratio_pct', fn ($val) => (float) $val === 0.0)
        ->where('capex_opex.summary.opex_ratio_pct', fn ($val) => (float) $val === 0.0)
        ->where('capex_opex.summary.capex_cost_idr', fn ($val) => (float) $val === 0.0)
        ->where('capex_opex.summary.opex_cost_idr', fn ($val) => (float) $val === 0.0)
        ->where('capex_opex.summary.total_cost_idr', fn ($val) => (float) $val === 0.0)
    );
});
