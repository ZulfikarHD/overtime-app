<?php

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\OperationalCalendar;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    PolicyThreshold::factory()->plantDefault()->create();
});

test('guest is redirected to login when accessing user routes', function () {
    $this->get(route('admin.administration', ['tab' => 'users']))->assertRedirect(route('login'));
    $this->post(route('admin.users.store'), [])->assertRedirect(route('login'));

    $user = User::factory()->create();
    $this->put(route('admin.users.update', $user), [])->assertRedirect(route('login'));
    $this->delete(route('admin.users.destroy', $user))->assertRedirect(route('login'));
    $this->post(route('admin.users.reset-password', $user))->assertRedirect(route('login'));
});

test('non-admin users are forbidden from accessing administration hub and user CRUD routes', function () {
    $manager = User::factory()->manager()->create();
    $teamLeader = User::factory()->teamLeader()->create();
    $operator = User::factory()->user()->create();
    $targetUser = User::factory()->create();

    foreach ([$manager, $teamLeader, $operator] as $nonAdmin) {
        $this->actingAs($nonAdmin)->get(route('admin.administration', ['tab' => 'users']))->assertForbidden();
        $this->actingAs($nonAdmin)->post(route('admin.users.store'), [
            'name' => 'Unauthorized User',
            'email' => 'unauth@factory.com',
            'password' => 'password123',
            'role' => 'user',
        ])->assertForbidden();
        $this->actingAs($nonAdmin)->put(route('admin.users.update', $targetUser), [
            'name' => 'Updated Name',
            'email' => $targetUser->email,
            'role' => 'user',
        ])->assertForbidden();
        $this->actingAs($nonAdmin)->delete(route('admin.users.destroy', $targetUser))->assertForbidden();
        $this->actingAs($nonAdmin)->post(route('admin.users.reset-password', $targetUser))->assertForbidden();
    }
});

test('admin can access administration hub on users tab with expected props', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.administration', ['tab' => 'users']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/Administration')
        ->where('activeTab', 'users')
        ->has('users')
        ->has('userStats')
        ->has('userFilters')
        ->has('availableRoles')
        ->has('departmentsWithSections')
    );
});

test('admin can search and filter users in administration hub', function () {
    $admin = User::factory()->admin()->create();

    $specialUser = User::factory()->create([
        'name' => 'Zulfikar Unique Dev',
        'email' => 'zulfikar@factory.com',
        'role' => UserRole::Manager,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.administration', [
        'tab' => 'users',
        'user_search' => 'Zulfikar',
        'user_role' => 'manager',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('users.total', 1)
        ->where('users.data.0.email', 'zulfikar@factory.com')
    );
});

test('admin can create a new user account with role and department scoping', function () {
    $admin = User::factory()->admin()->create();
    $department = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $department->id]);

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Agus Supriyanto',
        'email' => 'agus.supriyanto@factory.com',
        'password' => 'Password123!',
        'role' => UserRole::TeamLeader->value,
        'department_id' => $department->id,
        'section_id' => $section->id,
        'npk' => 'EMP-09999',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', __('User account created successfully.'));

    $this->assertDatabaseHas('users', [
        'email' => 'agus.supriyanto@factory.com',
        'name' => 'Agus Supriyanto',
        'role' => 'team_leader',
        'department_id' => $department->id,
        'section_id' => $section->id,
        'npk' => 'EMP-09999',
        'is_active' => true,
    ]);

    // Check creation audit log
    $newUser = User::where('email', 'agus.supriyanto@factory.com')->firstOrFail();
    $this->assertDatabaseHas('user_audits', [
        'user_id' => $newUser->id,
        'actor_user_id' => $admin->id,
        'action' => 'user_created',
        'new_role' => 'team_leader',
    ]);
});

test('creating user validates unique email and valid section for department', function () {
    $admin = User::factory()->admin()->create();
    $existing = User::factory()->create(['email' => 'existing@factory.com']);

    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $secB = Section::factory()->create(['department_id' => $deptB->id]);

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Duplicate Email',
        'email' => 'existing@factory.com',
        'password' => 'Password123!',
        'role' => 'user',
        'department_id' => $deptA->id,
        'section_id' => $secB->id, // Mismatched section
    ]);

    $response->assertSessionHasErrors(['email', 'section_id']);
});

test('admin can update user details and changing role logs audit entry', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->user()->create([
        'name' => 'Original Name',
        'email' => 'operator.change@factory.com',
    ]);

    $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => 'Promoted Manager',
        'email' => 'operator.change@factory.com',
        'role' => UserRole::Manager->value,
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', __('User account updated successfully.'));

    $user->refresh();
    expect($user->name)->toBe('Promoted Manager');
    expect($user->role)->toBe(UserRole::Manager);

    $this->assertDatabaseHas('user_audits', [
        'user_id' => $user->id,
        'actor_user_id' => $admin->id,
        'action' => 'role_change',
        'previous_role' => 'user',
        'new_role' => 'manager',
    ]);
});

test('admin cannot deactivate own account', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => 'admin',
        'is_active' => false,
    ]);

    $response->assertSessionHasErrors('is_active');
    $admin->refresh();
    expect($admin->is_active)->toBeTrue();
});

test('admin cannot demote own role from administrator', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => 'team_leader',
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors('role');
    $admin->refresh();
    expect($admin->role)->toBe(UserRole::Admin);
});

test('admin cannot delete own account', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

    $response->assertSessionHasErrors('user');
    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('admin cannot delete user with linked overtime submissions', function () {
    $admin = User::factory()->admin()->create();
    $tl = User::factory()->teamLeader()->create();
    $dept = Department::factory()->create();
    $sec = Section::factory()->create(['department_id' => $dept->id]);

    OperationalCalendar::firstOrCreate(
        ['calendar_date' => '2026-09-08'],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );

    OvertimeSubmission::create([
        'submission_code' => 'SPKL-TEST-001',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $sec->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 0.00,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $tl));

    $response->assertSessionHasErrors('user');
    $this->assertDatabaseHas('users', ['id' => $tl->id]);
});

test('admin can delete a user without linked overtime records', function () {
    $admin = User::factory()->admin()->create();
    $userToDelete = User::factory()->user()->create();

    $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $userToDelete));

    $response->assertRedirect();
    $response->assertSessionHas('success', __('User account deleted successfully.'));
    $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
});

test('admin can trigger password reset link for user', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $targetUser = User::factory()->create(['email' => 'target.reset@factory.com']);

    $response = $this->actingAs($admin)->post(route('admin.users.reset-password', $targetUser));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    Notification::assertSentTo($targetUser, ResetPassword::class);

    $this->assertDatabaseHas('user_audits', [
        'user_id' => $targetUser->id,
        'actor_user_id' => $admin->id,
        'action' => 'password_reset_sent',
    ]);
});

test('successful authentication stamps last_login_at timestamp', function () {
    $user = User::factory()->create([
        'email' => 'test.login.stamp@factory.com',
        'password' => 'password',
        'last_login_at' => null,
    ]);

    $this->post(route('login.store'), [
        'email' => 'test.login.stamp@factory.com',
        'password' => 'password',
    ]);

    $user->refresh();
    expect($user->last_login_at)->not->toBeNull();
});

test('deactivated user is prevented from authenticating', function () {
    $inactiveUser = User::factory()->inactive()->create([
        'email' => 'blocked.user@factory.com',
        'password' => 'password',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'blocked.user@factory.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
