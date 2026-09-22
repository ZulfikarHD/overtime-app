<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use App\Models\SplEntry;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a SplEntry group (one or more entries) for the approval queue tests.
 *
 * @param  array<string, mixed>  $attrs
 * @param  array{department?: Department, section?: Section, manager?: User, employee?: Employee}  $ctx
 * @return array{department: Department, section: Section, manager: User, entry: SplEntry}
 */
function createSplApprovalFixture(array $attrs = [], array $ctx = []): array
{
    $department = $ctx['department'] ?? Department::factory()->create([
        'code' => 'DEPT_SAQ_'.uniqid(),
        'name' => 'Stamping Approval Dept',
        'is_active' => true,
    ]);

    $section = $ctx['section'] ?? Section::factory()->create([
        'department_id' => $department->id,
        'code' => 'SEC_SAQ_'.uniqid(),
        'name' => 'Press Line A',
        'is_active' => true,
    ]);

    $manager = $ctx['manager'] ?? User::factory()->manager($department->id)->create();

    $employee = $ctx['employee'] ?? Employee::factory()->forDepartmentAndSection($department, $section)->create([
        'full_name' => 'Budi Santoso',
        'npk' => 'NPK-'.fake()->unique()->numerify('#####'),
        'is_active' => true,
    ]);

    $date = $attrs['realization_date'] ?? Carbon::now('Asia/Jakarta')->toDateString();

    $importer = User::factory()->user()->create();

    $entry = SplEntry::create(array_merge([
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'employee_name_snapshot' => $employee->full_name,
        'section_id' => $section->id,
        'department_id' => $department->id,
        'section_name_snapshot' => $section->name,
        'department_name_snapshot' => $department->name,
        'realization_date' => $date,
        'day_type' => 'HKN',
        'start_time' => '18:00:00',
        'end_time' => '22:00:00',
        'total_hours' => 4.00,
        'jenis_pekerjaan' => 'Assembly',
        'type_ot_code' => 61,
        'status' => 'PENDING',
        'imported_by_user_id' => $importer->id,
        'lock_version' => 0,
    ], $attrs));

    return compact('department', 'section', 'manager', 'entry');
}

test('guest is redirected to login when accessing approval queue', function () {
    $this->get(route('overtime.approvals'))->assertRedirect(route('login'));
});

test('team leader cannot access approval queue', function () {
    $section = Section::factory()->create();
    $tl = User::factory()->teamLeader($section->id, $section->department_id)->create();

    $this->actingAs($tl)
        ->get(route('overtime.approvals'))
        ->assertForbidden();
});

test('operator user cannot access approval queue', function () {
    $user = User::factory()->user()->create();

    $this->actingAs($user)
        ->get(route('overtime.approvals'))
        ->assertForbidden();
});

