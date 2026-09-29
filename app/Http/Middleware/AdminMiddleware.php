<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // An active impersonation session can only exist because an admin
        // started it (see UserController::impersonate), so it carries the
        // same admin-level access while it's active.
        $isImpersonating = $request->session()->has('impersonator_id');

        if (!$request->user() || ($request->user()->role !== 'admin' && !$isImpersonating)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. Admin access required.'], 403);
            }
            abort(403);
        }

        return $next($request);
    }
}
