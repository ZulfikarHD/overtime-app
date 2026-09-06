<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

test('guest is redirected to login when accessing employee roster or master data', function () {
    $this->get(route('admin.master-data', ['tab' => 'employees']))->assertRedirect(route('login'));
});

test('non-admin users are forbidden from managing employees', function () {
    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create();
    $operator = User::factory()->user()->create();

    $this->actingAs($manager)->get(route('admin.master-data', ['tab' => 'employees']))->assertForbidden();
    $this->actingAs($teamLeader)->get(route('admin.master-data', ['tab' => 'employees']))->assertForbidden();
    $this->actingAs($operator)->get(route('admin.master-data', ['tab' => 'employees']))->assertForbidden();
});

test('admin can access master data hub with employee roster, stats, and pagination', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create([
        'code' => 'PROD',
        'name' => 'Production Department',
        'default_hourly_rate' => 45000.00,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'STP',
        'name' => 'Stamping Section',
    ]);

    Employee::factory()->count(30)->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.master-data', ['tab' => 'employees']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/MasterData')
        ->where('activeTab', 'employees')
        ->has('employees.data', 25)
        ->where('employees.total', 30)
        ->where('employees.per_page', 25)
        ->has('employeeStats')
        ->where('employeeStats.total', 30)
    );
});

test('admin can search employees by npk, full name, or section', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['code' => 'MFG', 'name' => 'Manufacturing']);
    $sec1 = Section::factory()->create(['department_id' => $dept->id, 'code' => 'SEC_WELD', 'name' => 'Welding Section']);
    $sec2 = Section::factory()->create(['department_id' => $dept->id, 'code' => 'SEC_PAINT', 'name' => 'Painting Section']);

    $empTarget = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec1->id,
        'npk' => 'EMP-TARGET-99',
        'full_name' => 'Rian Kurniawan',
    ]);

    $empOther = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $sec2->id,
        'npk' => 'EMP-OTHER-11',
        'full_name' => 'Siti Aminah',
    ]);

    // Search by NPK
    $resNpk = $this->actingAs($admin)->get(route('admin.master-data', ['tab' => 'employees', 'search' => 'TARGET-99']));
    $resNpk->assertOk();
    $resNpk->assertInertia(fn (Assert $page) => $page
        ->where('employees.total', 1)
        ->where('employees.data.0.npk', 'EMP-TARGET-99')
    );

    // Search by name
    $resName = $this->actingAs($admin)->get(route('admin.master-data', ['tab' => 'employees', 'search' => 'Rian']));
    $resName->assertOk();
    $resName->assertInertia(fn (Assert $page) => $page
        ->where('employees.total', 1)
        ->where('employees.data.0.full_name', 'Rian Kurniawan')
    );

    // Search by section name
    $resSec = $this->actingAs($admin)->get(route('admin.master-data', ['tab' => 'employees', 'search' => 'Welding']));
    $resSec->assertOk();
    $resSec->assertInertia(fn (Assert $page) => $page
        ->where('employees.total', 1)
        ->where('employees.data.0.npk', 'EMP-TARGET-99')
    );
});

test('admin can filter employees by department and status', function () {
    $admin = User::factory()->admin()->create();

    $deptA = Department::factory()->create(['code' => 'DEPT_A']);
    $secA = Section::factory()->create(['department_id' => $deptA->id]);

    $deptB = Department::factory()->create(['code' => 'DEPT_B']);
    $secB = Section::factory()->create(['department_id' => $deptB->id]);

    $empActiveA = Employee::factory()->create([
        'department_id' => $deptA->id,
        'section_id' => $secA->id,
        'is_active' => true,
    ]);

    $empInactiveA = Employee::factory()->inactive()->create([
        'department_id' => $deptA->id,
        'section_id' => $secA->id,
    ]);

    $empActiveB = Employee::factory()->create([
        'department_id' => $deptB->id,
        'section_id' => $secB->id,
        'is_active' => true,
    ]);

    // Filter by Dept A and active
    $res = $this->actingAs($admin)->get(route('admin.master-data', [
        'tab' => 'employees',
        'department_id' => $deptA->id,
        'status' => 'active',
    ]));

    $res->assertOk();
    $res->assertInertia(fn (Assert $page) => $page
        ->where('employees.total', 1)
        ->where('employees.data.0.id', $empActiveA->id)
    );
});

