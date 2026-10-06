<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

// Logs every state-changing request (POST/PUT/PATCH/DELETE) app-wide — who did
// what, when, from where — regardless of which controller/feature handled it.
// A controller can add a readable summary of the change by setting the
// request attribute `audit_description` (see FixedAssetController@update).
class RecordAuditTrail
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $user = $request->user();

            $entry = [
                'user_id'     => $user?->id,
                'user_name'   => $user?->name,
                'method'      => $request->method(),
                'route_name'  => $request->route()?->getName(),
                'path'        => '/'.ltrim($request->path(), '/'),
                'status_code' => $response->getStatusCode(),
                'ip_address'  => $request->ip(),
                'user_agent'  => substr((string) $request->userAgent(), 0, 255),
            ];

            // Only touch the description column when there is one to write, and
            // only once its migration has run — so a deploy that lands before
            // `migrate` can't break every write request in the app.
            $description = $request->attributes->get('audit_description');
            if ($description && Schema::hasColumn('audit_logs', 'description')) {
                $entry['description'] = $description;
            }

            AuditLog::create($entry);
        }

        return $response;
    }
}
