<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecordingController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Recording::with(['call.client:id,name', 'call.agent:id,name'])
            ->orderByDesc('created_at');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->whereHas('call', function ($q) use ($search) {
                $q->where('caller', 'like', "%{$search}%")
                  ->orWhere('callee', 'like', "%{$search}%");
            });
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $recordings = $query->paginate(20)->withQueryString();

        return Inertia::render('Recordings/Index', [
            'recordings' => $recordings,
            'filters'    => $request->only(['search', 'from', 'to']),
        ]);
    }
}
