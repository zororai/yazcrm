<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockReceiptController extends Controller
{
    public function __construct(private readonly StockService $stock)
    {
    }

    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function store(Request $request, Store $store): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'supplier_name'          => 'nullable|string|max:255',
            'reference_number'       => 'nullable|string|max:100',
            'notes'                  => 'nullable|string',
            'lines'                  => 'required|array|min:1',
            'lines.*.item_id'        => 'required|exists:items,id',
            'lines.*.quantity'       => 'required|integer|min:1',
            'lines.*.unit_cost'      => 'nullable|numeric|min:0',
        ]);

        $this->stock->receiveStock($store, $request->user(), $data['lines'], $data);

        return back()->with('success', 'Stock received.');
    }
}
