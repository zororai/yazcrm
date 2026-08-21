<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DataCollectionForm;
use App\Models\User;
use App\Services\DataCollection\DataCollectionSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DataCollectionAssignmentController extends Controller
{
    public function __construct(private readonly DataCollectionSubmissionService $service)
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

        $data = $request->validate([
            'assigned_to' => 'required|exists:users,id',
            'due_date'    => 'nullable|date',
        ]);

        try {
            $this->service->assignForm($form, $request->user(), $data['assigned_to'], $data['due_date'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Form assigned.');
    }
}
