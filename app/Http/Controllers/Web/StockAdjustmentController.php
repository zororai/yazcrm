<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function __construct(private readonly StockService $stock)
    {
    }

    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function store(Request $request, Store $store, Item $item): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'physical_quantity' => 'required|integer|min:0',
            'reason'            => 'required|string|in:damaged,expired,lost,found,counting_error,data_correction',
            'notes'             => 'nullable|string',
        ]);

        $this->stock->adjustStock(
            $store, $item, $request->user(),
            $data['physical_quantity'], $data['reason'], $data['notes'] ?? null
        );

        return back()->with('success', 'Stock adjusted.');
    }
}
