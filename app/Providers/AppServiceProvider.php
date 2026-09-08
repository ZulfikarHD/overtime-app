<?php

namespace App\Providers;

use App\Listeners\UpdateUserLastLogin;
use App\Models\Department;
use App\Models\Section;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! function_exists('bcadd')) {
            require_once __DIR__.'/../Support/bcmath_polyfill.php';
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();

        Event::listen(Login::class, UpdateUserLastLogin::class);
    }

    /**
     * Configure role-based authorization gates.
     */
    protected function configureAuthorization(): void
    {
        // Role Gates
        Gate::define('is-admin', fn (User $user): bool => $user->isAdmin());
        Gate::define('is-manager', fn (User $user): bool => $user->isManager());
        Gate::define('is-team-leader', fn (User $user): bool => $user->isTeamLeader());
        Gate::define('is-user', fn (User $user): bool => $user->isUser());

        // Capability Gates per Role Permission Matrix
        Gate::define('manage-users', fn (User $user): bool => $user->isAdmin());
        Gate::define('create-overtime', fn (User $user): bool => $user->isAdmin() || $user->isTeamLeader());
        Gate::define('approve-overtime', fn (User $user): bool => $user->isAdmin() || $user->isManager());
        Gate::define('view-all-sections', fn (User $user): bool => $user->isAdmin());
        Gate::define('view-personal-report', fn (User $user): bool => true);
        Gate::define('view-ml-dashboard', fn (User $user): bool => $user->isAdmin() || $user->isManager());
        Gate::define('configure-policy', fn (User $user): bool => $user->isAdmin());

        // Scoping Gates
        Gate::define('view-section-overtime', fn (User $user, int|Section $section): bool => $user->canAccessSection($section));
        Gate::define('view-department-overtime', fn (User $user, int|Department $department): bool => $user->canAccessDepartment($department));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
