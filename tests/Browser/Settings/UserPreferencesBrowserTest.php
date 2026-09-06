<?php

use App\Models\User;

test('authenticated user can navigate to preferences and update preferences', function () {
    $user = User::factory()->create([
        'name' => 'Budi Santoso',
        'email' => 'pref.browser.user@factory.com',
        'password' => 'password',
    ]);

    visit('/login')
        ->fill('email', 'pref.browser.user@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        ->click('Budi Santoso')
        ->click('Settings')
        ->assertPathIs('/settings/profile')
        ->click('Preferences')
        ->assertPathIs('/settings/preferences')
        ->assertSee('Theme Mode')
        ->assertSee('Notification Channels & Alerts')
        ->assertSee('Plant Standards & Global Formats')
        ->assertSee('WIB (Asia/Jakarta)')
        ->click('[data-test="theme-option-dark"]')
        ->click('[data-test="save-preferences-button"]')
        ->assertPathIs('/settings/preferences');

    $user->refresh();
    expect($user->preferences['theme'])->toBe('dark');
});
