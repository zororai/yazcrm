<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appraisal;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyWorkController extends Controller
{
    public function index(Request $request): Response
    {
        $user      = $request->user();
        $isManager = in_array($user->role, ['admin', 'director'], true);

        $myAppraisals = Appraisal::where('user_id', $user->id)
            ->whereIn('status', ['draft', 'submitted'])
            ->with('supervisor:id,name')
            ->latest()
            ->get();

        $appraisalsToReview = Appraisal::query()
            ->where('status', 'submitted')
            ->when(! $isManager, fn ($q) => $q->where('supervisor_id', $user->id))
            ->with('user:id,name')
            ->latest()
            ->get();

        $myTasks = Task::with(['board:id,name'])
            ->assignedTo($user->id)
            ->open()
            ->where('is_archived', false)
            ->orderBy('due_date')
            ->get();

        return Inertia::render('MyWork/Index', [
            'myAppraisals'       => $myAppraisals,
            'appraisalsToReview' => $appraisalsToReview,
            'myTasks'            => $myTasks,
            'counts' => [
                'tasksOpen'     => $myTasks->count(),
                'tasksOverdue'  => $myTasks->filter->isOverdue()->count(),
                'appraisalsMine'  => $myAppraisals->count(),
                'appraisalsQueue' => $appraisalsToReview->count(),
            ],
        ]);
    }
}
