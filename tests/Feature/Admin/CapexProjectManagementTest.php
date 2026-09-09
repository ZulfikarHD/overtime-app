<?php

use App\Models\CapexProject;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest is redirected to login when accessing capex projects', function () {
    $this->get(route('admin.capex-projects.index'))->assertRedirect(route('login'));
});

test('operator and team leader are forbidden from capex projects master data', function () {
    $teamLeader = User::factory()->teamLeader()->create();
    $operator = User::factory()->user()->create();

    $this->actingAs($teamLeader)->get(route('admin.capex-projects.index'))->assertForbidden();
    $this->actingAs($operator)->get(route('admin.capex-projects.index'))->assertForbidden();
});

test('admin and manager can access capex projects hub', function () {
    $dept = Department::factory()->create();
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->manager($dept->id)->create();

    $project = CapexProject::factory()->create(['department_id' => $dept->id]);

    $this->actingAs($admin)->get(route('admin.capex-projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CapexProjects/Index')
            ->has('projects.data')
            ->has('stats')
            ->has('departments')
        );

    $this->actingAs($manager)->get(route('admin.capex-projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CapexProjects/Index')
        );
});

test('manager is scoped strictly to their department projects', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $managerA = User::factory()->manager($deptA->id)->create();

    $projA = CapexProject::factory()->create([
        'department_id' => $deptA->id,
        'project_code' => 'CPX-2026-DEPTA-001',
    ]);
    $projB = CapexProject::factory()->create([
        'department_id' => $deptB->id,
        'project_code' => 'CPX-2026-DEPTB-001',
    ]);

    $this->actingAs($managerA)->get(route('admin.capex-projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects.data.0.project_code', $projA->project_code)
            ->where('projects.total', 1)
        );
});

test('admin can create a capex project with valid parameters', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();

    $payload = [
        'project_code' => 'CPX-2026-ASSY-001',
        'asset_code' => 'AST-9901',
        'name' => 'Instalasi Robot Welding Line 2',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 450.0,
        'allocated_labor_budget_idr' => 31500000.0,
        'start_date' => '2026-10-01',
        'target_end_date' => '2026-12-31',
        'status' => 'PLANNING',
    ];

    $response = $this->actingAs($admin)->post(route('admin.capex-projects.store'), $payload);

    $response->assertRedirect(route('admin.capex-projects.index', ['tab' => 'portfolio']));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('capex_projects', [
        'project_code' => 'CPX-2026-ASSY-001',
        'asset_code' => 'AST-9901',
        'name' => 'Instalasi Robot Welding Line 2',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 450.00,
        'allocated_labor_budget_idr' => 31500000.00,
        'status' => 'PLANNING',
    ]);
});

test('creation fails if project_code does not follow standard regex format', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();

    $invalidCodes = ['invalid_code', 'CPX-123', 'PROJ-2026-001', 'CPX-2026'];

    foreach ($invalidCodes as $code) {
        $response = $this->actingAs($admin)->post(route('admin.capex-projects.store'), [
            'project_code' => $code,
            'name' => 'Test Project',
            'department_id' => $dept->id,
            'allocated_labor_hours' => 100.0,
            'allocated_labor_budget_idr' => 10000000.0,
            'start_date' => '2026-10-01',
            'target_end_date' => '2026-10-31',
        ]);

        $response->assertSessionHasErrors('project_code');
    }
});

test('creation fails if target_end_date is before start_date', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.capex-projects.store'), [
        'project_code' => 'CPX-2026-ASSY-002',
        'name' => 'Schedule Error Project',
        'department_id' => $dept->id,
        'allocated_labor_hours' => 100.0,
        'allocated_labor_budget_idr' => 10000000.0,
        'start_date' => '2026-11-01',
        'target_end_date' => '2026-10-01',
    ]);

    $response->assertSessionHasErrors('target_end_date');
});

