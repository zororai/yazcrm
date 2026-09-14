<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Item;
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
        $stores = Store::with(['location:id,name', 'manager:id,name', 'storekeeper:id,name'])
            ->orderBy('name')
            ->get()
            ->each(function ($store) {
                // An item counts towards a store's "items in stock" if it's
                // that item's Default Store, or if it already has an actual
                // stock row there (e.g. received manually without being the
                // default) — the same union used on the store's own page.
                $store->items_in_stock_count = Item::where('is_active', true)
                    ->where(function ($q) use ($store) {
                        $q->where('default_store_id', $store->id)
                            ->orWhereHas('storeStock', fn ($q2) => $q2->where('store_id', $store->id));
                    })
                    ->count();
            });

        return Inertia::render('Stores/Index', [
            'stores'    => $stores,
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
        // Show every item whose Default Store is this one, plus any item
        // that already has an actual stock row here (received manually
        // even without being the default) — items default-stored elsewhere
        // don't clutter this list until stock is actually moved/received in.
        $existingStock = $store->stock()->with('item:id,name,unit_of_measure')->get()->keyBy('item_id');
        // Full catalog — used for the Receive/Issue Stock dropdowns, which
        // should allow any active item, not just ones already tied here.
        $allActiveItems = Item::where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit_of_measure', 'default_store_id']);

        $displayItems = $allActiveItems->filter(
            fn (Item $item) => $item->default_store_id === $store->id || $existingStock->has($item->id)
        )->values();

        $stock = $displayItems->map(function (Item $item) use ($existingStock) {
            $row = $existingStock->get($item->id);

            return [
                'id'                 => $row?->id,
                'item'               => ['id' => $item->id, 'name' => $item->name, 'unit_of_measure' => $item->unit_of_measure],
                'quantity'           => $row->quantity ?? 0,
                'reserved_quantity'  => $row->reserved_quantity ?? 0,
                'available_quantity' => $row->available_quantity ?? 0,
            ];
        })->values();

        return Inertia::render('Stores/Show', [
            'store'       => $store->load(['location:id,name', 'manager:id,name', 'storekeeper:id,name']),
            'stock'       => $stock,
            'items'       => $allActiveItems->map->only(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'isManager'   => $this->isManager($request->user()),
        ]);
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
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
