<?php

use App\Models\AlertFollowUp;
use App\Models\Department;
use App\Models\PolicyThreshold;
use App\Models\User;

beforeEach(function () {
    PolicyThreshold::factory()->plantDefault()->create();
});

test('manager saves a 5W2H draft then sends it by email from the early warning panel', function () {
    $dept = Department::factory()->create(['name' => 'Assembly Followup Dept', 'is_active' => true]);
    $pic = Department::factory()->create(['name' => 'PCD Followup Dept', 'is_active' => true]);

    User::factory()->manager($pic->id)->create(['email' => 'pic.manager@factory.com']);
    User::factory()->manager($dept->id)->create([
        'email' => 'manager.followup@factory.com',
        'password' => 'password',
    ]);

    visit('/')
        ->click('[data-test="welcome-enter-button"]')
        ->fill('email', 'manager.followup@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->click('[data-test="tab-distribution"]')
        ->assertPresent('[data-test="followup-panel"]')
        ->assertSeeIn('[data-test="followup-status-pill"]', 'Draft')
        ->fill('[data-test="followup-why"]', 'Volume produksi naik 20%')
        ->fill('[data-test="followup-how"]', 'Tambah 5 orang shift malam')
        ->select('[data-test="followup-who"]', (string) $pic->id)
        ->fill('[data-test="followup-cost"]', '7500000')
        ->click('[data-test="followup-save-draft"]')
        ->assertSeeIn('[data-test="followup-history"]', '#1')
        ->click('[data-test="followup-send"]')
        ->assertSeeIn('[data-test="followup-status-pill"]', 'Sent')
        ->assertPresent('[data-test="followup-sent-note"]')
        ->assertMissing('[data-test="followup-send"]');

    expect(AlertFollowUp::query()->where('status', 'sent')->count())->toBe(1);
});

test('sending without the cause and plan shows validation feedback and keeps the draft editable', function () {
    $dept = Department::factory()->create(['is_active' => true]);

    User::factory()->manager($dept->id)->create([
        'email' => 'manager.followup.invalid@factory.com',
        'password' => 'password',
    ]);

    visit('/')
        ->click('[data-test="welcome-enter-button"]')
        ->fill('email', 'manager.followup.invalid@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->click('[data-test="tab-distribution"]')
        ->click('[data-test="followup-send"]')
        ->assertSee('Explain the cause (Why) before sending.')
        ->assertSee('Describe the follow-up plan (How) before sending.')
        ->assertPresent('[data-test="followup-send"]');

    expect(AlertFollowUp::query()->count())->toBe(0);
});
