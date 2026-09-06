<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if (! $user->is_active) {
            abort(403, 'Your account has been deactivated. Please contact the administrator.');
        }

        if (empty($roles)) {
            return $next($request);
        }

        // Expand any comma-separated roles passed like 'role:admin,manager'
        $parsedRoles = [];
        foreach ($roles as $role) {
            foreach (explode(',', $role) as $r) {
                $trimmed = trim($r);
                if ($trimmed !== '') {
                    $parsedRoles[] = $trimmed;
                }
            }
        }

        if ($user->hasRole($parsedRoles)) {
            return $next($request);
        }

        $roleLabel = $user->role instanceof UserRole ? $user->role->label() : (string) $user->role;

        abort(403, "Your account ({$roleLabel}) does not have permission to view this section.");
    }
}
