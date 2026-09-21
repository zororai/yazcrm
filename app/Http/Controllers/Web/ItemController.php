<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director', 'stores', 'accounting_dep'], true);
    }

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString() ?: null;

        return Inertia::render('Items/Index', [
            'items' => Item::with(['category:id,name', 'defaultStore:id,name'])
                ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->get(),
            'categories' => ItemCategory::orderBy('name')->get(['id', 'name']),
            'stores'     => Store::orderBy('name')->get(['id', 'name']),
            'isManager'  => $this->isManager($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'new_category_name'=> 'nullable|string|max:255',
            'description'      => 'nullable|string',
            'unit_of_measure'  => 'nullable|string|max:50',
            'minimum_stock'    => 'nullable|integer|min:0',
            'maximum_stock'    => 'nullable|integer|min:0',
            'reorder_level'    => 'nullable|integer|min:0',
            'default_store_id' => 'nullable|exists:stores,id',
        ]);

        unset($data['new_category_name']);
        $data['category_id'] = $this->resolveCategoryId($request);

        $item = Item::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('items.show', $item)->with('success', 'Item created.');
    }

    // Lets the item form create a category on the fly (picking "+ Add new
    // category…" and typing a name) instead of requiring a separate trip
    // to the Item Categories page first. firstOrCreate keeps this safe to
    // resubmit without creating duplicate categories of the same name.
    private function resolveCategoryId(Request $request): ?int
    {
        $raw = $request->input('category_id');

        if ($raw === '__new__') {
            $name = trim((string) $request->input('new_category_name', ''));
            abort_if($name === '', 422, 'Category name is required.');

            return ItemCategory::firstOrCreate(['name' => $name])->id;
        }

        if (empty($raw)) {
            return null;
        }

        abort_unless(ItemCategory::where('id', $raw)->exists(), 422, 'Invalid category.');

        return (int) $raw;
    }

    public function show(Request $request, Item $item): Response
    {
        return Inertia::render('Items/Show', [
            'item'      => $item->load(['category:id,name', 'defaultStore:id,name', 'creator:id,name']),
            'stock'     => $item->storeStock()->with('store:id,name')->get(),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'new_category_name'=> 'nullable|string|max:255',
            'description'      => 'nullable|string',
            'unit_of_measure'  => 'nullable|string|max:50',
            'minimum_stock'    => 'nullable|integer|min:0',
            'maximum_stock'    => 'nullable|integer|min:0',
            'reorder_level'    => 'nullable|integer|min:0',
            'default_store_id' => 'nullable|exists:stores,id',
            'is_active'        => 'sometimes|boolean',
        ]);

        unset($data['new_category_name']);
        $data['category_id'] = $this->resolveCategoryId($request);

        $item->update($data);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, Item $item): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $item->delete();

        return redirect()->route('items.index')->with('success', 'Item deleted.');
    }
}
