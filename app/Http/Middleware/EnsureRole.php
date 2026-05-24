<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce that the authenticated user has one of the allowed roles.
 *
 * Usage in routes:
 *     ->middleware('role:admin')
 *     ->middleware('role:user,viewer')   // multiple roles allowed
 *
 * If the user doesn't match, they're redirected to THEIR role's landing
 * route rather than getting a confusing 403. This makes role separation
 * feel natural (e.g. an admin who clicks a /dashboard link is just sent
 * back to /admin/users).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // API / JSON callers want machine-readable errors, not redirects.
        $wantsJson = $request->expectsJson() || $request->is('api/*');

        if (! $user) {
            return $wantsJson
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }

        $allowed = array_map(static fn (string $r) => UserRole::from($r), $roles);

        if (! in_array($user->role, $allowed, true)) {
            return $wantsJson
                ? response()->json(['message' => 'This token is not authorised for that role.'], 403)
                : redirect()->route($user->role->landingRoute());
        }

        return $next($request);
    }
}
