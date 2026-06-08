<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Risk;
use App\Support\RiskScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RiskController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Risk::with(['asset', 'controls', 'priorityAction'])->latest();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('band')) {
            $band = $request->band;
            $query->where(function ($q) use ($band) {
                match ($band) {
                    'red'   => $q->where('residual_score', '>=', 15),
                    'amber' => $q->whereBetween('residual_score', [7, 14]),
                    'green' => $q->where('residual_score', '<', 7),
                    default => null,
                };
            });
        }

        $risks  = $query->paginate(25)->withQueryString();
        $assets = Asset::select('id', 'asset_tag', 'name')->orderBy('name')->get();

        return Inertia::render('Risk/Index', [
            'risks'   => $risks,
            'assets'  => $assets,
            'filters' => $request->only(['category', 'band']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_id'   => 'nullable|exists:assets,id',
            'risk_ref'   => 'required|string|max:100|unique:risks,risk_ref',
            'category'   => 'required|in:infrastructure,software,data_protection,cybersecurity,continuity,people_process',
            'description' => 'required|string',
            'cause'      => 'nullable|string',
            'likelihood' => 'required|integer|min:1|max:5',
            'impact'     => 'required|integer|min:1|max:5',
            'risk_owner' => 'nullable|string|max:255',
        ]);

        Risk::create($data);

        return back()->with('success', 'Risk added.');
    }

    public function update(Request $request, Risk $risk): RedirectResponse
    {
        $data = $request->validate([
            'asset_id'   => 'nullable|exists:assets,id',
            'risk_ref'   => 'required|string|max:100|unique:risks,risk_ref,' . $risk->id,
            'category'   => 'required|in:infrastructure,software,data_protection,cybersecurity,continuity,people_process',
            'description' => 'required|string',
            'cause'      => 'nullable|string',
            'likelihood' => 'required|integer|min:1|max:5',
            'impact'     => 'required|integer|min:1|max:5',
            'risk_owner' => 'nullable|string|max:255',
        ]);

        $risk->update($data);

        return back()->with('success', 'Risk updated.');
    }

    public function destroy(Risk $risk): RedirectResponse
    {
        $risk->delete();

        return back()->with('success', 'Risk deleted.');
    }
}
