<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ItemCategoryController extends Controller
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('ItemCategories/Index', [
            'categories' => ItemCategory::with('parent:id,name')->withCount('items')->orderBy('name')->get(),
            'isManager'  => $this->isManager($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|exists:item_categories,id',
        ]);

        ItemCategory::create($data);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, ItemCategory $itemCategory): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => ['nullable', 'exists:item_categories,id', Rule::notIn([$itemCategory->id])],
        ]);

        $itemCategory->update($data);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, ItemCategory $itemCategory): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $itemCategory->delete();

        return back()->with('success', 'Category deleted.');
    }
}
