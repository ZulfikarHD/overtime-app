<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest is redirected to login when visiting preferences page', function () {
    $this->get(route('preferences.edit'))->assertRedirect(route('login'));
    $this->patch(route('preferences.update'), [])->assertRedirect(route('login'));
});

test('authenticated user can view preferences page with default values', function () {
    $user = User::factory()->create([
        'preferences' => null,
    ]);

    $response = $this->actingAs($user)->get(route('preferences.edit'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/Preferences')
        ->has('preferences')
        ->where('preferences.theme', 'system')
        ->where('preferences.spkl_pending_reminder', true)
        ->where('preferences.budget_threshold_alert', true)
        ->where('preferences.approval_status_notification', true)
    );
});

test('user can update theme and notification preferences', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('preferences.update'), [
        'theme' => 'dark',
        'spkl_pending_reminder' => false,
        'budget_threshold_alert' => true,
        'approval_status_notification' => false,
    ]);

    $response->assertRedirect(route('preferences.edit'));
    $response->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->preferences)->toBe([
        'theme' => 'dark',
        'spkl_pending_reminder' => false,
        'budget_threshold_alert' => true,
        'approval_status_notification' => false,
    ]);
});

test('updating preferences validates theme choice', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('preferences.update'), [
        'theme' => 'invalid-theme',
        'spkl_pending_reminder' => true,
        'budget_threshold_alert' => true,
        'approval_status_notification' => true,
    ]);

    $response->assertSessionHasErrors('theme');
});

test('updating preferences validates notification boolean fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('preferences.update'), [
        'theme' => 'light',
        'spkl_pending_reminder' => 'not-a-boolean',
        'budget_threshold_alert' => null,
        'approval_status_notification' => 'invalid',
    ]);

    $response->assertSessionHasErrors([
        'spkl_pending_reminder',
        'budget_threshold_alert',
        'approval_status_notification',
    ]);
});
