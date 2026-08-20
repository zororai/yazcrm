<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Boards\StoreBoardRequest;
use App\Http\Requests\Boards\UpdateBoardRequest;
use App\Models\Board;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Boards/Index', [
            'boards' => Board::with(['workspace:id,name', 'owner:id,name'])
                ->withCount('tasks')
                ->where('is_archived', false)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreBoardRequest $request): RedirectResponse
    {
        $board = Board::create($request->validated() + ['owner_id' => $request->user()->id]);

        return redirect()->route('boards.show', $board)->with('success', 'Board created.');
    }

    public function show(Request $request, Board $board): Response
    {
        $this->authorize('view', $board);

        $status  = $request->string('status')->toString() ?: null;
        $priority = $request->string('priority')->toString() ?: null;
        $assignee = $request->integer('assignee') ?: null;
        $search   = $request->string('search')->toString() ?: null;
        $overdue  = $request->boolean('overdue');

        $tasks = $board->tasks()
            ->with(['group:id,name,position', 'assignees:id,name', 'creator:id,name'])
            ->whereNull('parent_id')
            ->where('is_archived', false)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($priority, fn ($q) => $q->where('priority', $priority))
            ->when($assignee, fn ($q) => $q->assignedTo($assignee))
            ->when($overdue, fn ($q) => $q->overdue())
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
            }))
            ->orderBy('position')
            ->get();

        return Inertia::render('Boards/Show', [
            'board'  => $board->load(['workspace:id,name', 'owner:id,name']),
            'groups' => $board->groups()->where('is_archived', false)->orderBy('position')->get(),
            'tasks'  => $tasks,
            'counts' => [
                'total'     => $board->tasks()->count(),
                'open'      => $board->tasks()->open()->count(),
                'completed' => $board->tasks()->completed()->count(),
                'overdue'   => $board->tasks()->overdue()->count(),
                'mine'      => $board->tasks()->assignedTo($request->user()->id)->count(),
            ],
            'users'  => User::orderBy('name')->get(['id', 'name']),
            'can' => [
                'update'       => $request->user()->can('update', $board),
                'delete'       => $request->user()->can('delete', $board),
                'manageGroups' => $request->user()->can('manageGroups', $board),
            ],
        ]);
    }

    public function update(UpdateBoardRequest $request, Board $board): RedirectResponse
    {
        $board->update($request->validated());

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, Board $board): RedirectResponse
    {
        $this->authorize('delete', $board);

        $board->delete();

        return redirect()->route('boards.index')->with('success', 'Board deleted.');
    }
}
