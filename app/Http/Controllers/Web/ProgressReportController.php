<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProgressReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// Individual Monthly Progress Report — mirrors the org's paper template
// (name/job title/supervisor/date submitted, overall progress narrative,
// a workplan-activities table). Each user files one per calendar month.
class ProgressReportController extends Controller
{
    private function isManager(Request $request): bool
    {
        return in_array($request->user()->role, ['admin', 'director', 'helpline_manager'], true);
    }

    public function index(Request $request): Response
    {
        $user      = $request->user();
        $isManager = $this->isManager($request);
        $month     = Carbon::parse($request->input('month', now()->startOfMonth()->toDateString()))->startOfMonth()->toDateString();

        $mine = ProgressReport::where('user_id', $user->id)
            ->orderByDesc('month')
            ->get(['id', 'month', 'job_title', 'supervisor', 'date_submitted', 'overall_progress', 'activities']);

        $current = $mine->first(fn (ProgressReport $r) => $r->month->toDateString() === $month);

        $teamReports = [];
        if ($isManager) {
            $teamReports = ProgressReport::with('user:id,name,username')
                ->whereDate('month', $month)
                ->orderBy('user_id')
                ->get(['id', 'user_id', 'month', 'job_title', 'supervisor', 'date_submitted', 'overall_progress'])
                ->map(fn (ProgressReport $r) => [
                    'id'         => $r->id,
                    'user'       => $r->user,
                    'job_title'  => $r->job_title,
                    'supervisor' => $r->supervisor,
                    'submitted'  => $r->date_submitted?->toDateString(),
                ])->values();
        }

        return Inertia::render('ProgressReports/Index', [
            'month'    => $month,
            'current'  => $current ? [
                'id'                => $current->id,
                'job_title'         => $current->job_title,
                'supervisor'        => $current->supervisor,
                'date_submitted'    => $current->date_submitted?->toDateString(),
                'overall_progress'  => $current->overall_progress,
                'activities'        => $current->activities ?? [],
            ] : null,
            'history' => $mine->map(fn (ProgressReport $r) => [
                'id'    => $r->id,
                'month' => $r->month->toDateString(),
                'submitted' => (bool) $r->date_submitted,
            ])->values(),
            'isManager'    => $isManager,
            'teamReports'  => $teamReports,
            'allUsers'     => $isManager ? User::where('role', '!=', 'admin')->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month'             => 'required|date',
            'job_title'         => 'nullable|string|max:255',
            'supervisor'        => 'nullable|string|max:255',
            'date_submitted'    => 'nullable|date',
            'overall_progress'  => 'nullable|string',
            'activities'                    => 'nullable|array',
            'activities.*.activity'         => 'nullable|string|max:500',
            'activities.*.completed'        => 'nullable|string|max:255',
            'activities.*.details'          => 'nullable|string|max:1000',
        ]);

        // Always the authenticated user's own report — a report can't be
        // filed on someone else's behalf, even by a manager.
        ProgressReport::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'month'   => Carbon::parse($validated['month'])->startOfMonth()->toDateString(),
            ],
            [
                'job_title'        => $validated['job_title'] ?? null,
                'supervisor'       => $validated['supervisor'] ?? null,
                'date_submitted'   => $validated['date_submitted'] ?? null,
                'overall_progress' => $validated['overall_progress'] ?? null,
                'activities'       => array_values(array_filter(
                    $validated['activities'] ?? [],
                    fn ($a) => filled($a['activity'] ?? null) || filled($a['completed'] ?? null) || filled($a['details'] ?? null)
                )),
            ],
        );

        return back()->with('success', 'Progress report saved.');
    }

    // Manager viewing one agent's report for a given month.
    public function show(Request $request, ProgressReport $report): Response
    {
        abort_unless($report->user_id === $request->user()->id || $this->isManager($request), 403);

        return Inertia::render('ProgressReports/Show', [
            'report' => [
                'id'                => $report->id,
                'user'              => $report->user()->first(['id', 'name', 'username']),
                'month'             => $report->month->toDateString(),
                'job_title'         => $report->job_title,
                'supervisor'        => $report->supervisor,
                'date_submitted'    => $report->date_submitted?->toDateString(),
                'overall_progress'  => $report->overall_progress,
                'activities'        => $report->activities ?? [],
            ],
        ]);
    }
}
