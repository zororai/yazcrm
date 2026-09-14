<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director', 'stores', 'accounting_dep'], true);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Locations/Index', [
            'locations' => Location::withCount('stores')->orderBy('name')->get(),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string',
        ]);

        Location::create($data);

        return back()->with('success', 'Location created.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'address'   => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $location->update($data);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $location->delete();

        return back()->with('success', 'Location deleted.');
    }
}
