<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\User;

test('guest visiting root / sees tailored automotive isuzu login page with indonesian default and can switch to english', function () {
    visit('/')
        ->assertPathIs('/')
        ->assertSee('PT ISUZU ASTRA MOTOR INDONESIA')
        ->assertSee('Smart Monitoring Overtime versi 2.0')
        ->assertSee('Management Index Overtime berbasis Machine Learning')
        ->assertSee('Masuk ke Sistem')
        ->assertSee('Email atau NPK')
        ->assertSee('Kata Sandi')
        ->assertSee('Ingat saya')
        ->assertDontSee('Alokasi CapEx CIP')
        ->assertDontSee('Ergonomi 3-Klik')
        ->assertDontSee('Batas Depnaker')
        // Switch language to English via desktop brand bar toggle
        ->click('[data-test="lang-switch-desktop-en"]')
        ->assertSee('Log in to System')
        ->assertSee('Smart Monitoring Overtime versi 2.0')
        ->assertSee('Machine Learning-based Overtime Index Management')
        ->assertSee('Email or NPK')
        ->assertSee('Password')
        ->assertSee('Remember me')
        // Switch back to Indonesian
        ->click('[data-test="lang-switch-desktop-id"]')
        ->assertSee('Masuk ke Sistem')
        ->assertSee('Smart Monitoring Overtime versi 2.0')
        ->assertSee('Management Index Overtime berbasis Machine Learning')
        ->assertNoJavaScriptErrors();
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
        ->assertMissing('[data-test="active-shift-badge"]')
        ->assertSee('SMARTIME 2.0')
        ->assertSee('PT. Isuzu Astra Motor Indonesia')
        ->assertSee('Dashboard')
        ->assertSee('Fasilitas')
        ->assertDontSee('Sistem Shift')
        ->assertDontSee('3 Shift / 24 Jam')
        ->assertDontSee('Ambang Depnaker')
        ->assertDontSee('Maks 14 Jam/Minggu');
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
        ->assertMissing('[data-test="active-shift-badge"]')
        ->assertSee('SMARTIME 2.0')
        ->assertSee('PT. Isuzu Astra Motor Indonesia')
        ->assertSee('Dashboard')
        ->assertSee('Master Data')
        ->assertDontSee('Ambang Depnaker')
        ->assertDontSee('Sistem Shift')
        ->assertDontSee('3 Shift / 24 Jam')
        // Switch to English in sidebar footer
        ->click('[data-test="lang-switch-sidebar-en"]')
        ->assertSee('Operations & Overtime')
        ->assertSee('Facility')
        ->assertDontSee('Shift System')
        ->assertDontSee('3 Shifts / 24 Hours')
        ->assertDontSee('Statutory Limit')
        ->assertDontSee('Max 14 Hours/Week')
        ->click('[data-slot="sidebar-trigger"]')
        ->assertNoJavaScriptErrors();
});
