<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\User;
use App\Services\YeastarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExtensionController extends Controller
{
    public function __construct(private YeastarService $yeastar) {}

    public function index(): Response
    {
        $extensions = Extension::with('user')->orderBy('number')->get();
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
            'name'        => 'sometimes|string|max:255',
            'status'      => 'in:active,inactive',
            'caller_id'   => 'nullable|string|max:50',
        ]);

        $extension->update($data);

        return back()->with('success', 'Extension updated.');
    }

    public function assignUser(Request $request, Extension $extension): RedirectResponse
    {
        $request->validate(['user_id' => 'nullable|exists:users,id']);

        Extension::where('user_id', $request->user_id)->update(['user_id' => null]);
        $extension->update(['user_id' => $request->user_id]);

        return back()->with('success', 'Extension assigned.');
    }
}
