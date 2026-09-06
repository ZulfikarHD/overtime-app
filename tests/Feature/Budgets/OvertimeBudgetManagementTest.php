<?php

use App\Models\Department;
use App\Models\OvertimeBudget;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

test('guest is redirected to login when accessing budget routes', function () {
    $this->get(route('budgets.planning'))->assertRedirect(route('login'));
    $this->post(route('budgets.store'), [])->assertRedirect(route('login'));
    $this->post(route('budgets.import.preview'), [])->assertRedirect(route('login'));
    $this->post(route('budgets.import'), [])->assertRedirect(route('login'));
    $this->get(route('budgets.template'))->assertRedirect(route('login'));
});

test('operator and team leader are forbidden from accessing budget routes', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $operator = User::factory()->user()->create();
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    foreach ([$operator, $teamLeader] as $unauthorizedUser) {
        $this->actingAs($unauthorizedUser)->get(route('budgets.planning'))->assertForbidden();
        $this->actingAs($unauthorizedUser)->post(route('budgets.store'), [])->assertForbidden();
        $this->actingAs($unauthorizedUser)->post(route('budgets.import.preview'), [])->assertForbidden();
        $this->actingAs($unauthorizedUser)->post(route('budgets.import'), [])->assertForbidden();
        $this->actingAs($unauthorizedUser)->get(route('budgets.template'))->assertForbidden();
    }
});

test('admin can view budget planning matrix across departments', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create([
        'code' => 'DEPT_PROD',
        'name' => 'Production Department',
        'default_hourly_rate' => 30000.00,
    ]);

    $section1 = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_LINE_1',
        'name' => 'Assembly Line 1',
    ]);

    $section2 = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_LINE_2',
        'name' => 'Assembly Line 2',
    ]);

    $response = $this->actingAs($admin)->get(route('budgets.planning', [
        'year' => 2026,
        'month' => 9,
        'department_id' => $dept->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('budgets/Planning')
        ->where('fiscal_year', 2026)
        ->where('fiscal_month', 9)
        ->where('selected_department.id', $dept->id)
        ->has('sections', 2)
        ->has('summary')
        ->where('summary.total_planned_hours', 0)
        ->where('summary.configured_sections_count', 0)
    );
});

