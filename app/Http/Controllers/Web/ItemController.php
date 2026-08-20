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
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString() ?: null;

        return Inertia::render('Items/Index', [
            'items' => Item::with(['category:id,name', 'defaultStore:id,name'])
                ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%");
                }))
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
            'item_code'        => 'required|string|max:100|unique:items,item_code',
            'name'             => 'required|string|max:255',
            'category_id'      => 'nullable|exists:item_categories,id',
            'description'      => 'nullable|string',
            'unit_of_measure'  => 'nullable|string|max:50',
            'minimum_stock'    => 'nullable|integer|min:0',
            'maximum_stock'    => 'nullable|integer|min:0',
            'reorder_level'    => 'nullable|integer|min:0',
            'default_store_id' => 'nullable|exists:stores,id',
        ]);

        $item = Item::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('items.show', $item)->with('success', 'Item created.');
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
            'item_code'        => "required|string|max:100|unique:items,item_code,{$item->id}",
            'name'             => 'required|string|max:255',
            'category_id'      => 'nullable|exists:item_categories,id',
            'description'      => 'nullable|string',
            'unit_of_measure'  => 'nullable|string|max:50',
            'minimum_stock'    => 'nullable|integer|min:0',
            'maximum_stock'    => 'nullable|integer|min:0',
            'reorder_level'    => 'nullable|integer|min:0',
            'default_store_id' => 'nullable|exists:stores,id',
            'is_active'        => 'sometimes|boolean',
        ]);

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
