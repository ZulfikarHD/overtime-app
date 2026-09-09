<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('operator role visiting dashboard is redirected to self-service dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('my.dashboard'));
});

test('supervisory users can visit the operational dashboard', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('operator can visit self-service dashboard directly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('my.dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('dashboard/EmployeeSelfService'));
});
