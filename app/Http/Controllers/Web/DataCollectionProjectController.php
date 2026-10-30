<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DataCollectionForm;
use App\Models\DataCollectionProject;
use App\Models\User;
use App\Services\DataCollection\DataCollectionFormService;
use App\Support\DataCollection\FormAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DataCollectionProjectController extends Controller
{
    public function __construct(private readonly DataCollectionFormService $service)
    {
    }

    // Designers see every project; everyone else the projects holding a
    // form they have a permission on.
    public function index(Request $request): Response
    {
        $user = $request->user();
        $formIds = FormAccess::isDesigner($user) ? null : $this->visibleFormIds($user);

        return Inertia::render('DataCollection/Projects/Index', [
            'projects'  => DataCollectionProject::query()
                ->when($formIds !== null, fn ($q) => $q->whereHas('forms', fn ($f) => $f->whereIn('id', $formIds)))
                ->withCount(['forms' => fn ($q) => $q->when($formIds !== null, fn ($f) => $f->whereIn('id', $formIds))])
                ->with('owner:id,name')
                ->latest()
                ->get(),
            'users'     => FormAccess::isDesigner($user) ? User::orderBy('name')->get(['id', 'name']) : [],
            'isManager' => FormAccess::isDesigner($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(FormAccess::isDesigner($request->user()), 403);

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
        $user = $request->user();
        $formIds = FormAccess::isDesigner($user) ? null : $this->visibleFormIds($user);
        abort_if($formIds !== null && ! $project->forms()->whereIn('id', $formIds)->exists(), 403);

        return Inertia::render('DataCollection/Projects/Show', [
            'project'   => $project->load('owner:id,name'),
            'forms'     => $project->forms()
                ->when($formIds !== null, fn ($q) => $q->whereIn('id', $formIds))
                ->with('currentVersion:id,version_number,deployed_at')
                ->withCount(['versions', 'submissions'])
                ->orderBy('name')
                ->get(),
            'isManager' => FormAccess::isDesigner($user),
        ]);
    }

    public function update(Request $request, DataCollectionProject $project): RedirectResponse
    {
        abort_unless(FormAccess::isDesigner($request->user()), 403);

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
        abort_unless(FormAccess::isDesigner($request->user()), 403);

        $project->delete();

        return redirect()->route('data-collection.projects.index')->with('success', 'Project deleted.');
    }

    private function visibleFormIds(User $user): array
    {
        return array_values(array_unique(array_merge(...array_map(
            fn ($permission) => FormAccess::formIds($user, $permission) ?? [],
            ['view', 'collect', 'edit', 'export', 'map'],
        ))));
    }
}