test('admin can create an employee with valid parameters', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['default_hourly_rate' => 40000]);
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $payload = [
        'npk' => 'emp-new-001',
        'full_name' => 'Agus Pratama',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'job_position' => 'Senior Welder',
        'hourly_rate' => 45000.00,
        'is_active' => true,
    ];

    $response = $this->actingAs($admin)->post(route('admin.employees.store'), $payload);

    $response->assertSessionHas('success', __('Employee created successfully.'));

    $this->assertDatabaseHas('employees', [
        'npk' => 'EMP-NEW-001', // Converted to uppercase
        'full_name' => 'Agus Pratama',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'job_position' => 'Senior Welder',
        'hourly_rate' => 45000.00,
        'is_active' => true,
    ]);
});

test('duplicate employee NPK is rejected', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);

    Employee::factory()->create([
        'npk' => 'EMP-EXISTS',
        'department_id' => $dept->id,
        'section_id' => $section->id,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.employees.store'), [
        'npk' => 'emp-exists',
        'full_name' => 'Second Employee',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'job_position' => 'Operator',
        'hourly_rate' => null,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors(['npk']);
});

test('creating employee fails if section does not belong to department', function () {
    $admin = User::factory()->admin()->create();

    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $secOfDeptB = Section::factory()->create(['department_id' => $deptB->id]);

    $response = $this->actingAs($admin)->post(route('admin.employees.store'), [
        'npk' => 'EMP-MISMATCH',
        'full_name' => 'Mismatched Employee',
        'department_id' => $deptA->id,
        'section_id' => $secOfDeptB->id,
        'job_position' => 'Operator',
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors(['section_id']);
});

test('employee NPK is immutable upon update (BR-03)', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['default_hourly_rate' => 40000]);
    $section = Section::factory()->create(['department_id' => $dept->id]);

    $employee = Employee::factory()->create([
        'npk' => 'EMP-ORIGINAL-NPK',
        'full_name' => 'Original Name',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'job_position' => 'Junior Operator',
        'hourly_rate' => 35000.00,
    ]);

    $response = $this->actingAs($admin)->put(route('admin.employees.update', $employee), [
        'npk' => 'EMP-MODIFIED-NPK', // Should be ignored per BR-03
        'full_name' => 'Updated Name',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'job_position' => 'Senior Operator',
        'hourly_rate' => 42000.00,
        'is_active' => true,
    ]);

    $response->assertSessionHas('success', __('Employee updated successfully.'));

    $employee->refresh();
    expect($employee->npk)->toBe('EMP-ORIGINAL-NPK')
        ->and($employee->full_name)->toBe('Updated Name')
        ->and($employee->job_position)->toBe('Senior Operator')
        ->and((float) $employee->hourly_rate)->toBe(42000.00);
});

test('employee hourly_rate is nullable and effective_hourly_rate falls back to department default', function () {
    $dept = Department::factory()->create([
        'default_hourly_rate' => 45000.00,
    ]);
    $section = Section::factory()->create(['department_id' => $dept->id]);

    // Employee with null hourly_rate
    $employeeNullRate = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'hourly_rate' => null,
    ]);

    expect($employeeNullRate->hourly_rate)->toBeNull()
        ->and($employeeNullRate->effective_hourly_rate)->toBe(45000.00);

    // Employee with custom hourly_rate
    $employeeCustomRate = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'hourly_rate' => 52000.00,
    ]);

    expect((float) $employeeCustomRate->hourly_rate)->toBe(52000.00)
        ->and($employeeCustomRate->effective_hourly_rate)->toBe(52000.00);
});

test('admin can deactivate and activate employee record', function () {
    $admin = User::factory()->admin()->create();

    $employee = Employee::factory()->create(['is_active' => true]);

    // Deactivate
    $response = $this->actingAs($admin)->put(route('admin.employees.update', $employee), [
        'full_name' => $employee->full_name,
        'department_id' => $employee->department_id,
        'section_id' => $employee->section_id,
        'job_position' => $employee->job_position,
        'is_active' => false,
    ]);

    $response->assertSessionHas('success');
    expect($employee->fresh()->is_active)->toBeFalse();

    // Re-activate
    $response2 = $this->actingAs($admin)->put(route('admin.employees.update', $employee), [
        'full_name' => $employee->full_name,
        'department_id' => $employee->department_id,
        'section_id' => $employee->section_id,
        'job_position' => $employee->job_position,
        'is_active' => true,
    ]);

    $response2->assertSessionHas('success');
    expect($employee->fresh()->is_active)->toBeTrue();
});

