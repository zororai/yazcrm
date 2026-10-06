<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        $userId = $request->integer('user_id') ?: null;
        $method = $request->string('method')->toString() ?: null;
        $search = $request->string('search')->toString() ?: null;

        $hasDescription = Schema::hasColumn('audit_logs', 'description');

        $logs = AuditLog::with('user:id,name')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($method, fn ($q) => $q->where('method', $method))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search, $hasDescription) {
                $q->where('path', 'like', "%{$search}%")->orWhere('route_name', 'like', "%{$search}%");
                if ($hasDescription) {
                    $q->orWhere('description', 'like', "%{$search}%");
                }
            }))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('AuditLogs/Index', [
            'logs'  => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
