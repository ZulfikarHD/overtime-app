<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeItem;
use App\Models\OvertimePlan;
use App\Models\OvertimePlanItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// -------------------------------------------------------
// Authorization
// -------------------------------------------------------

test('guest is redirected to login for planning routes', function () {
    $this->get(route('overtime.planning.index'))->assertRedirect(route('login'));
    $this->get(route('overtime.planning.create'))->assertRedirect(route('login'));
    $this->post(route('overtime.planning.store'), [])->assertRedirect(route('login'));
});

test('operator (user role) is forbidden from planning routes', function () {
    $operator = User::factory()->user()->create();

    $this->actingAs($operator)->get(route('overtime.planning.index'))->assertForbidden();
    $this->actingAs($operator)->get(route('overtime.planning.create'))->assertForbidden();
    $this->actingAs($operator)->post(route('overtime.planning.store'), [])->assertForbidden();
});

// -------------------------------------------------------
// Index
// -------------------------------------------------------

test('admin can view planning index', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    OvertimePlan::factory()->create([
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'submitted_by_user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('overtime.planning.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('overtime/PlanningIndex')
            ->has('plans.data', 1),
        );
});

test('team_leader only sees plans for their own section', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $otherSection = Section::factory()->create(['department_id' => $dept->id]);

    $leader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $admin = User::factory()->admin()->create();

    // Plan for leader's section
    OvertimePlan::factory()->create([
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 1,
        'submitted_by_user_id' => $admin->id,
    ]);
    // Plan for other section (should NOT appear)
    OvertimePlan::factory()->create([
        'section_id' => $otherSection->id,
        'department_id' => $dept->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 1,
        'submitted_by_user_id' => $admin->id,
    ]);

    $this->actingAs($leader)
        ->get(route('overtime.planning.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('plans.data', 1),
        );
});

// -------------------------------------------------------
// Create page
// -------------------------------------------------------

test('admin can view planning create page', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();
    Section::factory()->create(['department_id' => $dept->id]);

    $this->actingAs($admin)
        ->get(route('overtime.planning.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('overtime/Planning')
            ->has('calendar_days')
            ->has('departments')
            ->has('actuals'),
        );
});

test('planning create includes weekly actuals from approved overtime items', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->create([
        'section_id' => $section->id,
        'department_id' => $dept->id,
    ]);

    $submission = OvertimeSubmission::factory()->create([
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'operational_date' => '2026-09-03', // week 1
        'day_type' => 'HKN',
        'submitted_by_user_id' => $admin->id,
    ]);

    OvertimeItem::factory()->approved($admin)->create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 2.0,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
    ]);

    $this->actingAs($admin)
        ->get(route('overtime.planning.create', [
            'section_id' => $section->id,
            'fiscal_year' => 2026,
            'fiscal_month' => 9,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('overtime/Planning')
            ->has('actuals')
            ->where("actuals.{$employee->id}.weeks.1.hours_production", 2)
            ->where("actuals.{$employee->id}.weeks.1.index_total", 3), // 2.0 × HKN 1.5
        );
});

// -------------------------------------------------------
// Store
// -------------------------------------------------------

test('admin can store a new planning document with items', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->create([
        'section_id' => $section->id,
        'department_id' => $dept->id,
    ]);

    $payload = [
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'notes' => 'Test planning',
        'items' => [
            [
                'employee_id' => $employee->id,
                'plan_date' => '2026-09-03',
                'hours_production' => 3.0,
                'hours_tpm' => 0.0,
                'hours_project' => 0.0,
                'hours_others' => 0.0,
            ],
        ],
    ];

    $response = $this->actingAs($admin)
        ->post(route('overtime.planning.store'), $payload);

    $response->assertRedirect();

    $this->assertDatabaseHas('overtime_plans', [
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'status' => 'DRAFT',
    ]);

    // Use the model (with `date` cast) instead of raw DB values to stay DB-driver-agnostic.
    $item = OvertimePlanItem::where('employee_id', $employee->id)->first();
    expect($item)->not->toBeNull();
    expect($item->plan_date->format('Y-m-d'))->toBe('2026-09-03');
    expect((float) $item->hours_production)->toBe(3.0);
});

test('storing a duplicate section/year/month upserts the plan', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->create([
        'section_id' => $section->id,
        'department_id' => $dept->id,
    ]);

    // First store
    $payload = [
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 10,
        'notes' => null,
        'items' => [
            [
                'employee_id' => $employee->id,
                'plan_date' => '2026-10-01',
                'hours_production' => 2.0,
                'hours_tpm' => 0.0,
                'hours_project' => 0.0,
                'hours_others' => 0.0,
            ],
        ],
    ];

    $this->actingAs($admin)->post(route('overtime.planning.store'), $payload);

    // Second store — same period should update, not duplicate
    $payload['items'][0]['hours_production'] = 4.0;
    $this->actingAs($admin)->post(route('overtime.planning.store'), $payload);

    $this->assertDatabaseCount('overtime_plans', 1);

    // Use the model to avoid driver-specific date format issues.
    $item = OvertimePlanItem::where('employee_id', $employee->id)
        ->whereDate('plan_date', '2026-10-01')
        ->first();
    expect($item)->not->toBeNull();
    expect((float) $item->hours_production)->toBe(4.0);
});

test('team_leader cannot store planning for a section outside their scope', function () {
    $dept = Department::factory()->create();
    $mySection = Section::factory()->create(['department_id' => $dept->id]);
    $otherSection = Section::factory()->create(['department_id' => $dept->id]);
    $leader = User::factory()->teamLeader($mySection->id, $dept->id)->create();
    $employee = Employee::factory()->create([
        'section_id' => $mySection->id,
        'department_id' => $dept->id,
    ]);

    $payload = [
        'section_id' => $otherSection->id, // not their section
        'department_id' => $dept->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'notes' => null,
        'items' => [
            [
                'employee_id' => $employee->id,
                'plan_date' => '2026-09-01',
                'hours_production' => 2.0,
                'hours_tpm' => 0.0,
                'hours_project' => 0.0,
                'hours_others' => 0.0,
            ],
        ],
    ];

    $this->actingAs($leader)
        ->post(route('overtime.planning.store'), $payload)
        ->assertForbidden();
});

// -------------------------------------------------------
// Publish / Destroy
// -------------------------------------------------------

test('admin can publish a draft plan', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $plan = OvertimePlan::factory()->create([
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'DRAFT',
    ]);

    $this->actingAs($admin)
        ->patch(route('overtime.planning.publish', $plan))
        ->assertRedirect();

    $this->assertDatabaseHas('overtime_plans', [
        'id' => $plan->id,
        'status' => 'PUBLISHED',
    ]);
});

test('published plan cannot be deleted', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $plan = OvertimePlan::factory()->published()->create([
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'submitted_by_user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('overtime.planning.destroy', $plan))
        ->assertStatus(422);

    $this->assertDatabaseHas('overtime_plans', ['id' => $plan->id]);
});

test('draft plan can be deleted by admin', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $plan = OvertimePlan::factory()->create([
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'DRAFT',
    ]);

    $this->actingAs($admin)
        ->delete(route('overtime.planning.destroy', $plan))
        ->assertRedirect(route('overtime.planning.index'));

    $this->assertDatabaseMissing('overtime_plans', ['id' => $plan->id]);
});
