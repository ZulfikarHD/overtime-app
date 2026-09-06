<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePreferencesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreferencesController extends Controller
{
    /**
     * Show the user preferences page.
     */
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Preferences', [
            'preferences' => $user->getEffectivePreferences(),
        ]);
    }

    /**
     * Update the user preferences.
     */
    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update([
            'preferences' => $request->validated(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Preferences updated successfully.'),
        ]);

        return to_route('preferences.edit');
    }
}
