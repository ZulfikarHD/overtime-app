<?php

use App\Models\User;

test('sidebar hides financial governance and approvals when features are off', function () {
    config([
        'features.financial_governance_enabled' => false,
        'features.overtime_approvals_enabled' => false,
    ]);

    User::factory()->admin()->create([
        'email' => 'feature-gate-admin@factory.com',
        'password' => 'password',
        'name' => 'Feature Gate Admin',
    ]);

    visit('/login')
        ->fill('email', 'feature-gate-admin@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertSee('ISUZU Overtime')
        ->assertDontSee('OT-CapEx')
        ->assertMissing('[data-test="nav-capex-projects"]')
        ->assertMissing('[data-test="nav-burn-index"]')
        ->assertMissing('[data-test="nav-analytics"]')
        ->assertMissing('[data-test="nav-budget-planning"]')
        ->assertMissing('[data-test="nav-overtime-approvals"]')
        ->assertVisible('[data-test="nav-dashboard"]')
        ->assertVisible('[data-test="nav-spl-import"]')
        ->assertVisible('[data-test="nav-employee-reports"]')
        ->assertNoJavaScriptErrors();
});
