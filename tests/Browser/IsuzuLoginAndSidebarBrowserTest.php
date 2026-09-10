<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\User;

test('guest visiting root / sees tailored automotive isuzu login page instead of welcome page', function () {
    visit('/')
        ->assertPathIs('/')
        ->assertSee('PT ISUZU ASTRA MOTOR INDONESIA')
        ->assertSee('Karawang Assembly Plant')
        ->assertSee('Log in to System')
        ->assertSee('Email or NPK')
        ->assertSee('Password')
        ->assertSee('Remember me')
        ->assertSee('CIP CapEx Allocation')
        ->assertSee('3-Click Ergonomics')
        ->assertSee('Statutory Limit');
});

test('user can log in through tailored isuzu automotive login page and view plant sidebar', function () {
    $dept = Department::create([
        'code' => 'DEPT_ISUZU_ASSY',
        'name' => 'Assembly Plant Department',
        'cost_center_code' => 'CC-ISZ-01',
        'default_hourly_rate' => 50000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_ISUZU_FINAL',
        'name' => 'Final Assembly Line',
        'is_active' => true,
    ]);

    $user = User::factory()->admin()->create([
        'name' => 'Isuzu Plant Manager',
        'email' => 'plant.manager@isuzu.astra.co.id',
        'password' => 'password',
        'npk' => 'ISZ-9001',
    ]);

    visit('/')
        ->fill('email', 'plant.manager@isuzu.astra.co.id')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertSee('ISUZU OT-CapEx')
        ->assertSee('Karawang Assembly')
        ->assertSee('Dashboard')
        ->assertSee('Facility')
        ->assertSee('Shift System')
        ->assertSee('Statutory Limit')
        ->assertSee('Maks 14 Jam/Minggu');
});

test('authenticated user sees redesigned sidebar following style guide with telemetry card and toggle rail', function () {
    $user = User::factory()->admin()->create([
        'name' => 'Automotive Engineer',
        'email' => 'engineer@isuzu.astra.co.id',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'engineer@isuzu.astra.co.id')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertSee('ISUZU OT-CapEx')
        ->assertSee('Karawang Assembly')
        ->assertSee('Dashboard')
        ->assertSee('Burn Index')
        ->assertSee('CapEx Projects')
        ->assertSee('Master Data')
        ->assertSee('Administration')
        ->assertSee('Statutory Limit')
        ->click('[data-slot="sidebar-trigger"]');
});
