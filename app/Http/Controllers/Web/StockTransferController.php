<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Store;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class StockTransferController extends Controller
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
        return Inertia::render('StockTransfers/Index', [
            'transfers' => StockTransfer::with(['fromStore:id,name', 'toStore:id,name', 'requestedBy:id,name'])
                ->latest()
                ->get(),
            'stores'    => Store::orderBy('name')->get(['id', 'name']),
            'items'     => Item::where('is_active', true)->orderBy('name')->get(['id', 'item_code', 'name']),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'from_store_id'    => 'required|exists:stores,id',
            'to_store_id'      => 'required|exists:stores,id|different:from_store_id',
            'notes'            => 'nullable|string',
            'lines'            => 'required|array|min:1',
            'lines.*.item_id'  => 'required|exists:items,id',
            'lines.*.quantity' => 'required|integer|min:1',
        ]);

        $transfer = $this->stock->createTransfer(
            $request->user(), $data['from_store_id'], $data['to_store_id'], $data['lines'], $data['notes'] ?? null
        );

        return redirect()->route('stock-transfers.show', $transfer)->with('success', 'Transfer created.');
    }

    public function show(Request $request, StockTransfer $stockTransfer): Response
    {
        return Inertia::render('StockTransfers/Show', [
            'transfer' => $stockTransfer->load(['fromStore:id,name', 'toStore:id,name', 'requestedBy:id,name', 'items.item:id,name,item_code']),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function dispatch(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        try {
            $this->stock->dispatchTransfer($stockTransfer, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transfer dispatched.');
    }

    public function receive(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        try {
            $this->stock->receiveTransfer($stockTransfer, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transfer received.');
    }

    public function cancel(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        try {
            $this->stock->cancelTransfer($stockTransfer, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transfer cancelled.');
    }
}
