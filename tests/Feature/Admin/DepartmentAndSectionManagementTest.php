<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest is redirected to login when accessing master data', function () {
    $response = $this->get(route('admin.master-data'));

    $response->assertRedirect(route('login'));
});

test('non-admin users are denied access to master data hub', function () {
    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create();
    $operator = User::factory()->user()->create();

    $this->actingAs($manager)->get(route('admin.master-data'))->assertForbidden();
    $this->actingAs($teamLeader)->get(route('admin.master-data'))->assertForbidden();
    $this->actingAs($operator)->get(route('admin.master-data'))->assertForbidden();
});

test('admin can access master data hub with departments and nested sections', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::create([
        'code' => 'DEPT_TEST_STP',
        'name' => 'Stamping Department Test',
        'cost_center_code' => 'CC-STP-999',
        'default_hourly_rate' => 35000.00,
        'is_active' => true,
    ]);

    Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_TEST_PRESS',
        'name' => 'Press Line A',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.master-data'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/MasterData')
        ->where('activeTab', 'departments')
        ->has('departments', fn (Assert $departments) => $departments
            ->where('0.code', 'DEPT_TEST_STP')
            ->where('0.name', 'Stamping Department Test')
            ->where('0.sections.0.code', 'SEC_TEST_PRESS')
            ->etc()
        )
    );
});

test('admin can create a department with valid parameters', function () {
    $admin = User::factory()->admin()->create();

    $payload = [
        'code' => 'dept_new_log',
        'name' => 'Logistics & Warehouse',
        'cost_center_code' => 'cc-log-101',
        'default_hourly_rate' => 42000.50,
        'is_active' => true,
    ];

    $response = $this->actingAs($admin)->post(route('admin.departments.store'), $payload);

    $response->assertSessionHas('success', __('Department created successfully.'));

    $this->assertDatabaseHas('departments', [
        'code' => 'DEPT_NEW_LOG',
        'name' => 'Logistics & Warehouse',
        'cost_center_code' => 'CC-LOG-101',
        'default_hourly_rate' => 42000.50,
        'is_active' => true,
    ]);
});

