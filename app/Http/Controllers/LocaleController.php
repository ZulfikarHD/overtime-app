<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Update the current application locale for authenticated or guest sessions.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:id,en'],
        ]);

        $locale = $validated['locale'];

        if ($user = $request->user()) {
            $preferences = is_array($user->preferences) ? $user->preferences : [];
            $preferences['locale'] = $locale;
            $user->update([
                'preferences' => $preferences,
            ]);
        }

        $request->session()->put('locale', $locale);
        cookie()->queue('locale', $locale, 60 * 24 * 365);

        return back();
    }
}
