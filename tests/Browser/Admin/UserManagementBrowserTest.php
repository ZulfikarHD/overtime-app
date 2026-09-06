<?php

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;

beforeEach(function () {
    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.00,
        'burn_danger_pct' => 115.00,
    ]);
});

test('admin can view user accounts tab in administration hub', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin.browser.view@factory.com',
        'password' => 'password',
    ]);

    User::factory()->create([
        'name' => 'Bambang Operator',
        'email' => 'bambang.op@factory.com',
        'role' => UserRole::User,
    ]);

    visit('/login')
        ->fill('email', 'admin.browser.view@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Administration')
        ->assertPathIs('/admin/administration')
        ->click('[data-test="tab-users"]')
        ->assertSee('User Accounts & Access Control')
        ->assertSee('Total Accounts')
        ->assertSee('Active Logins')
        ->assertSee('admin.browser.view@factory.com')
        ->assertSee('bambang.op@factory.com');
});

test('admin can provision a new user account via slide-in drawer', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin.browser.create@factory.com',
        'password' => 'password',
    ]);

    $dept = Department::factory()->create([
        'code' => 'DEPT_STAMPING',
        'name' => 'Stamping Department',
    ]);
    $sec = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_PRESS_A',
        'name' => 'Press Line A',
    ]);

    visit('/login')
        ->fill('email', 'admin.browser.create@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Administration')
        ->assertPathIs('/admin/administration')
        ->click('[data-test="tab-users"]')
        ->click('[data-test="btn-add-user"]')
        ->assertSee('Add New User Account')
        ->fill('[data-test="input-user-name"]', 'Surya Saputra')
        ->fill('[data-test="input-user-email"]', 'surya.saputra@factory.com')
        ->fill('[data-test="input-user-password"]', 'Password123!')
        ->select('[data-test="select-user-role"]', 'team_leader')
        ->select('[data-test="select-user-department"]', (string) $dept->id)
        ->select('[data-test="select-user-section"]', (string) $sec->id)
        ->fill('[data-test="input-user-npk"]', 'EMP-08888')
        ->click('[data-test="btn-save-user"]')
        ->assertSee('User account created successfully.')
        ->assertSee('Surya Saputra')
        ->assertSee('surya.saputra@factory.com');
});

test('admin can search and edit an existing user account', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin.browser.edit@factory.com',
        'password' => 'password',
    ]);

    $user = User::factory()->create([
        'name' => 'Dewi Lestari',
        'email' => 'dewi.lestari@factory.com',
        'role' => UserRole::User,
    ]);

    visit('/login')
        ->fill('email', 'admin.browser.edit@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Administration')
        ->click('[data-test="tab-users"]')
        ->fill('[data-test="input-search-users"]', 'Dewi')
        ->click('[data-test="btn-apply-user-search"]')
        ->assertSee('dewi.lestari@factory.com')
        ->click("[data-test=\"btn-edit-user-{$user->id}\"]")
        ->assertSee('Edit User Account')
        ->fill('[data-test="input-user-name"]', 'Dewi Lestari Senior')
        ->select('[data-test="select-user-role"]', 'manager')
        ->click('[data-test="btn-save-user"]')
        ->assertSee('User account updated successfully.')
        ->assertSee('Dewi Lestari Senior');
});

test('admin sees self-account protection on logged-in account row', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin.browser.self@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.browser.self@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Administration')
        ->click('[data-test="tab-users"]')
        ->assertSee('You')
        ->click("[data-test=\"btn-edit-user-{$admin->id}\"]")
        ->assertSee('Editing Your Own Account')
        ->assertSee('Role and active status are protected from modification to prevent accidental administrator lockout.');
});
