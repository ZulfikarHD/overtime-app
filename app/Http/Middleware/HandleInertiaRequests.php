<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            $user->loadMissing([
                'department:id,code,name',
                'section:id,department_id,code,name',
            ]);
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'locale' => app()->getLocale(),
            'translations' => $this->getTranslations(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                'toast' => $request->session()->get('toast'),
                'last_submission' => $request->session()->get('last_submission'),
            ],
        ];
    }

    /**
     * Get translations for the current active locale.
     *
     * @return array<string, string>
     */
    protected function getTranslations(): array
    {
        $locale = app()->getLocale();
        $path = base_path("lang/{$locale}.json");

        if (file_exists($path)) {
            $content = file_get_contents($path);

            return json_decode($content ?: '{}', true) ?: [];
        }

        return [];
    }
}
