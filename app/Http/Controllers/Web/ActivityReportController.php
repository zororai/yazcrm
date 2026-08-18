<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityReportController extends Controller
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    // HTML form inputs send '' for cleared date/select fields; treat that as null
    // so 'nullable' validation rules don't reject an intentionally-blank value.
    private function nullifyBlank(Request $request, array $keys): void
    {
        $request->merge(collect($keys)
            ->filter(fn ($key) => $request->input($key) === '')
            ->mapWithKeys(fn ($key) => [$key => null])
            ->all());
    }

    private function canView(User $user, ActivityReport $report): bool
    {
        if ($this->isManager($user)) {
            return true;
        }

        return $report->compiled_by === $user->id
            || $report->reviewer_id === $user->id
            || $report->approver_id === $user->id
            || in_array($user->id, $report->viewer_ids ?? [], true);
    }

    private function canEdit(User $user, ActivityReport $report): bool
    {
        if ($this->isManager($user)) {
            return true;
        }

        return $report->compiled_by === $user->id && $report->status === 'draft';
    }

    private function canReview(User $user, ActivityReport $report): bool
    {
        if ($this->isManager($user)) {
            return true;
        }

        return $report->reviewer_id === $user->id && $report->status === 'submitted';
    }

    private function canApprove(User $user, ActivityReport $report): bool
    {
        if ($this->isManager($user)) {
            return true;
        }

        return $report->approver_id === $user->id && $report->status === 'reviewed';
    }

    private function canManageViewers(User $user, ActivityReport $report): bool
    {
        return $this->isManager($user) || $report->compiled_by === $user->id;
    }

    private function permissionsFor(User $user, ActivityReport $report): array
    {
        return [
            'edit'          => $this->canEdit($user, $report),
            'review'        => $this->canReview($user, $report),
            'approve'       => $this->canApprove($user, $report),
            'manageViewers' => $this->canManageViewers($user, $report),
            'manage'        => $this->isManager($user),
            'delete'        => $user->role === 'admin',
        ];
    }

    public function index(Request $request): Response
    {
        $user  = $request->user();
        $query = ActivityReport::with(['compiler:id,name', 'reviewer:id,name', 'approver:id,name'])->latest();

        if (! $this->isManager($user)) {
            $query->where(function ($q) use ($user) {
                $q->where('compiled_by', $user->id)
                    ->orWhere('reviewer_id', $user->id)
                    ->orWhere('approver_id', $user->id)
                    ->orWhereJsonContains('viewer_ids', $user->id);
            });
        }

        return Inertia::render('ActivityReports/Index', [
            'reports' => $query->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'compiled_by' => 'nullable|exists:users,id',
        ]);

        $compiledBy = ($this->isManager($user) && ! empty($data['compiled_by'])) ? $data['compiled_by'] : $user->id;

        $report = ActivityReport::create([
            'compiled_by' => $compiledBy,
            'status'      => 'draft',
        ]);

        return redirect()->route('activity-reports.show', $report)->with('success', 'Activity report started.');
    }

    public function show(Request $request, ActivityReport $activityReport): Response
    {
        $user = $request->user();
        if (! $this->canView($user, $activityReport)) {
            abort(403);
        }

        return Inertia::render('ActivityReports/Show', [
            'report' => $activityReport->load(['compiler:id,name', 'reviewer:id,name', 'approver:id,name']),
            'users'  => User::orderBy('name')->get(['id', 'name']),
            'can'    => $this->permissionsFor($user, $activityReport),
        ]);
    }

    public function update(Request $request, ActivityReport $activityReport): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canEdit($user, $activityReport)) {
            abort(403);
        }

        $this->nullifyBlank($request, ['date', 'reviewer_id', 'approver_id']);

        $data = $request->validate([
            'reviewer_id'       => 'nullable|exists:users,id',
            'approver_id'       => 'nullable|exists:users,id',
            'name_of_activity'  => 'nullable|string|max:255',
            'date'              => 'nullable|date',
            'district'          => 'nullable|string|max:255',
            'organized_by'      => 'nullable|string|max:255',
            'officer_in_charge' => 'nullable|string|max:255',
            'venue'             => 'nullable|string|max:255',
            'attendance'        => 'nullable|array',
            'objectives'        => 'nullable|string',
            'methodology'       => 'nullable|string',
            'narration'         => 'nullable|string',
            'key_outcomes'      => 'nullable|string',
            'challenges'        => 'nullable|string',
            'action_items'      => 'nullable|array',
            'pictures_link'     => 'nullable|string|max:2048',
            'impact_quotes'     => 'nullable|array',
        ]);

        $activityReport->update($data);

        return back()->with('success', 'Saved.');
    }

    public function updateViewers(Request $request, ActivityReport $activityReport): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canManageViewers($user, $activityReport)) {
            abort(403);
        }

        $data = $request->validate([
            'viewer_ids'   => 'nullable|array',
            'viewer_ids.*' => 'integer|exists:users,id',
        ]);

        $activityReport->update(['viewer_ids' => $data['viewer_ids'] ?? []]);

        return back()->with('success', 'Viewers updated.');
    }

    public function submit(Request $request, ActivityReport $activityReport): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canEdit($user, $activityReport) || $activityReport->status !== 'draft') {
            abort(403);
        }
        if (! $activityReport->reviewer_id) {
            return back()->withErrors(['reviewer_id' => 'Choose a reviewer before submitting.']);
        }

        $activityReport->update([
            'status'      => 'submitted',
            'compiled_at' => now(),
        ]);

        return back()->with('success', 'Report submitted for review.');
    }

    public function review(Request $request, ActivityReport $activityReport): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canReview($user, $activityReport)) {
            abort(403);
        }

        $activityReport->update([
            'status'      => 'reviewed',
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Report marked as reviewed.');
    }

    public function approve(Request $request, ActivityReport $activityReport): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canApprove($user, $activityReport)) {
            abort(403);
        }

        $activityReport->update([
            'status'      => 'approved',
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Report approved.');
    }

    public function reopen(Request $request, ActivityReport $activityReport): RedirectResponse
    {
        $user = $request->user();
        if (! $this->isManager($user)) {
            abort(403);
        }

        $previous = match ($activityReport->status) {
            'approved' => 'reviewed',
            'reviewed' => 'submitted',
            default    => 'draft',
        };

        $data = ['status' => $previous];
        if ($previous === 'submitted') { $data['approved_at'] = null; }
        if ($previous === 'draft')     { $data['reviewed_at'] = null; }

        $activityReport->update($data);

        return back()->with('success', 'Report reopened.');
    }

    public function destroy(Request $request, ActivityReport $activityReport): RedirectResponse
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        $activityReport->delete();

        return back()->with('success', 'Activity report deleted.');
    }
}
