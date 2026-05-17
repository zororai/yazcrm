<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DistressDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DistressDomainController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('DistressDomains/Index', [
            'domains' => DistressDomain::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255|unique:distress_domains,name',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        DistressDomain::create([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => true,
        ]);

        return back()->with('success', 'Domain added.');
    }

    public function update(Request $request, DistressDomain $distressDomain): RedirectResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255|unique:distress_domains,name,' . $distressDomain->id,
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $distressDomain->update([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? $distressDomain->sort_order,
            'is_active'  => $data['is_active'] ?? $distressDomain->is_active,
        ]);

        return back()->with('success', 'Domain updated.');
    }

    public function destroy(DistressDomain $distressDomain): RedirectResponse
    {
        $distressDomain->delete();
        return back()->with('success', 'Domain removed.');
    }
}
