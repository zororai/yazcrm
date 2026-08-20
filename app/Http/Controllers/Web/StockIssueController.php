<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StockIssueController extends Controller
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
            'department_id'     => 'nullable|exists:departments,id',
            'issued_to'         => 'nullable|string|max:255',
            'reason'            => 'nullable|string',
            'lines'             => 'required|array|min:1',
            'lines.*.item_id'   => 'required|exists:items,id',
            'lines.*.quantity'  => 'required|integer|min:1',
        ]);

        try {
            $this->stock->issueStock($store, $request->user(), $data['lines'], $data);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Stock issued.');
    }
}
