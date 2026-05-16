<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Client;
use App\Services\YeastarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallController extends Controller
{
    public function __construct(private YeastarService $yeastar) {}

    public function index(Request $request): Response
    {
        $query = Call::with(['client', 'agent', 'recording'])->latest('started_at');

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
            'calls'   => $calls,
            'filters' => $request->only(['direction', 'status', 'search', 'date_from', 'date_to']),
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
