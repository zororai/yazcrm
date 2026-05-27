<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LookupItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LookupItemController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type'       => ['required', Rule::in(array_keys(LookupItem::TYPES))],
            'name'       => ['required', 'string', 'max:255', Rule::unique('lookup_items')->where('type', $request->type)],
            'sort_order' => 'nullable|integer|min:0',
        ]);

        LookupItem::create([
            'type'       => $data['type'],
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => true,
        ]);

        return back()->with('success', 'Item added.');
    }

    public function update(Request $request, LookupItem $lookupItem): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255',
                Rule::unique('lookup_items')->where('type', $lookupItem->type)->ignore($lookupItem->id)],
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $lookupItem->update([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? $lookupItem->sort_order,
            'is_active'  => $data['is_active']  ?? $lookupItem->is_active,
        ]);

        return back()->with('success', 'Item updated.');
    }

    public function destroy(LookupItem $lookupItem): RedirectResponse
    {
        $lookupItem->delete();
        return back()->with('success', 'Item removed.');
    }
}