test('admin cannot delete employee if linked to overtime item records', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->create([
        'department_id' => $dept->id,
        'section_id' => $section->id,
    ]);

    $calendarDate = '2026-09-06 00:00:00';
    DB::table('operational_calendars')->insert([
        'calendar_date' => $calendarDate,
        'day_type' => 'HKN',
        'is_holiday' => false,
        'created_at' => now(),
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-TEST-DEL-01',
        'submission_date' => '2026-09-06',
        'operational_date' => '2026-09-06',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $admin->id,
        'status' => 'DRAFT',
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => 2.0,
        'hourly_rate_snapshot' => 40000,
        'total_cost_snapshot' => 80000,
        'status' => 'PENDING',
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.employees.destroy', $employee));

    $response->assertSessionHasErrors(['delete']);
    $this->assertDatabaseHas('employees', ['id' => $employee->id]);
});

test('admin can delete clean employee with no overtime records', function () {
    $admin = User::factory()->admin()->create();

    $employee = Employee::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.employees.destroy', $employee));

    $response->assertSessionHas('success', __('Employee deleted successfully.'));
    $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
});

test('csv preview endpoint audits uploaded file and returns row-level validation results', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['code' => 'PROD', 'name' => 'Production']);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'code' => 'STP', 'name' => 'Stamping']);

    Employee::factory()->create(['npk' => 'EMP-TAKEN']);

    $csvContent = implode("\n", [
        'npk,full_name,department_code,section_code,job_position,hourly_rate',
        'EMP-NEW-101,Budi Santoso,PROD,STP,Line Operator,35000',
        'EMP-TAKEN,Duplicate User,PROD,STP,Operator,40000', // Already in DB
        'EMP-BAD-SEC,Salah Seksi,PROD,NONEXIST,Operator,', // Nonexistent section
        'EMP-NEW-101,Duplicate in CSV,PROD,STP,Operator,35000', // Duplicate in CSV
    ]);

    $file = UploadedFile::fake()->createWithContent('employees.csv', $csvContent);

    $response = $this->actingAs($admin)->postJson(route('admin.employees.import.preview'), [
        'file' => $file,
    ]);

    $response->assertOk();
    $data = $response->json();

    expect($data['total'])->toBe(4)
        ->and($data['valid_count'])->toBe(1)
        ->and($data['error_count'])->toBe(3);

    // Row 1 should be valid
    expect($data['rows'][0]['is_valid'])->toBeTrue()
        ->and($data['rows'][0]['npk'])->toBe('EMP-NEW-101')
        ->and($data['rows'][0]['department_id'])->toBe($dept->id)
        ->and($data['rows'][0]['section_id'])->toBe($sec->id);

    // Row 2 should be invalid (already in DB)
    expect($data['rows'][1]['is_valid'])->toBeFalse()
        ->and($data['rows'][1]['errors'][0])->toContain('sudah terdaftar pada sistem');

    // Row 3 should be invalid (nonexistent section)
    expect($data['rows'][2]['is_valid'])->toBeFalse()
        ->and($data['rows'][2]['errors'][0])->toContain('tidak ditemukan');

    // Row 4 should be invalid (duplicate in CSV)
    expect($data['rows'][3]['is_valid'])->toBeFalse()
        ->and($data['rows'][3]['errors'][0])->toContain('duplikat di dalam file CSV ini');
});

test('csv import commits validated employee rows into database', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['code' => 'ENG', 'name' => 'Engineering']);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'code' => 'MOLD', 'name' => 'Mold']);

    $payload = [
        'rows' => [
            [
                'npk' => 'EMP-BULK-01',
                'full_name' => 'Worker One',
                'department_id' => $dept->id,
                'section_id' => $sec->id,
                'job_position' => 'Mold Specialist',
                'hourly_rate' => 50000,
                'is_valid' => true,
            ],
            [
                'npk' => 'EMP-BULK-02',
                'full_name' => 'Worker Two',
                'department_id' => $dept->id,
                'section_id' => $sec->id,
                'job_position' => 'Technician',
                'hourly_rate' => null,
                'is_valid' => true,
            ],
        ],
    ];

    $response = $this->actingAs($admin)->post(route('admin.employees.import'), $payload);

    $response->assertSessionHas('success');

    $this->assertDatabaseHas('employees', [
        'npk' => 'EMP-BULK-01',
        'full_name' => 'Worker One',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'hourly_rate' => 50000,
    ]);

    $this->assertDatabaseHas('employees', [
        'npk' => 'EMP-BULK-02',
        'full_name' => 'Worker Two',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'hourly_rate' => null,
    ]);
});

test('admin can download csv roster template', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.employees.template'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->getContent())->toContain('npk,full_name,department_code,section_code,job_position,hourly_rate');
});
