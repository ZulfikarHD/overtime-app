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
        ->click('Log in to System')
        ->click('Analytics & Decision')
        ->assertPathIs('/analytics')
        ->assertSee('Overtime Analytics & Decision Intelligence')
        ->assertSee('Predictive Analytics')
        ->assertSee('Cost Analysis')
        ->assertSee('Correlation & Patterns')
        ->assertSee('Scenario Simulation')
        ->assertSee('Key Insights')
        ->assertSee('Period Comparison')
        ->click('[data-test="tab-cost"]')
        ->assertSee('Total Overtime Cost')
        ->click('[data-test="tab-correlation"]')
        ->assertSee('Fair Overtime Zone (Sweet Spot)')
        ->click('[data-test="tab-scenario"]')
        ->assertSee('Production Volume Planning Calculator')
        ->click('[data-test="tab-insights"]')
        ->assertSee('Risk Indicators & Automatic Alerts')
        ->click('[data-test="tab-comparison"]')
        ->assertSee('Period Comparison & Department Benchmarking');
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
        ->click('Log in to System')
        ->click('Analytics & Decision')
        ->assertPathIs('/analytics')
        ->assertSee('Quality Assurance Plant')
        ->assertSee('Export Report')
        ->click('[data-test="btn-analytics-export"]')
        ->assertSee('Download Executive Summary')
        ->assertSee('PDF (Executive 1-Page Summary)')
        ->assertSee('CSV (Raw Tab Data)');
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
        ->click('Log in to System')
        ->assertPathIs('/my/dashboard')
        ->assertDontSee('Analytics & Decision');
});