test('project_code is immutable after creation and cannot be updated', function () {
    $admin = User::factory()->admin()->create();
    $project = CapexProject::factory()->create([
        'project_code' => 'CPX-2026-ORIGINAL-001',
    ]);

    // Web form submit redirects with error
    $response = $this->actingAs($admin)->put(route('admin.capex-projects.update', $project), [
        'project_code' => 'CPX-2026-TAMPERED-999',
        'name' => 'Updated Name',
        'department_id' => $project->department_id,
        'allocated_labor_hours' => 500.0,
        'allocated_labor_budget_idr' => 35000000.0,
        'start_date' => $project->start_date->toDateString(),
        'target_end_date' => $project->target_end_date->toDateString(),
    ]);

    $response->assertSessionHasErrors('project_code');

    // JSON API request returns 422 Unprocessable Entity
    $jsonResponse = $this->actingAs($admin)->putJson(route('admin.capex-projects.update', $project), [
        'project_code' => 'CPX-2026-TAMPERED-999',
        'name' => 'Updated Name',
        'department_id' => $project->department_id,
        'allocated_labor_hours' => 500.0,
        'allocated_labor_budget_idr' => 35000000.0,
        'start_date' => $project->start_date->toDateString(),
        'target_end_date' => $project->target_end_date->toDateString(),
    ]);

    $jsonResponse->assertStatus(422)->assertJsonValidationErrors('project_code');
    expect($project->fresh()->project_code)->toBe('CPX-2026-ORIGINAL-001');
});

test('admin can update project parameters excluding project_code', function () {
    $admin = User::factory()->admin()->create();
    $project = CapexProject::factory()->create([
        'project_code' => 'CPX-2026-WELD-001',
        'name' => 'Old Name',
        'allocated_labor_hours' => 200.0,
    ]);

    $response = $this->actingAs($admin)->put(route('admin.capex-projects.update', $project), [
        'name' => 'New Name Automation',
        'asset_code' => 'AST-NEW-77',
        'department_id' => $project->department_id,
        'allocated_labor_hours' => 350.0,
        'allocated_labor_budget_idr' => 24500000.0,
        'start_date' => '2026-09-01',
        'target_end_date' => '2026-12-01',
    ]);

    $response->assertSessionHas('success');

    $fresh = $project->fresh();
    expect($fresh->name)->toBe('New Name Automation')
        ->and($fresh->asset_code)->toBe('AST-NEW-77')
        ->and((float) $fresh->allocated_labor_hours)->toBe(350.00)
        ->and($fresh->project_code)->toBe('CPX-2026-WELD-001');
});

test('lifecycle status transitions follow strict state machine rules', function () {
    $admin = User::factory()->admin()->create();

    // 1. PLANNING -> ACTIVE
    $project = CapexProject::factory()->planning()->create();
    $this->actingAs($admin)->patch(route('admin.capex-projects.status.update', $project), [
        'status' => 'ACTIVE',
    ])->assertSessionHas('success');
    expect($project->fresh()->status)->toBe('ACTIVE');

    // 2. ACTIVE -> ON_HOLD
    $this->actingAs($admin)->patch(route('admin.capex-projects.status.update', $project), [
        'status' => 'ON_HOLD',
    ])->assertSessionHas('success');
    expect($project->fresh()->status)->toBe('ON_HOLD');

    // 3. ON_HOLD -> ACTIVE
    $this->actingAs($admin)->patch(route('admin.capex-projects.status.update', $project), [
        'status' => 'ACTIVE',
    ])->assertSessionHas('success');
    expect($project->fresh()->status)->toBe('ACTIVE');

    // 4. ACTIVE -> COMPLETED
    $this->actingAs($admin)->patch(route('admin.capex-projects.status.update', $project), [
        'status' => 'COMPLETED',
    ])->assertSessionHas('success');
    expect($project->fresh()->status)->toBe('COMPLETED');

    // 5. COMPLETED -> CLOSED
    $this->actingAs($admin)->patch(route('admin.capex-projects.status.update', $project), [
        'status' => 'CLOSED',
    ])->assertSessionHas('success');
    expect($project->fresh()->status)->toBe('CLOSED');

    // 6. CLOSED cannot transition to any other status
    $invalidResponse = $this->actingAs($admin)->patch(route('admin.capex-projects.status.update', $project), [
        'status' => 'ACTIVE',
    ]);
    $invalidResponse->assertSessionHasErrors('status');
    expect($project->fresh()->status)->toBe('CLOSED');
});

test('illegal status transition from PLANNING to CLOSED fails', function () {
    $admin = User::factory()->admin()->create();
    $project = CapexProject::factory()->planning()->create();

    $response = $this->actingAs($admin)->patch(route('admin.capex-projects.status.update', $project), [
        'status' => 'CLOSED',
    ]);

    $response->assertSessionHasErrors('status');
    expect($project->fresh()->status)->toBe('PLANNING');
});

