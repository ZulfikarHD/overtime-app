<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('team leader can search employees within their assigned section only', function () {
    $dept = Department::factory()->create(['name' => 'Assembly Department']);
    $sectionA = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Chassis Line']);
    $sectionB = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Engine Line']);

    $empInSec = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sectionA->id,
        'npk' => 'EMP-10001',
        'full_name' => 'Budi Santoso',
    ]);

    $empOtherSec = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sectionB->id,
        'npk' => 'EMP-20002',
        'full_name' => 'Budi Haryanto',
    ]);

    $tl = User::factory()->teamLeader($sectionA->id, $dept->id)->create();

    $response = $this->actingAs($tl)->getJson(route('reports.employees.search', ['q' => 'Budi']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['npk'])->toBe('EMP-10001')
        ->and($data[0]['name'])->toBe('Budi Santoso');
});

test('manager can search employees within their department across sections', function () {
    $deptA = Department::factory()->create(['name' => 'Body & Stamping']);
    $deptB = Department::factory()->create(['name' => 'Quality Control']);

    $secA1 = Section::factory()->create(['department_id' => $deptA->id, 'name' => 'Stamping Line 1']);
    $secA2 = Section::factory()->create(['department_id' => $deptA->id, 'name' => 'Welding Line 2']);
    $secB1 = Section::factory()->create(['department_id' => $deptB->id, 'name' => 'Incoming Inspection']);

    Employee::factory()->create([
        'department_id' => $deptA->id,
        'section_id' => $secA1->id,
        'npk' => 'EMP-A101',
        'full_name' => 'Agus Priyono',
    ]);

    Employee::factory()->create([
        'department_id' => $deptA->id,
        'section_id' => $secA2->id,
        'npk' => 'EMP-A102',
        'full_name' => 'Agus Salim',
    ]);

    Employee::factory()->create([
        'department_id' => $deptB->id,
        'section_id' => $secB1->id,
        'npk' => 'EMP-B201',
        'full_name' => 'Agus QC',
    ]);

    $manager = User::factory()->manager($deptA->id)->create();

    $response = $this->actingAs($manager)->getJson(route('reports.employees.search', ['q' => 'Agus']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2);

    $npks = array_column($data, 'npk');
    expect($npks)->toContain('EMP-A101', 'EMP-A102')
        ->and($npks)->not->toContain('EMP-B201');
});

test('admin can search all employees plant-wide', function () {
    $deptA = Department::factory()->create();
    $secA = Section::factory()->create(['department_id' => $deptA->id]);

    $deptB = Department::factory()->create();
    $secB = Section::factory()->create(['department_id' => $deptB->id]);

    Employee::factory()->create([
        'department_id' => $deptA->id,
        'section_id' => $secA->id,
        'npk' => 'ISZ-9001',
        'full_name' => 'Dewi Sartika',
    ]);

    Employee::factory()->create([
        'department_id' => $deptB->id,
        'section_id' => $secB->id,
        'npk' => 'ISZ-9002',
        'full_name' => 'Dewi Lestari',
    ]);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->getJson(route('reports.employees.search', ['q' => 'Dewi']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
});

test('search query with less than 3 characters returns empty array without querying', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->getJson(route('reports.employees.search', ['q' => 'De']));

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

test('regular user role cannot search other employees', function () {
    $user = User::factory()->user()->create();

    $response = $this->actingAs($user)->getJson(route('reports.employees.search', ['q' => 'Budi']));

    $response->assertForbidden();
});

test('team leader can view dossier of subordinate in their section', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP-TL-01',
        'full_name' => 'Joko Widodo',
        'job_position' => 'Line Operator',
        'is_active' => true,
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create();

    $response = $this->actingAs($tl)->get(route('reports.employees.show', ['npk' => $emp->npk]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('reports/EmployeeDossier')
        ->where('employee.npk', 'EMP-TL-01')
        ->where('employee.full_name', 'Joko Widodo')
        ->where('employee.job_position', 'Line Operator')
        ->where('employee.is_active', true)
        ->has('fiscal_year')
        ->has('fiscal_month')
    );
});

test('team leader cannot view dossier of employee from another section', function () {
    $dept = Department::factory()->create();
    $sectionA = Section::factory()->create(['department_id' => $dept->id]);
    $sectionB = Section::factory()->create(['department_id' => $dept->id]);

    $emp = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sectionB->id,
        'npk' => 'EMP-TL-02',
    ]);

    $tl = User::factory()->teamLeader($sectionA->id, $dept->id)->create();

    $response = $this->actingAs($tl)->get(route('reports.employees.show', ['npk' => $emp->npk]));

    $response->assertForbidden();
});

test('manager can view dossier of employee in their department but not outside', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $secA = Section::factory()->create(['department_id' => $deptA->id]);
    $secB = Section::factory()->create(['department_id' => $deptB->id]);

    $empA = Employee::factory()->create(['department_id' => $deptA->id, 'section_id' => $secA->id, 'npk' => 'EMP-MGR-A']);
    $empB = Employee::factory()->create(['department_id' => $deptB->id, 'section_id' => $secB->id, 'npk' => 'EMP-MGR-B']);

    $manager = User::factory()->manager($deptA->id)->create();

    // Within department: allowed
    $responseA = $this->actingAs($manager)->get(route('reports.employees.show', ['npk' => $empA->npk]));
    $responseA->assertOk();

    // Outside department: forbidden
    $responseB = $this->actingAs($manager)->get(route('reports.employees.show', ['npk' => $empB->npk]));
    $responseB->assertForbidden();
});

test('regular user can only view their own dossier', function () {
    $dept = Department::factory()->create();
    $sec = Section::factory()->create(['department_id' => $dept->id]);

    $empOwn = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'npk' => 'EMP-USER-OWN',
        'full_name' => 'Operator Sendiri',
    ]);

    $empOther = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'npk' => 'EMP-USER-OTHER',
        'full_name' => 'Operator Lain',
    ]);

    $user = User::factory()->user()->create([
        'npk' => 'EMP-USER-OWN',
    ]);

    // Own dossier: allowed
    $responseOwn = $this->actingAs($user)->get(route('reports.employees.show', ['npk' => $empOwn->npk]));
    $responseOwn->assertOk();
    $responseOwn->assertInertia(fn (Assert $page) => $page
        ->component('reports/EmployeeDossier')
        ->where('employee.npk', 'EMP-USER-OWN')
        ->where('is_own_dossier', true)
    );

    // Other employee dossier: forbidden
    $responseOther = $this->actingAs($user)->get(route('reports.employees.show', ['npk' => $empOther->npk]));
    $responseOther->assertForbidden();
});

test('visiting dossier index redirects regular user to their own dossier', function () {
    $user = User::factory()->user()->create([
        'npk' => 'EMP-USER-777',
    ]);

    $response = $this->actingAs($user)->get(route('reports.employees.index'));

    $response->assertRedirect(route('reports.employees.show', ['npk' => 'EMP-USER-777']));
});

test('team leader visiting dossier index sees roster scoped to their section', function () {
    $dept = Department::factory()->create();
    $secA = Section::factory()->create(['department_id' => $dept->id]);
    $secB = Section::factory()->create(['department_id' => $dept->id]);

    Employee::factory()->count(3)->create(['department_id' => $dept->id, 'section_id' => $secA->id]);
    Employee::factory()->count(2)->create(['department_id' => $dept->id, 'section_id' => $secB->id]);

    $tl = User::factory()->teamLeader($secA->id, $dept->id)->create();

    $response = $this->actingAs($tl)->get(route('reports.employees.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('reports/EmployeeDossier')
        ->where('employee', null)
        ->has('roster', 3)
    );
});
