<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\User;

test('dashboard defaults to department scope with all sections and exposes section options', function () {
    $dept = Department::create([
        'code' => 'DEPT_SEC_FILTER',
        'name' => 'Assembly Filter Dept',
        'cost_center_code' => 'CC-SEC-F1',
        'default_hourly_rate' => 35000,
        'is_active' => true,
    ]);

    $secA = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_A',
        'name' => 'Line A',
        'is_active' => true,
    ]);

    $secB = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_B',
        'name' => 'Line B',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create();
    $this->actingAs($manager);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('selectedSectionId', null)
        ->where('selectedDepartmentId', $dept->id)
        ->has('sections', 2)
        ->where('sections.0.id', fn ($id) => in_array($id, [$secA->id, $secB->id], true))
        ->where('kpiCards.scope.section_id', null)
        ->where('dailyBurnChart.scope.section_id', null)
    );
});

test('dashboard narrows charts and kpi cards when section_id is selected', function () {
    $dept = Department::create([
        'code' => 'DEPT_SEC_NARROW',
        'name' => 'Narrow Filter Dept',
        'cost_center_code' => 'CC-SEC-N1',
        'default_hourly_rate' => 36000,
        'is_active' => true,
    ]);

    $secA = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_N_A',
        'name' => 'Narrow A',
        'is_active' => true,
    ]);

    Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_N_B',
        'name' => 'Narrow B',
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create();
    $this->actingAs($manager);

    $response = $this->get(route('dashboard', [
        'department_id' => $dept->id,
        'section_id' => $secA->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('selectedSectionId', $secA->id)
        ->where('selectedDepartmentId', $dept->id)
        ->where('kpiCards.scope.section_id', $secA->id)
        ->where('dailyBurnChart.scope.section_id', $secA->id)
        ->where('employeeSummary.scope.section_id', $secA->id)
    );
});

test('admin section filter is ignored when section does not belong to selected department', function () {
    $deptA = Department::create([
        'code' => 'DEPT_SEC_A',
        'name' => 'Dept A',
        'cost_center_code' => 'CC-A',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $deptB = Department::create([
        'code' => 'DEPT_SEC_B',
        'name' => 'Dept B',
        'cost_center_code' => 'CC-B',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    Section::create([
        'department_id' => $deptA->id,
        'code' => 'SEC_A1',
        'name' => 'Sec A1',
        'is_active' => true,
    ]);

    $secB = Section::create([
        'department_id' => $deptB->id,
        'code' => 'SEC_B1',
        'name' => 'Sec B1',
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->get(route('dashboard', [
        'department_id' => $deptA->id,
        'section_id' => $secB->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('selectedDepartmentId', $deptA->id)
        ->where('selectedSectionId', null)
        ->where('kpiCards.scope.section_id', null)
    );
});

test('admin sections list is scoped to the selected department', function () {
    $deptA = Department::create([
        'code' => 'DEPT_LIST_A',
        'name' => 'List Dept A',
        'cost_center_code' => 'CC-LA',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $deptB = Department::create([
        'code' => 'DEPT_LIST_B',
        'name' => 'List Dept B',
        'cost_center_code' => 'CC-LB',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $secA = Section::create([
        'department_id' => $deptA->id,
        'code' => 'SEC_LA',
        'name' => 'List Sec A',
        'is_active' => true,
    ]);

    Section::create([
        'department_id' => $deptB->id,
        'code' => 'SEC_LB',
        'name' => 'List Sec B',
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->get(route('dashboard', ['department_id' => $deptA->id]));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('sections', 1)
        ->where('sections.0.id', $secA->id)
        ->where('sections.0.department_id', $deptA->id)
    );
});
