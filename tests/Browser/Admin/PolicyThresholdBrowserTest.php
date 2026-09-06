<?php

use App\Models\Department;
use App\Models\PolicyThreshold;
use App\Models\User;

test('admin can navigate to administration hub and view plant default policy', function () {
    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.00,
        'burn_danger_pct' => 115.00,
    ]);

    $dept = Department::factory()->create([
        'code' => 'DEPT_TEST_QA',
        'name' => 'Quality Assurance Plant',
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin.policy.nav@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.policy.nav@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->assertSee('Administration')
        ->click('Administration')
        ->assertPathIs('/admin/administration')
        ->assertSee('Administration Hub')
        ->assertSee('Plant-wide Default Policy')
        ->assertSee('DEPT_TEST_QA')
        ->assertSee('Quality Assurance Plant');
});

test('admin can edit plant-wide default policy threshold', function () {
    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.00,
        'burn_danger_pct' => 115.00,
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin.policy.edit@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.policy.edit@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Administration')
        ->assertPathIs('/admin/administration')
        ->click('[data-test="btn-edit-plant-default"]')
        ->assertSee('Edit Plant Default')
        ->fill('#input-weekly-soft-limit', '25')
        ->fill('#input-spkl-grace-period', '4')
        ->click('[data-test="btn-save-policy"]')
        ->assertSee('Plant-wide default policy threshold updated successfully.')
        ->assertSee('25');
});

test('admin can configure and delete department policy override', function () {
    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
        'spkl_grace_period_days' => 2,
        'burn_warning_pct' => 100.00,
        'burn_danger_pct' => 115.00,
    ]);

    $dept = Department::factory()->create([
        'code' => 'DEPT_PRESS',
        'name' => 'Stamping Press Line',
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin.policy.override@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.policy.override@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Administration')
        ->assertPathIs('/admin/administration')
        // Open override sheet
        ->click('[data-test="btn-add-override"]')
        ->assertSee('Add Department Override')
        ->select('#select-department', (string) $dept->id)
        ->fill('#input-weekly-soft-limit', '32')
        ->fill('#input-consecutive-weeks', '4')
        ->fill('#input-burn-warning', '105')
        ->fill('#input-burn-danger', '120')
        ->click('[data-test="btn-save-policy"]')
        ->assertSee('Department policy override saved successfully.')
        ->assertSee('DEPT_PRESS')
        ->assertSee('32')
        // Delete override and confirm revert to plant default
        ->click('[data-test="btn-delete-override-DEPT_PRESS"]')
        ->assertSee('Delete department override?')
        ->click('[data-test="confirm-dialog-confirm-button"]')
        ->assertSee('Department policy override deleted successfully. Department will inherit plant default.');
});

test('client-side validation warns and prevents saving when danger is less than warning', function () {
    PolicyThreshold::factory()->plantDefault()->create();

    $dept = Department::factory()->create([
        'code' => 'DEPT_VALIDATE',
        'name' => 'Validation Department',
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin.policy.validation@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.policy.validation@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Administration')
        ->click('[data-test="btn-add-override"]')
        ->select('#select-department', (string) $dept->id)
        ->fill('#input-burn-warning', '120')
        ->fill('#input-burn-danger', '100')
        ->assertSee('Burn danger percentage must be greater than or equal to burn warning percentage.');
});
