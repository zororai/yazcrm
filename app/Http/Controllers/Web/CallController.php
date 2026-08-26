<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Client;
use App\Services\YeastarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class CallController extends Controller
{
    public function __construct(private YeastarService $yeastar) {}

    public function index(Request $request): Response
    {
        $user  = $request->user();
        $query = Call::with(['client', 'agent', 'recording'])->latest('started_at');

        // Agents only see their own calls via their assigned extension.
        // Admins, directors, and helpline managers see everything.
        if (! in_array($user->role, ['admin', 'director', 'helpline_manager'], true)) {
            $extNumber = \App\Models\Extension::where('user_id', $user->id)->value('extension_number');
            if ($extNumber) {
                $query->where('extension_number', $extNumber);
            } else {
                // Agent has no extension assigned — show nothing
                $query->whereRaw('0 = 1');
            }
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('caller', 'like', "%{$request->search}%")
                  ->orWhere('callee', 'like', "%{$request->search}%")
                  ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$request->search}%"));
            });
        }
        if ($request->filled('date_from')) {
            $query->where('started_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('started_at', '<=', $request->date_to . ' 23:59:59');
        }

        $calls = $query->paginate(25)->withQueryString();

        return Inertia::render('Calls/Index', [
            'calls'      => $calls,
            'filters'    => $request->only(['direction', 'status', 'search', 'date_from', 'date_to']),
            'is_agent'   => ! in_array($user->role, ['admin', 'director', 'helpline_manager'], true),
        ]);
    }

    public function export(Request $request): HttpResponse
    {
        $user  = $request->user();
        $query = Call::with(['client', 'agent'])->latest('started_at');

        if (! in_array($user->role, ['admin', 'director', 'helpline_manager'], true)) {
            $extNumber = \App\Models\Extension::where('user_id', $user->id)->value('extension_number');
            if ($extNumber) {
                $query->where('extension_number', $extNumber);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('caller', 'like', "%{$request->search}%")
                  ->orWhere('callee', 'like', "%{$request->search}%")
                  ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$request->search}%"));
            });
        }
        if ($request->filled('date_from')) {
            $query->where('started_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('started_at', '<=', $request->date_to . ' 23:59:59');
        }

        $csvHeaders = [
            'ID', 'Started At', 'Ended At', 'Direction', 'Status',
            'Caller', 'Callee', 'Duration', 'Extension', 'Agent', 'Client',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $csvHeaders);

        $query->chunk(200, function ($chunk) use ($handle) {
            foreach ($chunk as $c) {
                fputcsv($handle, [
                    $c->id,
                    $c->started_at?->format('Y-m-d H:i:s'),
                    $c->ended_at?->format('Y-m-d H:i:s'),
                    $c->direction ?? '',
                    $c->status ?? '',
                    $c->caller ?? '',
                    $c->callee ?? '',
                    $c->duration_formatted ?? '',
                    $c->extension_number ?? '',
                    $c->agent?->name ?? '',
                    $c->client?->name ?? '',
                ]);
            }
        });

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'calls-export-' . now()->format('Y-m-d') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store',
        ]);
    }

    public function show(Call $call): Response
    {
        $call->load(['client', 'agent', 'recording', 'ticket', 'callbackQueue.agent']);

        $clients = Client::select('id', 'name', 'phone')->orderBy('name')->get();

        return Inertia::render('Calls/Show', [
            'call'    => $call,
            'clients' => $clients,
        ]);
    }

    public function linkClient(Request $request, Call $call): RedirectResponse
    {
        $request->validate(['client_id' => 'required|exists:clients,id']);
        $call->update(['client_id' => $request->client_id]);

        return back()->with('success', 'Client linked successfully.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $synced = $this->yeastar->syncCalls(
            $request->get('start_time'),
            $request->get('end_time')
        );

        return back()->with('success', "Synced {$synced} calls from PBX.");
    }
}