test('duplicate department code is rejected', function () {
    $admin = User::factory()->admin()->create();

    Department::create([
        'code' => 'DEPT_DUP',
        'name' => 'Original Dept',
        'cost_center_code' => 'CC-01',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.departments.store'), [
        'code' => 'DEPT_DUP',
        'name' => 'Duplicate Dept',
        'cost_center_code' => 'CC-02',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors(['code']);
});

test('negative default hourly rate is rejected', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.departments.store'), [
        'code' => 'DEPT_NEG',
        'name' => 'Negative Rate Dept',
        'cost_center_code' => 'CC-NEG',
        'default_hourly_rate' => -500,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors(['default_hourly_rate']);
});

test('department code is immutable upon update', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::create([
        'code' => 'DEPT_IMMUTABLE',
        'name' => 'Initial Name',
        'cost_center_code' => 'CC-INIT',
        'default_hourly_rate' => 25000,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->put(route('admin.departments.update', $dept), [
        'code' => 'DEPT_TRY_CHANGE',
        'name' => 'Updated Name',
        'cost_center_code' => 'CC-UPDATED',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $response->assertSessionHas('success');

    $dept->refresh();
    expect($dept->code)->toBe('DEPT_IMMUTABLE')
        ->and($dept->name)->toBe('Updated Name')
        ->and($dept->cost_center_code)->toBe('CC-UPDATED')
        ->and((float) $dept->default_hourly_rate)->toBe(30000.0);
});

test('admin can create a section nested under a department', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::create([
        'code' => 'DEPT_PARENT',
        'name' => 'Parent Dept',
        'cost_center_code' => 'CC-PAR',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.sections.store'), [
        'department_id' => $dept->id,
        'code' => 'sec_child_1',
        'name' => 'Child Section 1',
        'is_active' => true,
    ]);

    $response->assertSessionHas('success', __('Section created successfully.'));

    $this->assertDatabaseHas('sections', [
        'department_id' => $dept->id,
        'code' => 'SEC_CHILD_1',
        'name' => 'Child Section 1',
        'is_active' => true,
    ]);
});

test('duplicate section code is rejected', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::create([
        'code' => 'DEPT_SEC_DUP',
        'name' => 'Dept',
        'cost_center_code' => 'CC-01',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_DUP',
        'name' => 'Existing Section',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.sections.store'), [
        'department_id' => $dept->id,
        'code' => 'SEC_DUP',
        'name' => 'Second Section',
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors(['code']);
});

test('admin cannot deactivate department if it has active child sections', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::create([
        'code' => 'DEPT_DEACT_GUARD',
        'name' => 'Guarded Dept',
        'cost_center_code' => 'CC-GRD',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_ACTIVE_CHILD',
        'name' => 'Active Child Section',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->put(route('admin.departments.update', $dept), [
        'name' => 'Guarded Dept',
        'cost_center_code' => 'CC-GRD',
        'default_hourly_rate' => 30000,
        'is_active' => false,
    ]);

    $response->assertSessionHasErrors(['is_active']);
    expect($dept->fresh()->is_active)->toBeTrue();
});

test('admin can deactivate department when child sections are inactive', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::create([
        'code' => 'DEPT_DEACT_OK',
        'name' => 'Deactivatable Dept',
        'cost_center_code' => 'CC-OK',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $sec = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_INACTIVE_CHILD',
        'name' => 'Inactive Child Section',
        'is_active' => false,
    ]);

    $response = $this->actingAs($admin)->put(route('admin.departments.update', $dept), [
        'name' => 'Deactivatable Dept',
        'cost_center_code' => 'CC-OK',
        'default_hourly_rate' => 30000,
        'is_active' => false,
    ]);

    $response->assertSessionHas('success');
    expect($dept->fresh()->is_active)->toBeFalse();
});

test('admin cannot delete department with child sections', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::create([
        'code' => 'DEPT_HAS_SEC',
        'name' => 'Dept with Section',
        'cost_center_code' => 'CC-SEC',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_CHILD_EXISTS',
        'name' => 'Child Section',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.departments.destroy', $dept));

    $response->assertSessionHasErrors(['delete']);
    $this->assertDatabaseHas('departments', ['id' => $dept->id]);
});

test('admin cannot delete department with registered employees', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::create([
        'code' => 'DEPT_HAS_EMP',
        'name' => 'Dept with Employee',
        'cost_center_code' => 'CC-EMP',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_FOR_EMP_TEST',
        'name' => 'Section for Employee Test',
        'is_active' => true,
    ]);

    Employee::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP_TEST_001',
        'full_name' => 'Test Employee',
        'job_position' => 'Operator',
        'hourly_rate' => 25000,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.departments.destroy', $dept));

    $response->assertSessionHasErrors(['delete']);
    $this->assertDatabaseHas('departments', ['id' => $dept->id]);
});

test('admin can delete clean department with no dependencies', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::create([
        'code' => 'DEPT_CLEAN',
        'name' => 'Clean Dept',
        'cost_center_code' => 'CC-CLN',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.departments.destroy', $dept));

    $response->assertSessionHas('success');
    $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
});

test('admin cannot delete section with registered employees', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::create([
        'code' => 'DEPT_SEC_EMP',
        'name' => 'Dept',
        'cost_center_code' => 'CC-01',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_HAS_EMP',
        'name' => 'Section with Employee',
        'is_active' => true,
    ]);

    Employee::create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'npk' => 'EMP_TEST_002',
        'full_name' => 'Test Employee 2',
        'job_position' => 'Senior Technician',
        'hourly_rate' => 32000,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.sections.destroy', $section));

    $response->assertSessionHasErrors(['delete']);
    $this->assertDatabaseHas('sections', ['id' => $section->id]);
});

test('admin can delete clean section with no dependencies', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::create([
        'code' => 'DEPT_FOR_CLEAN_SEC',
        'name' => 'Dept',
        'cost_center_code' => 'CC-01',
        'default_hourly_rate' => 30000,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_CLEAN',
        'name' => 'Clean Section',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.sections.destroy', $section));

    $response->assertSessionHas('success');
    $this->assertDatabaseMissing('sections', ['id' => $section->id]);
});
