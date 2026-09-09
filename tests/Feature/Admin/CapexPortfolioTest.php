<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-09 10:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('guest is redirected to login when accessing portfolio overview', function () {
    $this->get(route('admin.capex-projects.index', ['tab' => 'portfolio']))
        ->assertRedirect(route('login'));
});

test('department manager sees only their department projects in portfolio with summary header', function () {
    $deptA = Department::factory()->create(['name' => 'Assembly Department']);
    $deptB = Department::factory()->create(['name' => 'Body Department']);

    $managerA = User::factory()->manager($deptA->id)->create();

    $projectA1 = CapexProject::factory()->create([
        'department_id' => $deptA->id,
        'project_code' => 'CPX-2026-ASSY-001',
        'status' => 'ACTIVE',
        'allocated_labor_hours' => 200.0,
    ]);
    $projectA2 = CapexProject::factory()->create([
        'department_id' => $deptA->id,
        'project_code' => 'CPX-2026-ASSY-002',
        'status' => 'PLANNING',
        'allocated_labor_hours' => 100.0,
    ]);
    $projectB = CapexProject::factory()->create([
        'department_id' => $deptB->id,
        'project_code' => 'CPX-2026-BODY-001',
        'status' => 'ACTIVE',
    ]);

    $this->actingAs($managerA)
        ->get(route('admin.capex-projects.index', ['tab' => 'portfolio']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CapexProjects/Index')
            ->has('projects.data', 2)
            ->where('projects.total', 2)
            ->where('stats.total_active', 1)
            ->where('stats.department_name', 'Assembly Department')
            ->where('stats.total_allocated_hours', fn ($val) => (float) $val === 200.0)
        );
});

test('admin sees cross department portfolio with department filter and column data', function () {
    $deptA = Department::factory()->create(['code' => 'ASSY', 'name' => 'Assembly Dept']);
    $deptB = Department::factory()->create(['code' => 'WELD', 'name' => 'Welding Dept']);

    $admin = User::factory()->admin()->create();

    CapexProject::factory()->create([
        'department_id' => $deptA->id,
        'project_code' => 'CPX-2026-ASSY-101',
        'status' => 'ACTIVE',
    ]);
    CapexProject::factory()->create([
        'department_id' => $deptB->id,
        'project_code' => 'CPX-2026-WELD-201',
        'status' => 'ACTIVE',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.capex-projects.index', ['tab' => 'portfolio']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CapexProjects/Index')
            ->has('projects.data', 2)
            ->has('departments', 2)
        );

    // Admin filters by specific department
    $this->actingAs($admin)
        ->get(route('admin.capex-projects.index', [
            'tab' => 'portfolio',
            'department_id' => $deptA->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CapexProjects/Index')
            ->has('projects.data', 1)
            ->where('projects.data.0.project_code', 'CPX-2026-ASSY-101')
            ->where('stats.department_name', 'Assembly Dept')
        );
});

test('portfolio calculates burn index, milestone ratio, days remaining, and flags at-risk projects', function () {
    $dept = Department::factory()->create();
    $sec = Section::factory()->create(['department_id' => $dept->id]);
    $admin = User::factory()->admin()->create();

    $projectAtRisk = CapexProject::factory()->create([
        'department_id' => $dept->id,
        'project_code' => 'CPX-2026-RISK-001',
        'allocated_labor_hours' => 100.0,
        'physical_progress_pct' => 40.0, // Burn = 95%, Ratio = 95/40 = 2.38 > 1.2 => At Risk
        'status' => 'ACTIVE',
        'target_end_date' => '2026-09-25',
    ]);

    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec->id]);
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => '2026-09-05'],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );
    $sub = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-CPX-PORTFOLIO-01',
        'submission_date' => '2026-09-05',
        'operational_date' => '2026-09-05',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'APPROVED',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $sub->id,
        'employee_id' => $emp->id,
        'npk_snapshot' => $emp->npk,
        'capex_project_id' => $projectAtRisk->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 95.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.0,
        'total_cost_snapshot' => 3325000.0,
        'status' => 'APPROVED',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.capex-projects.index', ['tab' => 'portfolio']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CapexProjects/Index')
            ->where('projects.data.0.computed_burn_index_pct', fn ($val) => (float) $val === 95.0)
            ->where('projects.data.0.computed_milestone_burn_ratio', fn ($val) => (float) $val === 2.38)
            ->where('projects.data.0.is_at_risk', true)
            ->where('projects.data.0.days_remaining', 16)
            ->where('stats.at_risk_count', 1)
        );
});

test('portfolio filters correctly by status and date range', function () {
    $dept = Department::factory()->create();
    $admin = User::factory()->admin()->create();

    CapexProject::factory()->create([
        'department_id' => $dept->id,
        'project_code' => 'CPX-ACTIVE-OCT',
        'status' => 'ACTIVE',
        'target_end_date' => '2026-10-15',
    ]);
    CapexProject::factory()->create([
        'department_id' => $dept->id,
        'project_code' => 'CPX-PLANNING-NOV',
        'status' => 'PLANNING',
        'target_end_date' => '2026-11-20',
    ]);
    CapexProject::factory()->create([
        'department_id' => $dept->id,
        'project_code' => 'CPX-ACTIVE-DEC',
        'status' => 'ACTIVE',
        'target_end_date' => '2026-12-30',
    ]);

    // Filter by status ACTIVE
    $this->actingAs($admin)
        ->get(route('admin.capex-projects.index', ['status' => 'ACTIVE']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 2)
            ->where('projects.data.0.status', 'ACTIVE')
            ->where('projects.data.1.status', 'ACTIVE')
        );

    // Filter by date range (October only)
    $this->actingAs($admin)
        ->get(route('admin.capex-projects.index', [
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.project_code', 'CPX-ACTIVE-OCT')
        );
});

test('portfolio sorts correctly by various columns', function () {
    $dept = Department::factory()->create();
    $admin = User::factory()->admin()->create();

    CapexProject::factory()->create([
        'department_id' => $dept->id,
        'project_code' => 'CPX-B',
        'allocated_labor_hours' => 300.0,
        'target_end_date' => '2026-12-01',
    ]);
    CapexProject::factory()->create([
        'department_id' => $dept->id,
        'project_code' => 'CPX-A',
        'allocated_labor_hours' => 100.0,
        'target_end_date' => '2026-10-01',
    ]);

    // Sort by project_code asc
    $this->actingAs($admin)
        ->get(route('admin.capex-projects.index', ['sort_by' => 'project_code', 'sort_dir' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects.data.0.project_code', 'CPX-A')
            ->where('projects.data.1.project_code', 'CPX-B')
        );

    // Sort by allocated_labor_hours desc
    $this->actingAs($admin)
        ->get(route('admin.capex-projects.index', ['sort_by' => 'allocated_labor_hours', 'sort_dir' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects.data.0.project_code', 'CPX-B')
            ->where('projects.data.1.project_code', 'CPX-A')
        );

    // Sort by target_end_date asc
    $this->actingAs($admin)
        ->get(route('admin.capex-projects.index', ['sort_by' => 'target_end_date', 'sort_dir' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects.data.0.project_code', 'CPX-A')
            ->where('projects.data.1.project_code', 'CPX-B')
        );
});

test('legacy route /reports/capex-projects/portfolio redirects to hub tab portfolio preserving query', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get('/reports/capex-projects/portfolio?status=ACTIVE&date_from=2026-01-01');

    $response->assertRedirect(route('admin.capex-projects.index', [
        'status' => 'ACTIVE',
        'date_from' => '2026-01-01',
        'tab' => 'portfolio',
    ]));
});
