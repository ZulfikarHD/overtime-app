<?php

use App\Models\Department;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest is redirected to login when accessing analytics routes', function () {
    $this->get(route('analytics.index'))->assertRedirect(route('login'));
    $this->get(route('analytics.export'))->assertRedirect(route('login'));
});

test('operator role is forbidden from accessing analytics hub', function () {
    $operator = User::factory()->user()->create();

    $this->actingAs($operator)
        ->get(route('analytics.index'))
        ->assertForbidden();

    $this->actingAs($operator)
        ->get(route('analytics.export'))
        ->assertForbidden();
});

test('team leader role is forbidden from accessing analytics hub', function () {
    $teamLeader = User::factory()->teamLeader()->create();

    $this->actingAs($teamLeader)
        ->get(route('analytics.index'))
        ->assertForbidden();

    $this->actingAs($teamLeader)
        ->get(route('analytics.export'))
        ->assertForbidden();
});

test('admin can access analytics hub and view all active departments', function () {
    $admin = User::factory()->admin()->create();

    $deptA = Department::factory()->create(['name' => 'Assembly Department', 'is_active' => true]);
    $deptB = Department::factory()->create(['name' => 'Quality Department', 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('analytics.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Analytics/Index')
        ->where('currentTab', 'predictive')
        ->where('userRole', 'admin')
        ->where('filters.department_id', 'all')
        ->has('departments', fn (Assert $deptAssert) => $deptAssert
            ->where('0.name', fn ($name) => in_array($name, ['Assembly Department', 'Quality Department'], true))
            ->etc()
        )
    );
});

test('manager can access analytics hub with department scoped', function () {
    $dept = Department::factory()->create(['name' => 'Maintenance Department', 'is_active' => true]);
    $manager = User::factory()->manager($dept->id)->create();

    $response = $this->actingAs($manager)->get(route('analytics.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Analytics/Index')
        ->where('currentTab', 'predictive')
        ->where('userRole', 'manager')
        ->where('userDepartmentId', $dept->id)
        ->where('filters.department_id', (string) $dept->id)
    );
});

test('valid tab query parameter sets currentTab in props', function () {
    $admin = User::factory()->admin()->create();

    $validTabs = ['predictive', 'cost', 'correlation', 'scenario', 'insights', 'comparison'];

    foreach ($validTabs as $tab) {
        $response = $this->actingAs($admin)->get(route('analytics.index', ['tab' => $tab]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Analytics/Index')
            ->where('currentTab', $tab)
        );
    }
});

test('invalid tab query parameter falls back to predictive', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('analytics.index', ['tab' => 'unknown_invalid_tab']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Analytics/Index')
        ->where('currentTab', 'predictive')
    );
});

test('export endpoint streams csv for authorized user', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create(['name' => 'Stamping Department', 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('analytics.export', [
        'format' => 'csv',
        'tab' => 'cost',
        'department_id' => $dept->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('export endpoint downloads pdf for authorized user', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create(['name' => 'Painting Department', 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('analytics.export', [
        'format' => 'pdf',
        'tab' => 'predictive',
        'department_id' => $dept->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('manager cannot export data for other departments', function () {
    $deptA = Department::factory()->create(['name' => 'Dept A', 'is_active' => true]);
    $deptB = Department::factory()->create(['name' => 'Dept B', 'is_active' => true]);

    $manager = User::factory()->manager($deptA->id)->create();

    $response = $this->actingAs($manager)->get(route('analytics.export', [
        'format' => 'csv',
        'tab' => 'cost',
        'department_id' => $deptB->id,
    ]));

    $response->assertForbidden();
});
