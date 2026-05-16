<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use App\Services\YeastarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtensionController extends Controller
{
    public function __construct(private YeastarService $yeastar) {}

    public function index(Request $request): JsonResponse
    {
        $query = Extension::with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('extension_number', 'like', "%{$request->search}%")
                  ->orWhere('name', 'like', "%{$request->search}%");
        }

        return response()->json($query->get());
    }

    public function show(Extension $extension): JsonResponse
    {
        return response()->json($extension->load('user'));
    }

    public function sync(): JsonResponse
    {
        $this->yeastar->syncExtensions();
        return response()->json(['message' => 'Extensions synced successfully.']);
    }

    public function assignUser(Request $request, Extension $extension): JsonResponse
    {
        $request->validate(['user_id' => 'nullable|exists:users,id']);
        $extension->update(['user_id' => $request->user_id]);
        return response()->json($extension->fresh()->load('user'));
    }

    public function update(Request $request, Extension $extension): JsonResponse
    {
        $request->validate([
            'name'              => 'sometimes|string|max:100',
            'caller_id_name'    => 'nullable|string|max:100',
            'email'             => 'nullable|email',
            'voicemail_enabled' => 'boolean',
        ]);

        $extension->update($request->only(['name', 'caller_id_name', 'email', 'voicemail_enabled']));
        return response()->json($extension->fresh()->load('user'));
    }
}
