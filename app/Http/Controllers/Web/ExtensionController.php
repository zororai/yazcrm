<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\User;
use App\Services\YeastarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExtensionController extends Controller
{
    public function __construct(private YeastarService $yeastar) {}

    public function index(): Response
    {
        $extensions = Extension::with('user')->orderBy('extension_number')->get();
        $unassignedUsers = User::whereDoesntHave('extension')->where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Extensions/Index', [
            'extensions'      => $extensions,
            'unassignedUsers' => $unassignedUsers,
        ]);
    }

    public function sync(): RedirectResponse
    {
        $synced = $this->yeastar->syncExtensions();

        return back()->with('success', "Synced {$synced} extensions from PBX.");
    }

    public function update(Request $request, Extension $extension): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'status'         => 'in:active,inactive',
            'caller_id_name' => 'nullable|string|max:50',
            'sip_password'   => 'nullable|string|max:255',
            'sip_domain'     => 'nullable|string|max:255',
        ]);

        $extension->update($data);

        return back()->with('success', 'Extension updated.');
    }

    public function mySipConfig(Request $request): JsonResponse
    {
        $ext = Extension::where('user_id', $request->user()->id)->first();

        if (!$ext || !$ext->sip_password) {
            return response()->json(['configured' => false]);
        }

        $pbxHost   = parse_url(config('yeastar.base_url'), PHP_URL_HOST) ?? '192.168.10.150';
        $sipDomain = $ext->sip_domain ?: $pbxHost;

        return response()->json([
            'configured'       => true,
            'extension_number' => $ext->extension_number,
            'sip_password'     => $ext->sip_password,
            'sip_domain'       => $sipDomain,
            'ws_url'           => "wss://{$sipDomain}:8088/ws",
            'display_name'     => $ext->caller_id_name ?: $ext->name,
        ]);
    }

    public function assignUser(Request $request, Extension $extension): RedirectResponse
    {
        $request->validate(['user_id' => 'nullable|exists:users,id']);

        Extension::where('user_id', $request->user_id)->update(['user_id' => null]);
        $extension->update(['user_id' => $request->user_id]);

        return back()->with('success', 'Extension assigned.');
    }
}
