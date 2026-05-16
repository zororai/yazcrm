<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CallbackQueue;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallbackController extends Controller
{
    public function index(Request $request): Response
    {
        $query = CallbackQueue::with(['client', 'agent'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $callbacks = $query->paginate(20)->withQueryString();
        $clients   = Client::select('id', 'name', 'phone')->orderBy('name')->get();

        return Inertia::render('Callbacks/Index', [
            'callbacks' => $callbacks,
            'clients'   => $clients,
            'filters'   => $request->only(['status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'notes'     => 'nullable|string',
        ]);

        CallbackQueue::create([
            'client_id' => $request->client_id,
            'notes'     => $request->notes,
        ]);

        return back()->with('success', 'Callback queued.');
    }

    public function assign(Request $request, CallbackQueue $callbackQueue): RedirectResponse
    {
        $callbackQueue->update([
            'agent_id' => $request->user()->id,
            'status'   => 'assigned',
        ]);

        return back()->with('success', 'Callback assigned to you.');
    }

    public function complete(CallbackQueue $callbackQueue): RedirectResponse
    {
        $callbackQueue->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Callback marked complete.');
    }

    public function destroy(CallbackQueue $callbackQueue): RedirectResponse
    {
        $callbackQueue->delete();

        return back()->with('success', 'Callback removed.');
    }
}
