<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
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

        return response()->json($query->paginate($request->get('per_page', 20)));
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json(
            $client->load([
                'calls' => fn($q) => $q->latest('started_at')->limit(10),
                'tickets' => fn($q) => $q->latest()->limit(5),
                'callbackQueue' => fn($q) => $q->where('status', 'pending'),
                'whatsappMessages' => fn($q) => $q->latest()->limit(20),
            ])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'phone'            => 'required|string|unique:clients,phone',
            'email'            => 'nullable|email',
            'company'          => 'nullable|string|max:255',
            'notes'            => 'nullable|string',
            'whatsapp_number'  => 'nullable|string',
        ]);

        $client = Client::create($request->only(['name', 'phone', 'email', 'company', 'notes', 'whatsapp_number']));
        return response()->json($client, 201);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $request->validate([
            'name'            => 'sometimes|string|max:255',
            'phone'           => "sometimes|string|unique:clients,phone,{$client->id}",
            'email'           => 'nullable|email',
            'company'         => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
            'whatsapp_number' => 'nullable|string',
            'status'          => 'in:active,inactive',
        ]);

        $client->update($request->only(['name', 'phone', 'email', 'company', 'notes', 'whatsapp_number', 'status']));
        return response()->json($client->fresh());
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->delete();
        return response()->json(['message' => 'Client deleted.']);
    }

    public function findByPhone(Request $request): JsonResponse
    {
        $phone  = $request->get('phone');
        $client = Client::where('phone', $phone)
            ->orWhere('whatsapp_number', $phone)
            ->first();

        return response()->json($client);
    }
}
