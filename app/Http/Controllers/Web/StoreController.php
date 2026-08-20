<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Stores/Index', [
            'stores'    => Store::with(['location:id,name', 'manager:id,name', 'storekeeper:id,name'])
                ->withCount('stock')
                ->orderBy('name')
                ->get(),
            'locations' => Location::orderBy('name')->get(['id', 'name']),
            'users'     => User::orderBy('name')->get(['id', 'name']),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'code'            => 'required|string|max:50|unique:stores,code',
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'location_id'     => 'required|exists:locations,id',
            'manager_id'      => 'nullable|exists:users,id',
            'storekeeper_id'  => 'nullable|exists:users,id',
        ]);

        $created = Store::create($data);

        return redirect()->route('stores.show', $created)->with('success', 'Store created.');
    }

    public function show(Request $request, Store $store): Response
    {
        return Inertia::render('Stores/Show', [
            'store' => $store->load(['location:id,name', 'manager:id,name', 'storekeeper:id,name']),
            'stock' => $store->stock()->with('item:id,item_code,name,unit_of_measure')->get(),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'code'           => "required|string|max:50|unique:stores,code,{$store->id}",
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'location_id'    => 'required|exists:locations,id',
            'manager_id'     => 'nullable|exists:users,id',
            'storekeeper_id' => 'nullable|exists:users,id',
            'is_active'      => 'sometimes|boolean',
        ]);

        $store->update($data);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, Store $store): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $store->delete();

        return redirect()->route('stores.index')->with('success', 'Store deleted.');
    }
}
