<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamTaskController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isManager = in_array($user->role, ['admin', 'director'], true);

        $subordinateIds = $isManager
            ? User::pluck('id')->all()
            : $user->subordinates()->pluck('id')->all();

        $status   = $request->string('status')->toString() ?: null;
        $priority = $request->string('priority')->toString() ?: null;
        $member   = $request->integer('member') ?: null;
        $overdue  = $request->boolean('overdue');

        $tasks = Task::with(['board:id,name', 'assignees:id,name', 'creator:id,name'])
            ->where('is_archived', false)
            ->when($subordinateIds, fn ($q) => $q->assignedToAny($member ? [$member] : $subordinateIds))
            ->when(! $subordinateIds, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($priority, fn ($q) => $q->where('priority', $priority))
            ->when($overdue, fn ($q) => $q->overdue())
            ->orderBy('due_date')
            ->get();

        return Inertia::render('Team/Tasks', [
            'tasks'   => $tasks,
            'members' => $isManager
                ? User::orderBy('name')->get(['id', 'name'])
                : $user->subordinates()->orderBy('name')->get(['id', 'name']),
            'counts' => [
                'total'     => count($subordinateIds) ? Task::assignedToAny($subordinateIds)->count() : 0,
                'open'      => count($subordinateIds) ? Task::assignedToAny($subordinateIds)->open()->count() : 0,
                'overdue'   => count($subordinateIds) ? Task::assignedToAny($subordinateIds)->overdue()->count() : 0,
                'completed' => count($subordinateIds) ? Task::assignedToAny($subordinateIds)->completed()->count() : 0,
            ],
        ]);
    }
}
