<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Server-side counterpart of the sidebar's can(key): admins always pass,
// everyone else needs at least one of the given nav permission keys.
// Usage: ->middleware('nav:analytics') or 'nav:by_project,domains'.
class EnsureNavPermission
{
    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        $user = $request->user();

        $allowed = $user && ($user->role === 'admin'
            || array_intersect($keys, $user->nav_permissions ?? []) !== []);

        abort_unless($allowed, 403);

        return $next($request);
    }
}
