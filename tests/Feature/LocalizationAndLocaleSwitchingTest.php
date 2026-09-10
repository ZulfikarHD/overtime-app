<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('default application locale is id and shares indonesian translations to inertia', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/Login')
        ->where('locale', 'id')
        ->has('translations')
        ->where('translations.Log in to System', 'Masuk ke Sistem')
        ->where('translations.CIP CapEx Allocation', 'Alokasi CapEx CIP')
        ->where('translations.Statutory Overtime Limit', 'Batas Depnaker')
    );
});

test('guest can switch locale to english via post /locale', function () {
    $response = $this->post('/locale', [
        'locale' => 'en',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('locale', 'en');
    $response->assertPlainCookie('locale', 'en');

    $followResponse = $this->withSession(['locale' => 'en'])->get('/');
    $followResponse->assertOk();
    $followResponse->assertInertia(fn (Assert $page) => $page
        ->component('auth/Login')
        ->where('locale', 'en')
        ->where('translations.Log in to System', 'Log in to System')
        ->where('translations.CIP CapEx Allocation', 'CIP CapEx Allocation')
    );
});

test('authenticated user switching locale updates user preferences and cookie', function () {
    $user = User::factory()->admin()->create([
        'preferences' => ['theme' => 'light'],
    ]);

    $response = $this->actingAs($user)->post('/locale', [
        'locale' => 'en',
    ]);

    $response->assertRedirect();
    $response->assertPlainCookie('locale', 'en');

    $user->refresh();
    expect($user->locale)->toBe('en');
    expect($user->preferences['locale'])->toBe('en');
    expect($user->preferences['theme'])->toBe('light');

    $dashResponse = $this->actingAs($user)->get('/dashboard');
    $dashResponse->assertOk();
    $dashResponse->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('locale', 'en')
        ->where('translations.Operations & Overtime', 'Operations & Overtime')
        ->where('translations.Facility', 'Facility')
    );
});

test('locale switching rejects invalid locale codes', function () {
    $response = $this->post('/locale', [
        'locale' => 'fr',
    ]);

    $response->assertSessionHasErrors(['locale']);
});
