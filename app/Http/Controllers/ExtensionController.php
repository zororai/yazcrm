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
            'sip_password'      => 'nullable|string|max:255',
            'sip_domain'        => 'nullable|string|max:255',
        ]);

        $extension->update($request->only([
            'name', 'caller_id_name', 'email', 'voicemail_enabled',
            'sip_password', 'sip_domain',
        ]));
        return response()->json($extension->fresh()->load('user'));
    }

    // Returns the SIP credentials for the authenticated user's extension.
    // Only served over HTTPS — never expose SIP passwords over plain HTTP.
    public function mySipConfig(Request $request): JsonResponse
    {
        $ext = Extension::where('user_id', $request->user()->id)->first();

        if (!$ext || !$ext->sip_password) {
            return response()->json(['configured' => false], 200);
        }

        $pbxHost = parse_url(config('yeastar.base_url'), PHP_URL_HOST)
            ?? config('yeastar.base_url');

        return response()->json([
            'configured'       => true,
            'extension_number' => $ext->extension_number,
            'sip_password'     => $ext->sip_password,
            'sip_domain'       => $ext->sip_domain ?: $pbxHost,
            'ws_url'           => "wss://{$pbxHost}:8088/ws",
            'display_name'     => $ext->caller_id_name ?: $ext->name,
        ]);
    }
}