test('manager sees groups for their department only', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();
    $fixture = createSplApprovalFixture(['realization_date' => $today]);

    // Another department entry — should NOT appear
    $otherDept = Department::factory()->create(['is_active' => true]);
    $otherSec = Section::factory()->create(['department_id' => $otherDept->id, 'is_active' => true]);
    $importer = User::factory()->user()->create();

    SplEntry::create([
        'npk_snapshot' => 'NPK-99999',
        'employee_name_snapshot' => 'Orang Lain',
        'section_id' => $otherSec->id,
        'department_id' => $otherDept->id,
        'section_name_snapshot' => $otherSec->name,
        'department_name_snapshot' => $otherDept->name,
        'realization_date' => $today,
        'day_type' => 'HKN',
        'start_time' => '18:00:00',
        'end_time' => '22:00:00',
        'total_hours' => 4.00,
        'status' => 'PENDING',
        'imported_by_user_id' => $importer->id,
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('overtime.approvals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('overtime/ApprovalQueue')
            ->has('groups', 1)
            ->where('groups.0.section_id', $fixture['section']->id)
            ->where('pending_count', 1)
            ->has('available_sections')
            ->has('filters')
        );
});

test('admin sees plant-wide groups and can filter by department', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $fixtureA = createSplApprovalFixture(['realization_date' => $today]);
    // Different section/dept for fixtureB
    $fixtureB = createSplApprovalFixture(['realization_date' => $today]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('overtime.approvals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('overtime/ApprovalQueue')
            ->has('groups', 2)
            ->has('available_departments')
            ->where('pending_count', 2)
        );

    $this->actingAs($admin)
        ->get(route('overtime.approvals', ['department_id' => $fixtureA['department']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 1)
            ->where('groups.0.section_id', $fixtureA['section']->id)
            ->where('filters.department_id', (string) $fixtureA['department']->id)
        );

    expect($fixtureB['entry']->exists)->toBeTrue();
});

test('default status filter shows PENDING groups only', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $fixturePending = createSplApprovalFixture(['realization_date' => $today, 'status' => 'PENDING']);
    $importer = User::factory()->user()->create();

    // An APPROVED entry in the same department (different section to form a different group)
    $approvedSec = Section::factory()->create([
        'department_id' => $fixturePending['department']->id,
        'is_active' => true,
    ]);
    SplEntry::create([
        'npk_snapshot' => 'NPK-A1',
        'employee_name_snapshot' => 'Approved Worker',
        'section_id' => $approvedSec->id,
        'department_id' => $fixturePending['department']->id,
        'section_name_snapshot' => $approvedSec->name,
        'department_name_snapshot' => $fixturePending['department']->name,
        'realization_date' => $today,
        'day_type' => 'HKN',
        'start_time' => '18:00:00',
        'end_time' => '22:00:00',
        'total_hours' => 3.00,
        'status' => 'APPROVED',
        'imported_by_user_id' => $importer->id,
    ]);

    $this->actingAs($fixturePending['manager'])
        ->get(route('overtime.approvals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 1)
            ->where('groups.0.section_id', $fixturePending['section']->id)
            ->where('groups.0.status', 'PENDING')
            ->where('pending_count', 1)
        );

    // Switch to APPROVED filter
    $this->actingAs($fixturePending['manager'])
        ->get(route('overtime.approvals', ['status' => 'APPROVED']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 1)
            ->where('groups.0.status', 'APPROVED')
        );

    // ALL filter shows both
    $this->actingAs($fixturePending['manager'])
        ->get(route('overtime.approvals', ['status' => 'ALL']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 2)
        );
});

test('queue supports section and date range filters', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();
    $oldDate = Carbon::now('Asia/Jakarta')->subDays(10)->toDateString();

    $fixture = createSplApprovalFixture(['realization_date' => $today]);

    // Old entry in same dept/section
    $importer = User::factory()->user()->create();
    SplEntry::create([
        'npk_snapshot' => 'NPK-OLD',
        'employee_name_snapshot' => 'Old Worker',
        'section_id' => $fixture['section']->id,
        'department_id' => $fixture['department']->id,
        'section_name_snapshot' => $fixture['section']->name,
        'department_name_snapshot' => $fixture['department']->name,
        'realization_date' => $oldDate,
        'day_type' => 'HKN',
        'start_time' => '18:00:00',
        'end_time' => '21:00:00',
        'total_hours' => 3.00,
        'status' => 'PENDING',
        'imported_by_user_id' => $importer->id,
    ]);

    // Filter by section → both dates but same section (so 2 groups if dates differ)
    $this->actingAs($fixture['manager'])
        ->get(route('overtime.approvals', [
            'section_id' => $fixture['section']->id,
            'status' => 'ALL',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('groups', 2));

    // Filter by date range → only old
    $this->actingAs($fixture['manager'])
        ->get(route('overtime.approvals', [
            'date_from' => $oldDate,
            'date_to' => $oldDate,
            'status' => 'ALL',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('groups', 1)
            ->where('groups.0.date', $oldDate)
        );
});

test('approval queue paginates twenty groups per page', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $department = Department::factory()->create(['is_active' => true]);
    $manager = User::factory()->manager($department->id)->create();
    $importer = User::factory()->user()->create();

    // Create 21 distinct section+date groups
    for ($i = 1; $i <= 21; $i++) {
        $section = Section::factory()->create([
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        SplEntry::create([
            'npk_snapshot' => "NPK-PAGE-{$i}",
            'employee_name_snapshot' => "Worker {$i}",
            'section_id' => $section->id,
            'department_id' => $department->id,
            'section_name_snapshot' => $section->name,
            'department_name_snapshot' => $department->name,
            'realization_date' => $today,
            'day_type' => 'HKN',
            'start_time' => '18:00:00',
            'end_time' => '22:00:00',
            'total_hours' => 4.00,
            'status' => 'PENDING',
            'imported_by_user_id' => $importer->id,
        ]);
    }

    $this->actingAs($manager)
        ->get(route('overtime.approvals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 20)
            ->where('pagination.per_page', 20)
            ->where('pagination.total', 21)
            ->where('pagination.last_page', 2)
        );
});

test('groups response contains section department and aggregate fields', function () {
    $today = Carbon::now('Asia/Jakarta')->toDateString();
    $fixture = createSplApprovalFixture(['realization_date' => $today, 'total_hours' => 5.50]);

    $this->actingAs($fixture['manager'])
        ->get(route('overtime.approvals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 1)
            ->where('groups.0.entries_count', 1)
            ->where('groups.0.pending_count', 1)
            ->where('groups.0.approved_count', 0)
            ->where('groups.0.rejected_count', 0)
            ->where('groups.0.status', 'PENDING')
            ->has('groups.0.section')
            ->has('groups.0.department')
            ->has('groups.0.total_hours')
        );
});
