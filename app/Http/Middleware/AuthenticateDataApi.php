<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// Auth for the analyst Data API (/api/data/*). Like KoboToolbox, it accepts
// either an API token ("Authorization: Bearer <token>" from /api/auth/login)
// or the user's CRM email + password as HTTP Basic auth — the latter is what
// Power BI / Excel "From Web" connectors offer out of the box. Stateless:
// nothing is stored in a session.
class AuthenticateDataApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->bearerToken()
            ? Auth::guard('sanctum')->user()
            : (Auth::onceBasic('email') === null ? Auth::user() : null);

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401, [
                'WWW-Authenticate' => 'Basic realm="YazCRM Data API"',
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is disabled.'], 403);
        }

        $allowed = in_array($user->role, ['admin', 'director'], true)
            || in_array('data_api', $user->nav_permissions ?? [], true);

        if (! $allowed) {
            return response()->json(['message' => 'You do not have Data API access.'], 403);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