test('overtime submission rejects non-active or closed capex project under BR-08', function () {
    $dept = Department::factory()->create();
    $sec = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
    ]);
    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'hourly_rate' => 35000.0,
    ]);

    $closedProject = CapexProject::factory()->closed()->create(['department_id' => $dept->id]);
    $activeProject = CapexProject::factory()->active()->create(['department_id' => $dept->id]);

    $calendarDate = '2026-09-08';
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $calendarDate],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );

    // 1. Submit with closed project -> Fails BR-08
    $failResponse = $this->actingAs($teamLeader)->post(route('overtime.submissions.store'), [
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'operational_date' => $calendarDate,
        'shift' => '1',
        'items' => [
            [
                'employee_id' => $emp->id,
                'hours_production' => 0.0,
                'hours_tpm' => 0.0,
                'hours_project' => 2.5,
                'hours_others' => 0.0,
                'capex_project_id' => $closedProject->id,
                'task_description' => 'Fabrication test',
            ],
        ],
    ]);

    $failResponse->assertSessionHasErrors('items.0.capex_project_id');

    // 2. Submit with active project -> Succeeds
    $successResponse = $this->actingAs($teamLeader)->post(route('overtime.submissions.store'), [
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'operational_date' => $calendarDate,
        'shift' => '1',
        'items' => [
            [
                'employee_id' => $emp->id,
                'hours_production' => 0.0,
                'hours_tpm' => 0.0,
                'hours_project' => 2.5,
                'hours_others' => 0.0,
                'capex_project_id' => $activeProject->id,
                'task_description' => 'Fabrication test',
            ],
        ],
    ]);

    $successResponse->assertSessionHasNoErrors();
    $this->assertDatabaseHas('overtime_items', [
        'employee_id' => $emp->id,
        'capex_project_id' => $activeProject->id,
        'hours_project' => 2.5,
    ]);
});

test('admin can delete capex project only when no overtime items exist', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();
    $sec = Section::factory()->create(['department_id' => $dept->id]);
    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec->id]);

    OperationalCalendar::firstOrCreate(
        ['calendar_date' => '2026-09-08'],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );

    $projectDeletable = CapexProject::factory()->create(['department_id' => $dept->id]);
    $projectLocked = CapexProject::factory()->create(['department_id' => $dept->id]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-CPX-TEST-01',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
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
        'capex_project_id' => $projectLocked->id,
        'hours_production' => 0.0,
        'hours_tpm' => 0.0,
        'hours_project' => 4.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.0,
        'total_cost_snapshot' => 140000.0,
        'status' => 'APPROVED',
    ]);

    // Deleting project without overtime items succeeds
    $this->actingAs($admin)->delete(route('admin.capex-projects.destroy', $projectDeletable))
        ->assertRedirect(route('admin.capex-projects.index', ['tab' => 'portfolio']))
        ->assertSessionHas('success');
    $this->assertDatabaseMissing('capex_projects', ['id' => $projectDeletable->id]);

    // Deleting project with overtime items fails with validation error
    $response = $this->actingAs($admin)->delete(route('admin.capex-projects.destroy', $projectLocked));
    $response->assertSessionHasErrors('project');
    $this->assertDatabaseHas('capex_projects', ['id' => $projectLocked->id]);
});

test('legacy capex routes redirect to unified hub with appropriate tab', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/reports/capex-projects/portfolio')
        ->assertRedirect('/admin/capex-projects?tab=portfolio');

    $this->actingAs($admin)->get('/reports/capex-labor')
        ->assertRedirect('/admin/capex-projects?tab=attribution');
});

test('capex project show view renders cockpit with computed burn metrics', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();
    $sec = Section::factory()->create(['department_id' => $dept->id]);
    $emp = Employee::factory()->create(['department_id' => $dept->id, 'section_id' => $sec->id]);

    OperationalCalendar::firstOrCreate(
        ['calendar_date' => '2026-09-08'],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );

    $project = CapexProject::factory()->create([
        'department_id' => $dept->id,
        'allocated_labor_hours' => 100.0,
        'allocated_labor_budget_idr' => 5000000.0,
        'physical_progress_pct' => 50.0,
        'status' => 'ACTIVE',
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-SUB-SHOW-01',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
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
        'hours_project' => 60.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.0,
        'total_cost_snapshot' => 2100000.0,
        'status' => 'APPROVED',
    ]);

    $this->actingAs($admin)->get(route('admin.capex-projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CapexProjects/Show')
            ->where('project.id', $project->id)
            ->where('metrics.allocated_hours', 100)
            ->where('metrics.consumed_hours', 60)
            ->where('metrics.burn_index_pct', 60)
            ->where('metrics.milestone_burn_ratio', 1.2)
        );
});
