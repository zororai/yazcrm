<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appraisal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppraisalController extends Controller
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

    public function index(Request $request): Response
    {
        $user  = $request->user();
        $query = Appraisal::with(['user:id,name', 'supervisor:id,name'])->latest();

        if (! $this->isManager($user)) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('supervisor_id', $user->id);
            });
        }

        return Inertia::render('Appraisals/Index', [
            'appraisals' => $query->get(),
            'staff'      => User::orderBy('name')->get(['id', 'name', 'supervisor_id']),
            'isManager'  => $this->isManager($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
        ]);

        // Only admins/directors may start an appraisal on behalf of someone else
        $targetId = ($this->isManager($user) && ! empty($data['user_id'])) ? $data['user_id'] : $user->id;
        $target   = User::findOrFail($targetId);

        $appraisal = Appraisal::create([
            'user_id'       => $target->id,
            'supervisor_id' => $target->supervisor_id,
            'status'        => 'draft',
        ]);

        return redirect()->route('appraisals.show', $appraisal)->with('success', 'Appraisal started.');
    }

    public function show(Request $request, Appraisal $appraisal): Response
    {
        $user = $request->user();
        $this->authorizeView($user, $appraisal);

        return Inertia::render('Appraisals/Show', [
            'appraisal' => $appraisal->load(['user:id,name', 'supervisor:id,name']),
            'staff'     => $this->isManager($user) ? User::orderBy('name')->get(['id', 'name']) : [],
            'can'       => [
                'editSelf'   => $this->canEditSelf($user, $appraisal),
                'editReview' => $this->canEditReview($user, $appraisal),
                'manage'     => $this->isManager($user),
                'delete'     => $user->role === 'admin',
            ],
        ]);
    }

    public function update(Request $request, Appraisal $appraisal): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canEditSelf($user, $appraisal)) {
            abort(403);
        }

        $this->nullifyBlank($request, ['start_date', 'supervisor_id']);

        $data = $request->validate([
            'job_title'           => 'nullable|string|max:255',
            'department'          => 'nullable|string|max:255',
            'employee_number'     => 'nullable|string|max:100',
            'start_date'          => 'nullable|date',
            'employee_responses'  => 'nullable|array',
            'supervisor_id'       => 'nullable|exists:users,id',
        ]);

        // Only a manager may reassign the supervisor
        if (! $this->isManager($user)) {
            unset($data['supervisor_id']);
        }

        $appraisal->update($data);

        return back()->with('success', 'Saved.');
    }

    public function submit(Request $request, Appraisal $appraisal): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canEditSelf($user, $appraisal) || $appraisal->status !== 'draft') {
            abort(403);
        }

        $appraisal->update([
            'status'             => 'submitted',
            'submitted_at'       => now(),
            'employee_signed_at' => now(),
        ]);

        return back()->with('success', 'Appraisal submitted to your supervisor.');
    }

    public function updateReview(Request $request, Appraisal $appraisal): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canEditReview($user, $appraisal)) {
            abort(403);
        }

        $this->nullifyBlank($request, ['date_of_review', 'next_review_date', 'overall_rating']);

        $data = $request->validate([
            'date_of_review'        => 'nullable|date',
            'next_review_date'      => 'nullable|date',
            'overall_rating'        => 'nullable|integer|min:1|max:5',
            'supervisor_responses'  => 'nullable|array',
        ]);

        $appraisal->update($data);

        return back()->with('success', 'Saved.');
    }

    public function complete(Request $request, Appraisal $appraisal): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canEditReview($user, $appraisal) || $appraisal->status !== 'submitted') {
            abort(403);
        }

        $appraisal->update([
            'status'                => 'completed',
            'completed_at'          => now(),
            'supervisor_signed_at'  => now(),
        ]);

        return back()->with('success', 'Appraisal completed.');
    }

    public function reopen(Request $request, Appraisal $appraisal): RedirectResponse
    {
        $user = $request->user();
        if (! $this->isManager($user)) {
            abort(403);
        }

        $appraisal->update([
            'status'       => $appraisal->status === 'completed' ? 'submitted' : 'draft',
            'completed_at' => null,
        ]);

        return back()->with('success', 'Appraisal reopened.');
    }

    public function destroy(Request $request, Appraisal $appraisal): RedirectResponse
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        $appraisal->delete();

        return back()->with('success', 'Appraisal deleted.');
    }

    private function authorizeView(User $user, Appraisal $appraisal): void
    {
        if ($this->isManager($user)) {
            return;
        }
        if ($appraisal->user_id === $user->id || $appraisal->supervisor_id === $user->id) {
            return;
        }
        abort(403);
    }

    private function canEditSelf(User $user, Appraisal $appraisal): bool
    {
        if ($this->isManager($user)) {
            return true;
        }

        return $appraisal->user_id === $user->id && $appraisal->status === 'draft';
    }

    private function canEditReview(User $user, Appraisal $appraisal): bool
    {
        if ($this->isManager($user)) {
            return true;
        }

        return $appraisal->supervisor_id === $user->id && $appraisal->status === 'submitted';
    }
}
