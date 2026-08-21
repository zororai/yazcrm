<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DataCollectionForm;
use App\Models\DataCollectionFormVersion;
use App\Models\User;
use App\Services\DataCollection\DataCollectionFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DataCollectionFormVersionController extends Controller
{
    public function __construct(private readonly DataCollectionFormService $service)
    {
    }

    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function store(Request $request, DataCollectionForm $form): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        try {
            $this->service->createNewVersion($form, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'New draft version created.');
    }

    public function update(Request $request, DataCollectionForm $form, DataCollectionFormVersion $version): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'schema'               => 'required|array',
            'schema.title'         => 'nullable|string|max:255',
            'schema.sections'      => 'array',
        ]);

        try {
            $this->service->updateVersionSchema($version, $request->user(), $data['schema']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Draft saved.');
    }

    public function publish(Request $request, DataCollectionForm $form, DataCollectionFormVersion $version): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        try {
            $this->service->publishVersion($version, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Form version published.');
    }
}
