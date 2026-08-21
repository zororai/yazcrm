<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DataCollectionProject;
use App\Models\User;
use App\Services\DataCollection\DataCollectionFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DataCollectionProjectController extends Controller
{
    public function __construct(private readonly DataCollectionFormService $service)
    {
    }

    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('DataCollection/Projects/Index', [
            'projects'  => DataCollectionProject::withCount('forms')->with('owner:id,name')->latest()->get(),
            'users'     => User::orderBy('name')->get(['id', 'name']),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'code'        => 'required|string|max:50|unique:data_collection_projects,code',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'owner_id'    => 'nullable|exists:users,id',
        ]);

        $project = $this->service->createProject($request->user(), $data);

        return redirect()->route('data-collection.projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Request $request, DataCollectionProject $project): Response
    {
        return Inertia::render('DataCollection/Projects/Show', [
            'project'   => $project->load('owner:id,name'),
            'forms'     => $project->forms()->with('currentVersion')->withCount('versions')->orderBy('name')->get(),
            'isManager' => $this->isManager($request->user()),
        ]);
    }

    public function update(Request $request, DataCollectionProject $project): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'code'        => "required|string|max:50|unique:data_collection_projects,code,{$project->id}",
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'owner_id'    => 'nullable|exists:users,id',
            'status'      => 'sometimes|string|in:draft,active,completed,archived',
        ]);

        $this->service->updateProject($project, $request->user(), $data);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, DataCollectionProject $project): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $project->delete();

        return redirect()->route('data-collection.projects.index')->with('success', 'Project deleted.');
    }
}
