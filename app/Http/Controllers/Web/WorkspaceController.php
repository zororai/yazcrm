<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Workspaces/Index', [
            'workspaces' => Workspace::withCount('boards')
                ->with('owner:id,name')
                ->where('is_archived', false)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreWorkspaceRequest $request): RedirectResponse
    {
        $workspace = Workspace::create($request->validated() + ['owner_id' => $request->user()->id]);

        return redirect()->route('workspaces.show', $workspace)->with('success', 'Workspace created.');
    }

    public function show(Request $request, Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);

        return Inertia::render('Workspaces/Show', [
            'workspace' => $workspace->load('owner:id,name'),
            'boards'    => $workspace->boards()->with('owner:id,name')->withCount('tasks')->orderBy('name')->get(),
            'can'       => [
                'update' => $request->user()->can('update', $workspace),
                'delete' => $request->user()->can('delete', $workspace),
            ],
        ]);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        $workspace->update($request->validated());

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);

        $workspace->delete();

        return redirect()->route('workspaces.index')->with('success', 'Workspace deleted.');
    }
}
