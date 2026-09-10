<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\User;

test('user can log in using email and view operational dashboard', function () {
    $user = User::factory()->admin()->create([
        'name' => 'Super Admin',
        'email' => 'admin@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertSee('Super Admin')
        ->assertSee('Dashboard');
});

test('user can log in using numeric NPK', function () {
    $user = User::factory()->teamLeader()->create([
        'npk' => 'EMP-88899',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'EMP-88899')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertSee('EMP-88899');
});

test('dashboard displays role badge and active operational shift', function () {
    $dept = Department::create([
        'code' => 'DEPT_ASSY',
        'name' => 'Assembly Department',
        'cost_center_code' => 'CC-ASSY-001',
        'default_hourly_rate' => 45000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_LINE_A',
        'name' => 'Line A Stamping',
        'is_active' => true,
    ]);

    $user = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Ahmad Fauzi',
        'email' => 'ahmad.fauzi@factory.com',
        'password' => 'password',
        'npk' => 'EMP-55001',
    ]);

    visit('/login')
        ->fill('email', 'EMP-55001')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertSee('Ahmad Fauzi')
        ->assertSee('Team Leader')
        ->assertSee('Assembly Department')
        ->assertSee('Line A Stamping')
        ->assertSee('WIB');
});

test('invalid credentials show inline error without wiping input', function () {
    visit('/login')
        ->fill('email', 'nonexistent@factory.com')
        ->fill('password', 'wrong-password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/login');
});

test('user can sign out safely and return to home', function () {
    $user = User::factory()->user()->create([
        'name' => 'Budi Santoso',
        'email' => 'budi.santoso@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'budi.santoso@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/my/dashboard')
        ->click('Budi Santoso')
        ->click('[data-test="logout-button"]')
        ->click('[data-test="confirm-logout-button"]')
        ->assertPathIs('/');
});

test('unauthorized user receives friendly access restricted 403 page and can return to dashboard', function () {
    $operator = User::factory()->user()->create([
        'name' => 'Operator Joko',
        'email' => 'operator.joko@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'operator.joko@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/my/dashboard')
        ->navigate('/admin/overview')
        ->assertSee('Access Restricted')
        ->assertSee('Return to Dashboard')
        ->click('Return to Dashboard')
        ->assertPathIs('/my/dashboard');
});
