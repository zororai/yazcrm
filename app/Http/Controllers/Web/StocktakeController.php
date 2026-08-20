<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Stocktake;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class StocktakeController extends Controller
{
    public function __construct(private readonly StockService $stock)
    {
    }

    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Stocktakes/Index', [
            'stocktakes' => Stocktake::with(['store:id,name', 'startedBy:id,name'])->latest()->get(),
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
            'store_id' => 'required|exists:stores,id',
        ]);

        $store = Store::findOrFail($data['store_id']);
        $stocktake = $this->stock->startStocktake($store, $request->user());

        return redirect()->route('stocktakes.show', $stocktake)->with('success', 'Stocktake started.');
    }

    public function show(Request $request, Stocktake $stocktake): Response
    {
        return Inertia::render('Stocktakes/Show', [
            'stocktake' => $stocktake->load(['store:id,name', 'startedBy:id,name', 'completedBy:id,name', 'items.item:id,name,item_code,unit_of_measure']),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function update(Request $request, Stocktake $stocktake): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'counts'   => 'required|array',
            'counts.*' => 'nullable|integer|min:0',
        ]);

        try {
            $this->stock->recordCounts($stocktake, $data['counts']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Counts saved.');
    }

    public function complete(Request $request, Stocktake $stocktake): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        try {
            $this->stock->completeStocktake($stocktake, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Stocktake completed. Variances have been posted as adjustments.');
    }
}
