<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Logs every state-changing request (POST/PUT/PATCH/DELETE) app-wide — who did
// what, when, from where — regardless of which controller/feature handled it.
class RecordAuditTrail
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $user = $request->user();

            AuditLog::create([
                'user_id'     => $user?->id,
                'user_name'   => $user?->name,
                'method'      => $request->method(),
                'route_name'  => $request->route()?->getName(),
                'path'        => '/'.ltrim($request->path(), '/'),
                'status_code' => $response->getStatusCode(),
                'ip_address'  => $request->ip(),
                'user_agent'  => substr((string) $request->userAgent(), 0, 255),
            ]);
        }

        return $response;
    }
}
