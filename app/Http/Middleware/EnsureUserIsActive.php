<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

// auth/login already refuses disabled accounts, but a token issued before the
// account was disabled stays valid for up to 30 days. This closes that: the
// token is revoked and the app gets the same 401 shape as an expired login,
// so it sends the user back to the login screen (which then says why).
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            // A cookie session (statefulApi) has a TransientToken, nothing to revoke.
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json([
                'code'    => 'account_disabled',
                'message' => 'Your account has been disabled. Contact your administrator.',
            ], 401);
        }

        return $next($request);
    }
}
