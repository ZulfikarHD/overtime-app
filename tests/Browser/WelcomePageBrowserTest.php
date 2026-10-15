<?php

use App\Models\User;

test('guest flows from welcome page to login page and then to the dashboard', function () {
    User::factory()->admin()->create([
        'name' => 'Welcome Flow Admin',
        'email' => 'welcome.flow@factory.com',
        'password' => 'password',
    ]);

    visit('/')
        ->click('[data-test="lang-switch-welcome-id"]')
        ->assertSee('Halo, Selamat Datang!')
        ->assertSee('Karlina Ibrahim')
        ->assertPresent('[data-test="welcome-photo"]')
        ->click('[data-test="welcome-enter-button"]')
        ->assertPathIs('/login')
        ->fill('email', 'welcome.flow@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();
});
