<?php

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

test('users table schema includes role, npk, department_id, section_id, and is_active columns', function () {
    expect(Schema::hasColumn('users', 'role'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'npk'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'department_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'section_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'is_active'))->toBeTrue();
});

test('user model role helper methods identify roles accurately', function () {
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create();
    $operator = User::factory()->user()->create();

    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->isManager())->toBeFalse()
        ->and($admin->isTeamLeader())->toBeFalse()
        ->and($admin->isUser())->toBeFalse();

    expect($manager->isManager())->toBeTrue()
        ->and($manager->isAdmin())->toBeFalse();

    expect($teamLeader->isTeamLeader())->toBeTrue()
        ->and($teamLeader->isAdmin())->toBeFalse();

    expect($operator->isUser())->toBeTrue()
        ->and($operator->isAdmin())->toBeFalse();
});

test('user model hasRole supports enum, single string, and array of roles', function () {
    $manager = User::factory()->manager()->create();

    expect($manager->hasRole(UserRole::Manager))->toBeTrue()
        ->and($manager->hasRole('manager'))->toBeTrue()
        ->and($manager->hasRole(['admin', 'manager']))->toBeTrue()
        ->and($manager->hasRole(['admin', 'team_leader']))->toBeFalse();
});

test('user canAccessSection strictly isolates cross-section access for team leaders and cross-department for managers', function () {
    $deptA = Department::create([
        'code' => 'DEPT_A',
        'name' => 'Production Department A',
        'cost_center_code' => 'CC-A-001',
        'default_hourly_rate' => 45000.00,
        'is_active' => true,
    ]);

    $deptB = Department::create([
        'code' => 'DEPT_B',
        'name' => 'Production Department B',
        'cost_center_code' => 'CC-B-001',
        'default_hourly_rate' => 48000.00,
        'is_active' => true,
    ]);

    $sectionA1 = Section::create([
        'department_id' => $deptA->id,
        'code' => 'SEC_A1',
        'name' => 'Section A1',
        'is_active' => true,
    ]);

    $sectionA2 = Section::create([
        'department_id' => $deptA->id,
        'code' => 'SEC_A2',
        'name' => 'Section A2',
        'is_active' => true,
    ]);

    $sectionB1 = Section::create([
        'department_id' => $deptB->id,
        'code' => 'SEC_B1',
        'name' => 'Section B1',
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create();
    $managerA = User::factory()->manager($deptA->id)->create();
    $teamLeaderA1 = User::factory()->teamLeader($sectionA1->id, $deptA->id)->create();
    $operator = User::factory()->user()->create(['department_id' => $deptA->id, 'section_id' => $sectionA1->id]);

    // Admin can access all sections
    expect($admin->canAccessSection($sectionA1))->toBeTrue()
        ->and($admin->canAccessSection($sectionB1))->toBeTrue();

    // Manager can access all sections within own department, but not other departments
    expect($managerA->canAccessSection($sectionA1))->toBeTrue()
        ->and($managerA->canAccessSection($sectionA2))->toBeTrue()
        ->and($managerA->canAccessSection($sectionB1))->toBeFalse();

    // Team Leader can only access their assigned section
    expect($teamLeaderA1->canAccessSection($sectionA1))->toBeTrue()
        ->and($teamLeaderA1->canAccessSection($sectionA2))->toBeFalse()
        ->and($teamLeaderA1->canAccessSection($sectionB1))->toBeFalse();

    // General user cannot access any section data management
    expect($operator->canAccessSection($sectionA1))->toBeFalse();
});

test('user canAccessDepartment restricts access to own department for managers and allows for admin', function () {
    $deptA = Department::create([
        'code' => 'DEPT_A1',
        'name' => 'Dept A1',
        'cost_center_code' => 'CC-A1-001',
        'default_hourly_rate' => 50000.00,
        'is_active' => true,
    ]);

    $deptB = Department::create([
        'code' => 'DEPT_B1',
        'name' => 'Dept B1',
        'cost_center_code' => 'CC-B1-001',
        'default_hourly_rate' => 52000.00,
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create();
    $managerA = User::factory()->manager($deptA->id)->create();
    $teamLeader = User::factory()->teamLeader(null, $deptA->id)->create();

    expect($admin->canAccessDepartment($deptA))->toBeTrue()
        ->and($admin->canAccessDepartment($deptB))->toBeTrue()
        ->and($managerA->canAccessDepartment($deptA))->toBeTrue()
        ->and($managerA->canAccessDepartment($deptB))->toBeFalse()
        ->and($teamLeader->canAccessDepartment($deptA))->toBeFalse();
});

test('users can authenticate using their numeric NPK', function () {
    $user = User::factory()->create([
        'npk' => 'EMP-99881',
        'password' => 'secret-password-123',
        'is_active' => true,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'EMP-99881',
        'password' => 'secret-password-123',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('deactivated users cannot authenticate even with valid credentials', function () {
    $user = User::factory()->inactive()->create([
        'email' => 'inactive.user@example.com',
        'npk' => 'EMP-INACTIVE',
        'password' => 'valid-password',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'inactive.user@example.com',
        'password' => 'valid-password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('ensure_role middleware allows authorized roles and blocks unauthorized roles with 403', function () {
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create();
    $operator = User::factory()->user()->create();

    // Admin endpoint
    $this->actingAs($admin)->get(route('admin.overview'))->assertOk();
    $this->actingAs($manager)->get(route('admin.overview'))->assertForbidden();
    $this->actingAs($teamLeader)->get(route('admin.overview'))->assertForbidden();
    $this->actingAs($operator)->get(route('admin.overview'))->assertForbidden();

    // Manager endpoint (admin and manager allowed)
    $this->actingAs($admin)->get(route('manager.overview'))->assertOk();
    $this->actingAs($manager)->get(route('manager.overview'))->assertOk();
    $this->actingAs($teamLeader)->get(route('manager.overview'))->assertForbidden();
    $this->actingAs($operator)->get(route('manager.overview'))->assertForbidden();

    // Team Leader endpoint (admin and team_leader allowed)
    $this->actingAs($admin)->get(route('team-leader.overview'))->assertOk();
    $this->actingAs($manager)->get(route('team-leader.overview'))->assertForbidden();
    $this->actingAs($teamLeader)->get(route('team-leader.overview'))->assertOk();
    $this->actingAs($operator)->get(route('team-leader.overview'))->assertForbidden();
});

test('authorization gates strictly enforce role permission matrix', function () {
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create();
    $operator = User::factory()->user()->create();

    // User management: Admin only
    expect(Gate::forUser($admin)->allows('manage-users'))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('manage-users'))->toBeFalse()
        ->and(Gate::forUser($teamLeader)->allows('manage-users'))->toBeFalse()
        ->and(Gate::forUser($operator)->allows('manage-users'))->toBeFalse();

    // Create overtime: Admin and Team Leader
    expect(Gate::forUser($admin)->allows('create-overtime'))->toBeTrue()
        ->and(Gate::forUser($teamLeader)->allows('create-overtime'))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('create-overtime'))->toBeFalse()
        ->and(Gate::forUser($operator)->allows('create-overtime'))->toBeFalse();

    // Approve overtime: Admin and Manager
    expect(Gate::forUser($admin)->allows('approve-overtime'))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('approve-overtime'))->toBeTrue()
        ->and(Gate::forUser($teamLeader)->allows('approve-overtime'))->toBeFalse()
        ->and(Gate::forUser($operator)->allows('approve-overtime'))->toBeFalse();

    // View all sections: Admin only
    expect(Gate::forUser($admin)->allows('view-all-sections'))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('view-all-sections'))->toBeFalse()
        ->and(Gate::forUser($teamLeader)->allows('view-all-sections'))->toBeFalse();

    // View personal report: All authenticated users
    expect(Gate::forUser($admin)->allows('view-personal-report'))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('view-personal-report'))->toBeTrue()
        ->and(Gate::forUser($teamLeader)->allows('view-personal-report'))->toBeTrue()
        ->and(Gate::forUser($operator)->allows('view-personal-report'))->toBeTrue();

    // ML dashboard: Admin and Manager
    expect(Gate::forUser($admin)->allows('view-ml-dashboard'))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('view-ml-dashboard'))->toBeTrue()
        ->and(Gate::forUser($teamLeader)->allows('view-ml-dashboard'))->toBeFalse()
        ->and(Gate::forUser($operator)->allows('view-ml-dashboard'))->toBeFalse();

    // Policy configuration: Admin only
    expect(Gate::forUser($admin)->allows('configure-policy'))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('configure-policy'))->toBeFalse()
        ->and(Gate::forUser($teamLeader)->allows('configure-policy'))->toBeFalse();
});

test('handle inertia requests shares role, npk, department, and section in auth user prop', function () {
    $dept = Department::create([
        'code' => 'DEPT_PROD',
        'name' => 'Production Department',
        'cost_center_code' => 'CC-PR-001',
        'default_hourly_rate' => 45000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_STAMPING',
        'name' => 'Stamping Section',
        'is_active' => true,
    ]);

    $user = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Zulfikar Hidayatullah',
        'npk' => 'EMP-77001',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('auth.user.name', 'Zulfikar Hidayatullah')
        ->where('auth.user.role', 'team_leader')
        ->where('auth.user.npk', 'EMP-77001')
        ->where('auth.user.department.name', 'Production Department')
        ->where('auth.user.section.name', 'Stamping Section')
        ->has('translations')
        ->has('locale')
    );
});
