<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceProviderController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ServiceProvider::orderBy('chapter')->orderBy('organisation');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('organisation', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('physical_address', 'like', "%{$search}%")
                  ->orWhere('services_offered', 'like', "%{$search}%");
            });
        }

        if ($request->filled('chapter')) {
            $query->where('chapter', $request->chapter);
        }

        return Inertia::render('ServiceProviders/Index', [
            'providers' => $query->get(),
            'chapters'  => ServiceProvider::whereNotNull('chapter')->distinct()->orderBy('chapter')->pluck('chapter'),
            'filters'   => $request->only(['search', 'chapter']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'organisation'     => 'required|string|max:255',
            'phone'            => 'nullable|string|max:100',
            'email'            => 'nullable|email|max:255',
            'physical_address' => 'nullable|string|max:500',
            'services_offered' => 'nullable|string',
            'notes'            => 'nullable|string|max:255',
            'chapter'          => 'nullable|string|max:100',
        ]);

        ServiceProvider::create($data);

        return back()->with('success', 'Service provider added.');
    }

    public function update(Request $request, ServiceProvider $serviceProvider): RedirectResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'organisation'     => 'required|string|max:255',
            'phone'            => 'nullable|string|max:100',
            'email'            => 'nullable|email|max:255',
            'physical_address' => 'nullable|string|max:500',
            'services_offered' => 'nullable|string',
            'notes'            => 'nullable|string|max:255',
            'chapter'          => 'nullable|string|max:100',
        ]);

        $serviceProvider->update($data);

        return back()->with('success', 'Service provider updated.');
    }

    public function destroy(ServiceProvider $serviceProvider): RedirectResponse
    {
        $serviceProvider->delete();

        return back()->with('success', 'Service provider removed.');
    }
}
