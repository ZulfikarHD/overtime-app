<?php

use App\Models\PolicyThreshold;
use App\Models\ShiftSchedule;
use App\Models\User;

test('admin can open shift times tab and create a new shift period', function () {
    PolicyThreshold::factory()->plantDefault()->create();

    ShiftSchedule::factory()->create([
        'name' => 'Standard Plant Shift',
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'shift_1_start' => '07:00',
        'shift_1_end' => '15:00',
        'shift_2_start' => '15:00',
        'shift_2_end' => '23:00',
        'shift_3_start' => '23:00',
        'shift_3_end' => '07:00',
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin.shifts@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'admin.shifts@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Administration')
        ->assertPathIs('/admin/administration')
        ->click('[data-test="tab-shifts"]')
        ->assertSee('Plant Shift Times')
        ->assertPresent('[data-test="shift-schedules-table"]')
        ->assertSee('Standard Plant Shift')
        ->click('[data-test="btn-add-shift-schedule"]')
        ->assertPresent('[data-test="shift-schedule-sheet"]')
        ->fill('[data-test="input-shift-schedule-name"]', 'Ramadan Adjustment')
        ->fill('[data-test="input-shift-effective-from"]', '2026-03-01')
        ->fill('[data-test="input-shift-effective-until"]', '2026-03-31')
        ->fill('[data-test="input-shift-1-start"]', '06:00')
        ->fill('[data-test="input-shift-1-end"]', '14:00')
        ->fill('[data-test="input-shift-2-start"]', '14:00')
        ->fill('[data-test="input-shift-2-end"]', '22:00')
        ->fill('[data-test="input-shift-3-start"]', '22:00')
        ->fill('[data-test="input-shift-3-end"]', '06:00')
        ->click('[data-test="btn-save-shift-schedule"]')
        ->assertSee('Shift schedule saved successfully.')
        ->assertSee('Ramadan Adjustment')
        ->assertNoJavaScriptErrors();
});
