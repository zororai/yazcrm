<?php

namespace App\Services\Leave;

use App\Models\AuditLog;
use Illuminate\Http\Request;

// Writes the readable before/after line for a leave correction into the
// CRM's audit trail. On web routes RecordAuditTrail already logs the request
// and picks the line up from `audit_description`; API routes don't run that
// middleware, so the entry is written here.
class LeaveAuditor
{
    public static function record(Request $request, string $description): void
    {
        if (! $request->is('api/*')) {
            // A FormRequest is a copy of the request with its own attribute
            // bag; the middleware reads the original one.
            request()->attributes->set('audit_description', $description);

            return;
        }

        $user = $request->user();

        AuditLog::create([
            'user_id'     => $user?->id,
            'user_name'   => $user?->name,
            'method'      => $request->method(),
            'route_name'  => $request->route()?->getName(),
            'path'        => '/'.ltrim($request->path(), '/'),
            'description' => $description,
            'status_code' => 200,
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
