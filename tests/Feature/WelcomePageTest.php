<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest sees the welcome page at the root before login', function () {
    $this->withSession(['locale' => 'id'])
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('locale', 'id')
            ->where('translations.Hello, Welcome!', 'Halo, Selamat Datang!')
        );
});

test('login page stays available at /login', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
});

test('authenticated user visiting the root is redirected to the dashboard', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
