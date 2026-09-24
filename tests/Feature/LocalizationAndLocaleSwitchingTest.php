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
        ->where('translations', function ($translations): bool {
            $translations = collect($translations);
            expect($translations->get('SMARTIME (Smart Overtime) 2.0'))
                ->toBe('SMARTIME (Smart Overtime) 2.0')
                ->and($translations->get('Machine Learning-Based Decision Support System (M-DSS)'))
                ->toBe('Machine Learning-Based Decision Support System (M-DSS)')
                ->and($translations->get('Developer system by: KARLINA IBRAHIM (PCD Department)'))
                ->toBe('Developer system by: KARLINA IBRAHIM (PCD Department)')
                ->and($translations->get('Welcome to'))
                ->toBe('Selamat datang di');

            return true;
        })
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
        ->where('translations', function ($translations): bool {
            $translations = collect($translations);
            expect($translations->get('SMARTIME (Smart Overtime) 2.0'))
                ->toBe('SMARTIME (Smart Overtime) 2.0')
                ->and($translations->get('Machine Learning-Based Decision Support System (M-DSS)'))
                ->toBe('Machine Learning-Based Decision Support System (M-DSS)')
                ->and($translations->get('Welcome to'))
                ->toBe('Welcome to')
                ->and($translations->get('Developer system by: KARLINA IBRAHIM (PCD Department)'))
                ->toBe('Developer system by: KARLINA IBRAHIM (PCD Department)');

            return true;
        })
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
