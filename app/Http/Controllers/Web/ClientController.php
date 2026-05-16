<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Client::withCount('calls');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('company', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $clients = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Clients/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'phone'           => 'required|string|unique:clients,phone',
            'email'           => 'nullable|email',
            'company'         => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
            'whatsapp_number' => 'nullable|string',
        ]);

        $client = Client::create($data);

        return redirect()->route('clients.show', $client)->with('success', 'Client created successfully.');
    }

    public function show(Client $client): Response
    {
        $client->load([
            'calls'            => fn ($q) => $q->with('agent')->latest('started_at')->limit(10),
            'tickets'          => fn ($q) => $q->with('agent')->latest()->limit(5),
            'callbackQueue'    => fn ($q) => $q->with('agent')->where('status', 'pending'),
            'whatsappMessages' => fn ($q) => $q->latest()->limit(20),
        ]);

        return Inertia::render('Clients/Show', ['client' => $client]);
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('Clients/Edit', ['client' => $client]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'phone'           => "sometimes|string|unique:clients,phone,{$client->id}",
            'email'           => 'nullable|email',
            'company'         => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
            'whatsapp_number' => 'nullable|string',
            'status'          => 'in:active,inactive',
        ]);

        $client->update($data);

        return redirect()->route('clients.show', $client)->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Client deleted.');
    }
}
