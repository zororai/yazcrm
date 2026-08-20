<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\AssignTaskRequest;
use App\Http\Requests\Tasks\ChangeTaskPriorityRequest;
use App\Http\Requests\Tasks\ChangeTaskStatusRequest;
use App\Http\Requests\Tasks\ReopenTaskRequest;
use App\Http\Requests\Tasks\StoreTaskCommentRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TaskController extends Controller
{
    public function __construct(private readonly TaskWorkflowService $workflow)
    {
    }

    private function runTransition(callable $transition, string $successMessage): RedirectResponse
    {
        try {
            $transition();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $successMessage);
    }

    public function show(Request $request, Task $task): Response
    {
        $this->authorize('view', $task);
        $user = $request->user();

        return Inertia::render('Tasks/Show', [
            'task' => $task->load([
                'board:id,name', 'group:id,name', 'parent:id,title',
                'creator:id,name', 'assignees:id,name', 'watchers:id,name',
                'subtasks' => fn ($q) => $q->orderBy('position'),
                'comments.user:id,name',
            ]),
            'progress' => $task->progress(),
            'can' => [
                'update'       => $user->can('update', $task),
                'assign'       => $user->can('assign', $task),
                'changeStatus' => $user->can('changeStatus', $task),
                'delete'       => $user->can('delete', $task),
                'manage'       => $user->can('manage', $task),
            ],
            'activityLogs' => $user->can('manage', $task)
                ? $task->activityLogs()->with('user:id,name')->get()
                : [],
            'users' => $user->can('assign', $task) ? User::orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $task = $this->workflow->create($request->user(), $request->validated());

        return redirect()->route('tasks.show', $task)->with('success', 'Task created.');
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->workflow->update($task, $request->user(), $request->validated());

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $boardId = $task->board_id;
        $task->delete();

        return redirect()->route('boards.show', $boardId)->with('success', 'Task deleted.');
    }

    public function assign(AssignTaskRequest $request, Task $task): RedirectResponse
    {
        $this->workflow->assign($task, $request->user(), $request->validated()['user_ids']);

        return back()->with('success', 'Assignees updated.');
    }

    public function changeStatus(ChangeTaskStatusRequest $request, Task $task): RedirectResponse
    {
        return $this->runTransition(
            fn () => $this->workflow->changeStatus($task, $request->user(), $request->validated()['status']),
            'Status updated.'
        );
    }

    public function changePriority(ChangeTaskPriorityRequest $request, Task $task): RedirectResponse
    {
        $this->workflow->changePriority($task, $request->user(), $request->validated()['priority']);

        return back()->with('success', 'Priority updated.');
    }

    public function complete(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('changeStatus', $task);

        return $this->runTransition(
            fn () => $this->workflow->complete($task, $request->user()),
            'Task completed.'
        );
    }

    public function reopen(ReopenTaskRequest $request, Task $task): RedirectResponse
    {
        return $this->runTransition(
            fn () => $this->workflow->reopen($task, $request->user(), $request->validated()['reason']),
            'Task reopened.'
        );
    }

    public function archive(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('archive', $task);

        $this->workflow->archive($task, $request->user());

        return back()->with('success', 'Task archived.');
    }

    public function restore(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('restore', $task);

        $this->workflow->restore($task, $request->user());

        return back()->with('success', 'Task restored.');
    }

    public function comment(StoreTaskCommentRequest $request, Task $task): RedirectResponse
    {
        $this->workflow->addComment($task, $request->user(), $request->validated()['comment']);

        return back()->with('success', 'Comment added.');
    }
}
