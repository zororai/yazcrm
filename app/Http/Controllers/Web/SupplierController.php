<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Suppliers/Index', [
            'suppliers' => Supplier::withCount('purchaseOrders')->orderBy('name')->get(),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'supplier_code'   => 'required|string|max:50|unique:suppliers,supplier_code',
            'name'            => 'required|string|max:255',
            'contact_person'  => 'nullable|string|max:255',
            'phone'           => 'nullable|string|max:50',
            'email'           => 'nullable|email|max:255',
            'address'         => 'nullable|string',
            'tax_number'      => 'nullable|string|max:100',
            'payment_terms'   => 'nullable|string|max:255',
        ]);

        Supplier::create($data + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Supplier created.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'supplier_code'   => "required|string|max:50|unique:suppliers,supplier_code,{$supplier->id}",
            'name'            => 'required|string|max:255',
            'contact_person'  => 'nullable|string|max:255',
            'phone'           => 'nullable|string|max:50',
            'email'           => 'nullable|email|max:255',
            'address'         => 'nullable|string',
            'tax_number'      => 'nullable|string|max:100',
            'payment_terms'   => 'nullable|string|max:255',
            'status'          => 'sometimes|string|in:active,inactive',
        ]);

        $supplier->update($data);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $supplier->delete();

        return back()->with('success', 'Supplier deleted.');
    }
}
