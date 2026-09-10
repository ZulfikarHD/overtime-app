<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\User;

test('guest visiting root / sees tailored automotive isuzu login page with indonesian default and can switch to english', function () {
    visit('/')
        ->assertPathIs('/')
        ->assertSee('PT ISUZU ASTRA MOTOR INDONESIA')
        ->assertSee('Masuk ke Sistem')
        ->assertSee('Email atau NPK')
        ->assertSee('Kata Sandi')
        ->assertSee('Ingat saya')
        ->assertSee('Alokasi CapEx CIP')
        ->assertSee('Ergonomi 3-Klik')
        ->assertSee('Batas Depnaker')
        // Switch language to English via desktop brand bar toggle
        ->click('[data-test="lang-switch-desktop-en"]')
        ->assertSee('Log in to System')
        ->assertSee('Email or NPK')
        ->assertSee('Password')
        ->assertSee('Remember me')
        ->assertSee('CIP CapEx Allocation')
        ->assertSee('3-Click Ergonomics')
        ->assertSee('Statutory Limit')
        // Switch back to Indonesian
        ->click('[data-test="lang-switch-desktop-id"]')
        ->assertSee('Masuk ke Sistem')
        ->assertSee('Alokasi CapEx CIP');
});

test('user can log in through tailored isuzu automotive login page and view plant sidebar with telemetry card', function () {
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
        'department_id' => $dept->id,
        'section_id' => $section->id,
    ]);

    visit('/')
        ->fill('email', 'plant.manager@isuzu.astra.co.id')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertSee('ISUZU OT-CapEx')
        ->assertSee('Karawang Assembly')
        ->assertSee('Dashboard')
        ->assertSee('Fasilitas')
        ->assertSee('Sistem Shift')
        ->assertSee('3 Shift / 24 Jam')
        ->assertSee('Ambang Depnaker')
        ->assertSee('Maks 14 Jam/Minggu');
});

test('authenticated user sees redesigned sidebar following style guide with telemetry card and toggle rail', function () {
    $dept = Department::create([
        'code' => 'DEPT_ENG_TEST',
        'name' => 'Engineering Department',
        'cost_center_code' => 'CC-ENG-01',
        'default_hourly_rate' => 50000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_ENG_TEST',
        'name' => 'Engineering Section',
        'is_active' => true,
    ]);

    $user = User::factory()->admin()->create([
        'name' => 'Automotive Engineer',
        'email' => 'engineer@isuzu.astra.co.id',
        'password' => 'password',
        'npk' => 'ISZ-9002',
        'department_id' => $dept->id,
        'section_id' => $section->id,
    ]);

    visit('/')
        ->fill('email', 'engineer@isuzu.astra.co.id')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertSee('ISUZU OT-CapEx')
        ->assertSee('Karawang Assembly')
        ->assertSee('Dashboard')
        ->assertSee('Burn Index')
        ->assertSee('Proyek CapEx')
        ->assertSee('Master Data')
        ->assertSee('Ambang Depnaker')
        // Switch to English in sidebar footer
        ->click('[data-test="lang-switch-sidebar-en"]')
        ->assertSee('Operations & Overtime')
        ->assertSee('Financial & Governance')
        ->assertSee('CapEx Projects')
        ->assertSee('Facility')
        ->assertSee('Shift System')
        ->assertSee('3 Shifts / 24 Hours')
        ->assertSee('Statutory Limit')
        ->assertSee('Max 14 Hours/Week')
        ->click('[data-slot="sidebar-trigger"]');
});
