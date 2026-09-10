<?php

use App\Models\Department;
use App\Models\User;

test('admin can navigate to analytics hub from sidebar and interact with all 6 tabs', function () {
    $dept = Department::create([
        'code' => 'DEPT_BRW_ASY',
        'name' => 'Assembly Plant Department',
        'cost_center_code' => 'CC-BRW-ASY',
        'default_hourly_rate' => 45000.00,
        'is_active' => true,
    ]);

    $admin = User::factory()->admin()->create([
        'name' => 'Budi Analytics Admin',
        'email' => 'budi.analytics@factory.com',
        'password' => 'password',
        'npk' => 'EMP-99111',
    ]);

    visit('/login')
        ->fill('email', 'budi.analytics@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->click('[data-test="nav-analytics"]')
        ->assertPathIs('/analytics')
        ->assertPresent('[data-test="analytics-page-heading"]')
        ->assertPresent('[data-test="tab-predictive"]')
        ->assertPresent('[data-test="tab-cost"]')
        ->assertPresent('[data-test="tab-correlation"]')
        ->assertPresent('[data-test="tab-scenario"]')
        ->assertPresent('[data-test="tab-insights"]')
        ->assertPresent('[data-test="tab-comparison"]')
        ->click('[data-test="tab-cost"]')
        ->assertPresent('[data-test="tab-cost-content"]')
        ->click('[data-test="tab-correlation"]')
        ->assertPresent('[data-test="tab-correlation-content"]')
        ->click('[data-test="tab-scenario"]')
        ->assertPresent('[data-test="tab-scenario-content"]')
        ->click('[data-test="tab-insights"]')
        ->assertPresent('[data-test="tab-insights-content"]')
        ->click('[data-test="tab-comparison"]')
        ->assertPresent('[data-test="tab-comparison-content"]');
});

test('manager sees scoped analytics view and can open export report popover', function () {
    $dept = Department::create([
        'code' => 'DEPT_BRW_QAS',
        'name' => 'Quality Assurance Plant',
        'cost_center_code' => 'CC-BRW-QAS',
        'default_hourly_rate' => 48000.00,
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Rina Manager QA',
        'email' => 'rina.qa@factory.com',
        'password' => 'password',
        'npk' => 'EMP-99222',
    ]);

    visit('/login')
        ->fill('email', 'rina.qa@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->click('[data-test="nav-analytics"]')
        ->assertPathIs('/analytics')
        ->assertPresent('[data-test="filter-department-locked"]')
        ->assertPresent('[data-test="btn-analytics-export"]')
        ->click('[data-test="btn-analytics-export"]')
        ->assertPresent('[data-test="export-pdf-option"]')
        ->assertPresent('[data-test="export-csv-option"]');
});

test('operator role does not see analytics sidebar item', function () {
    $operator = User::factory()->user()->create([
        'name' => 'Siti Operator',
        'email' => 'siti.operator@factory.com',
        'password' => 'password',
        'npk' => 'EMP-99333',
    ]);

    visit('/login')
        ->fill('email', 'siti.operator@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/my/dashboard')
        ->assertMissing('[data-test="nav-analytics"]');
});
