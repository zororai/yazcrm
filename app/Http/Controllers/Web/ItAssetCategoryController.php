<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ItAssetCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ItAssetCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Registry/Categories/Index', [
            'categories' => ItAssetCategory::with('parent:id,name')->withCount('assets')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|exists:it_asset_categories,id',
        ]);

        ItAssetCategory::create($data);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, ItAssetCategory $itAssetCategory): RedirectResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => ['nullable', 'exists:it_asset_categories,id', Rule::notIn([$itAssetCategory->id])],
        ]);

        $itAssetCategory->update($data);

        return back()->with('success', 'Saved.');
    }

    public function destroy(ItAssetCategory $itAssetCategory): RedirectResponse
    {
        $itAssetCategory->delete();

        return back()->with('success', 'Category deleted.');
    }
}