test('manager is scoped to their assigned department', function () {
    $deptManager = Department::factory()->create([
        'code' => 'DEPT_MGR',
        'name' => 'Manager Department',
    ]);

    $otherDept = Department::factory()->create([
        'code' => 'DEPT_OTHER',
        'name' => 'Other Department',
    ]);

    $section = Section::factory()->create(['department_id' => $deptManager->id]);
    $otherSection = Section::factory()->create(['department_id' => $otherDept->id]);

    $manager = User::factory()->manager($deptManager->id)->create();

    // Even if manager requests otherDept, service must lock to manager's department
    $response = $this->actingAs($manager)->get(route('budgets.planning', [
        'year' => 2026,
        'month' => 9,
        'department_id' => $otherDept->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('budgets/Planning')
        ->where('selected_department.id', $deptManager->id)
        ->has('sections', 1)
        ->where('sections.0.section_id', $section->id)
    );
});

test('manager cannot store budget for a different department', function () {
    $deptManager = Department::factory()->create();
    $otherDept = Department::factory()->create();

    $otherSection = Section::factory()->create(['department_id' => $otherDept->id]);

    $manager = User::factory()->manager($deptManager->id)->create();

    $response = $this->actingAs($manager)->post(route('budgets.store'), [
        'department_id' => $otherDept->id,
        'section_id' => $otherSection->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 120.0,
    ]);

    $response->assertSessionHasErrors('department_id');
});

test('admin can upsert section overtime budget with auto 5-week distribution and cost snapshot', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create([
        'default_hourly_rate' => 25000.00,
    ]);

    $section = Section::factory()->create(['department_id' => $dept->id]);

    $response = $this->actingAs($admin)->post(route('budgets.store'), [
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 100.0,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $budget = OvertimeBudget::where('department_id', $dept->id)
        ->where('section_id', $section->id)
        ->where('fiscal_year', 2026)
        ->where('fiscal_month', 9)
        ->first();

    expect($budget)->not->toBeNull();
    expect((float) $budget->planned_hours)->toBe(100.0);
    expect((float) $budget->planned_cost_idr)->toBe(2500000.00); // 100 * 25,000
    expect((float) $budget->week1_planned_hours)->toBe(23.26); // 100 / 4.3 rounded
});

test('custom 5-week distribution can be stored even when weekly sum diverges from monthly target', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create([
        'default_hourly_rate' => 20000.00,
    ]);

    $section = Section::factory()->create(['department_id' => $dept->id]);

    $response = $this->actingAs($admin)->post(route('budgets.store'), [
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 100.0,
        'week1_planned_hours' => 20.0,
        'week2_planned_hours' => 20.0,
        'week3_planned_hours' => 20.0,
        'week4_planned_hours' => 20.0,
        'week5_planned_hours' => 30.0, // sum = 110 != 100
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $budget = OvertimeBudget::where('section_id', $section->id)->first();
    expect((float) $budget->planned_hours)->toBe(100.0);
    expect((float) $budget->week5_planned_hours)->toBe(30.0);
});

test('csv preview validates rows and flags invalid sections or years', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['code' => 'DEPT_CSV']);
    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_VALID',
        'is_active' => true,
    ]);

    $csvContent = "section_code,fiscal_year,fiscal_month,planned_hours\n"
        ."SEC_VALID,2026,9,150.00\n"
        ."SEC_UNKNOWN,2026,9,120.00\n"
        ."SEC_VALID,1999,9,100.00\n";

    $file = UploadedFile::fake()->createWithContent('budget.csv', $csvContent);

    $response = $this->actingAs($admin)->post(route('budgets.import.preview'), [
        'file' => $file,
    ]);

    $response->assertOk();
    $data = $response->json();

    expect($data['total'])->toBe(3);
    expect($data['valid_count'])->toBe(1);
    expect($data['error_count'])->toBe(2);
    expect($data['rows'][0]['is_valid'])->toBeTrue();
    expect($data['rows'][1]['is_valid'])->toBeFalse();
    expect($data['rows'][2]['is_valid'])->toBeFalse();
});

test('csv import commits verified rows to database', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create([
        'default_hourly_rate' => 25000.00,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_COMMIT',
    ]);

    $response = $this->actingAs($admin)->post(route('budgets.import'), [
        'rows' => [
            [
                'section_id' => $section->id,
                'department_id' => $dept->id,
                'fiscal_year' => 2026,
                'fiscal_month' => 9,
                'planned_hours' => 200.0,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('overtime_budgets', [
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'fiscal_year' => 2026,
        'fiscal_month' => 9,
        'planned_hours' => 200.0,
    ]);
});

test('manager cannot import csv with rows outside assigned department', function () {
    $managerDept = Department::factory()->create();
    $otherDept = Department::factory()->create();

    $manager = User::factory()->manager($managerDept->id)->create();
    $otherSection = Section::factory()->create([
        'department_id' => $otherDept->id,
        'code' => 'SEC_OUTSIDE',
    ]);

    $csvContent = "section_code,fiscal_year,fiscal_month,planned_hours\n"
        ."SEC_OUTSIDE,2026,9,150.00\n";

    $file = UploadedFile::fake()->createWithContent('budget.csv', $csvContent);

    $response = $this->actingAs($manager)->post(route('budgets.import.preview'), [
        'file' => $file,
    ]);

    $response->assertOk();
    $data = $response->json();
    expect($data['valid_count'])->toBe(0);
    expect($data['error_count'])->toBe(1);
    expect($data['rows'][0]['is_valid'])->toBeFalse();
});

test('budget template download returns valid csv file', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('budgets.template'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $response->assertHeader('Content-Disposition', 'attachment; filename="overtime_budget_template.csv"');

    expect($response->getContent())->toContain('section_code,fiscal_year,fiscal_month,planned_hours');
});
