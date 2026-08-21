<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DataCollectionForm;
use App\Models\DataCollectionProject;
use App\Models\User;
use App\Services\DataCollection\DataCollectionFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DataCollectionFormController extends Controller
{
    public function __construct(private readonly DataCollectionFormService $service)
    {
    }

    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function store(Request $request, DataCollectionProject $project): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'code'        => 'required|string|max:50|unique:data_collection_forms,code',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $form = $this->service->createForm($project, $request->user(), $data);

        return redirect()->route('data-collection.forms.show', $form)->with('success', 'Form created.');
    }

    public function show(Request $request, DataCollectionForm $form): Response
    {
        $isManager = $this->isManager($request->user());

        return Inertia::render('DataCollection/Forms/Show', [
            'form'        => $form->load('project:id,name'),
            'versions'    => $form->versions()->with('publishedBy:id,name')->get(),
            'assignments' => $isManager ? $form->assignments()->with(['assignee:id,name', 'formVersion:id,version_number'])->latest()->get() : [],
            'submissions' => $isManager
                ? $form->submissions()->with('submittedBy:id,name')->latest()->limit(50)->get()
                : $form->submissions()->where('submitted_by', $request->user()->id)->latest()->get(),
            'users'       => $isManager ? User::orderBy('name')->get(['id', 'name']) : [],
            'isManager'   => $isManager,
        ]);
    }

    public function update(Request $request, DataCollectionForm $form): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $this->service->updateForm($form, $request->user(), $data);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, DataCollectionForm $form): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $projectId = $form->project_id;
        $form->delete();

        return redirect()->route('data-collection.projects.show', $projectId)->with('success', 'Form deleted.');
    }
}
