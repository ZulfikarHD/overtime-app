<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\User;

test('authenticated user sees topbar indicators, live wib clock, and role badge', function () {
    $dept = Department::create([
        'code' => 'DEPT_BROWSER_ASY',
        'name' => 'Assembly Department',
        'cost_center_code' => 'CC-ASY-BRW',
        'default_hourly_rate' => 45000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_BROWSER_TRIM',
        'name' => 'Trim & Chassis Line',
        'is_active' => true,
    ]);

    $user = User::factory()->teamLeader($section->id, $dept->id)->create([
        'name' => 'Bambang Sudirman',
        'email' => 'bambang.sudirman@factory.com',
        'password' => 'password',
        'npk' => 'EMP-88001',
    ]);

    visit('/login')
        ->fill('email', 'bambang.sudirman@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertSee('Bambang Sudirman')
        ->assertSee('EMP-88001')
        ->assertSee('WIB')
        ->assertSee('Team Leader')
        ->assertSee('Trim & Chassis Line')
        ->assertSee('Assembly Department')
        ->assertSee('Dashboard');
});

test('user dropdown displays department and section and reveals inline sign out confirmation', function () {
    $dept = Department::create([
        'code' => 'DEPT_BROWSER_MNT',
        'name' => 'Maintenance Department',
        'cost_center_code' => 'CC-MNT-BRW',
        'default_hourly_rate' => 50000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_BROWSER_ELEC',
        'name' => 'Automation & PLC Section',
        'is_active' => true,
    ]);

    $user = User::factory()->manager($dept->id)->create([
        'name' => 'Dr. Hendra Gunawan',
        'email' => 'hendra.gunawan@factory.com',
        'password' => 'password',
        'npk' => 'EMP-99002',
    ]);

    visit('/login')
        ->fill('email', 'hendra.gunawan@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Dr. Hendra Gunawan')
        ->assertSee('Maintenance Department')
        ->assertSee('Sign Out')
        ->click('[data-test="logout-button"]')
        ->assertSee('Yes, Sign Out')
        ->click('Cancel')
        ->assertDontSee('Yes, Sign Out');
});

test('guest layout enforces centered clean authentication card with dual identifier placeholder', function () {
    visit('/login')
        ->assertSee('Log in to System')
        ->assertSee('Email or NPK')
        ->assertSee('Password')
        ->assertSee('Remember me')
        ->assertSee('Having trouble logging in? Contact HR / IT Admin');
});
