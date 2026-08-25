<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssetCategoryController extends Controller
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('AssetCategories/Index', [
            'categories' => AssetCategory::with('parent:id,name')->withCount('fixedAssets')->orderBy('name')->get(),
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
            'parent_id' => 'nullable|exists:asset_categories,id',
        ]);

        AssetCategory::create($data);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, AssetCategory $assetCategory): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => ['nullable', 'exists:asset_categories,id', Rule::notIn([$assetCategory->id])],
        ]);

        $assetCategory->update($data);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, AssetCategory $assetCategory): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $assetCategory->delete();

        return back()->with('success', 'Category deleted.');
    }
}
