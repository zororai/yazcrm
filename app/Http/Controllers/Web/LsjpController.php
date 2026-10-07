<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LsjpCheckup;
use App\Models\LsjpCheckupPhoto;
use App\Models\LsjpParticipant;
use App\Models\User;
use App\Support\Locations\Zimbabwe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

// LSJP Register — Livelihood Skills & Job Preparation. Records the people
// trained in a skill and their 1-, 3- and 6-month follow-up check-ups
// (progress, challenges, photos, comment, referral). Routes are gated by the
// "lsjp" nav permission; deleting a person is admin/director only.
class LsjpController extends Controller
{
    private const MAX_PHOTOS = 6;

    private function canDelete(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function index(Request $request): Response
    {
        $search   = trim((string) $request->input('search', ''));
        $project  = trim((string) $request->input('project', ''));
        $district = trim((string) $request->input('district', ''));
        $followUp = (string) $request->input('follow_up', '');

        $people = LsjpParticipant::with('checkups:id,lsjp_participant_id,month,conducted_on,progress')
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('id_number', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when($project, fn ($q) => $q->where('project', $project))
            ->when($district, fn ($q) => $q->where('district', $district))
            ->orderByDesc('training_completed_on')->orderBy('full_name')
            ->get()
            ->map(fn (LsjpParticipant $p) => [
                'id'                    => $p->id,
                'full_name'             => $p->full_name,
                'id_number'             => $p->id_number,
                'age'                   => $p->age,
                'sex'                   => $p->sex,
                'district'              => $p->district,
                'location'              => $p->location,
                'key_population'        => $p->key_population,
                'current_activity'      => $p->current_activity,
                'skill_trained'         => $p->skill_trained,
                'project'               => $p->project,
                'training_completed_on' => $p->training_completed_on->toDateString(),
                'schedule'              => $p->schedule(),
            ]);

        // Counts across the (search/project/district-filtered) register, before
        // the follow-up filter narrows the table.
        $states = $people->flatMap(fn ($p) => collect($p['schedule'])->pluck('state'));
        $summary = [
            'people'   => $people->count(),
            'overdue'  => $states->filter(fn ($s) => $s === 'overdue')->count(),
            'due_soon' => $states->filter(fn ($s) => $s === 'due_soon')->count(),
            'done'     => $states->filter(fn ($s) => $s === 'done')->count(),
            'complete' => $people->filter(fn ($p) => collect($p['schedule'])->every(fn ($s) => $s['state'] === 'done'))->count(),
        ];

        $hasState = fn (array $p, string $state) => collect($p['schedule'])->contains('state', $state);
        $people = match ($followUp) {
            'overdue'  => $people->filter(fn ($p) => $hasState($p, 'overdue')),
            'due_soon' => $people->filter(fn ($p) => $hasState($p, 'due_soon')),
            'complete' => $people->filter(fn ($p) => collect($p['schedule'])->every(fn ($s) => $s['state'] === 'done')),
            default    => $people,
        };

        return Inertia::render('Lsjp/Index', [
            'people'   => $people->values(),
            'summary'  => $summary,
            'filters'  => ['search' => $search, 'project' => $project, 'district' => $district, 'follow_up' => $followUp],
            'options'  => $this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateParticipant($request);

        $person = LsjpParticipant::create($data + ['created_by' => $request->user()->id]);

        $request->attributes->set('audit_description', "Added {$person->full_name} to the LSJP register (trained {$person->training_completed_on->toDateString()})");

        return redirect()->route('lsjp.show', $person)->with('success', "{$person->full_name} added to the LSJP register.");
    }

    public function show(Request $request, LsjpParticipant $participant): Response
    {
        $participant->load(['checkups.photos', 'checkups.conductedBy:id,name', 'creator:id,name']);

        return Inertia::render('Lsjp/Show', [
            'person'    => $participant,
            'schedule'  => $participant->schedule(),
            'options'   => $this->options(),
            'canDelete' => $this->canDelete($request->user()),
        ]);
    }

    public function update(Request $request, LsjpParticipant $participant): RedirectResponse
    {
        $participant->update($this->validateParticipant($request, $participant));

        $changed = array_keys(array_diff_key($participant->getChanges(), ['updated_at' => 1]));
        $request->attributes->set('audit_description', "Edited LSJP record for {$participant->full_name}"
            .($changed ? ': '.implode(', ', $changed) : ' (no changes)'));

        return back()->with('success', $changed ? 'Details updated.' : 'No changes to save.');
    }

    public function destroy(Request $request, LsjpParticipant $participant): RedirectResponse
    {
        abort_unless($this->canDelete($request->user()), 403);

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        $participant->delete(); // soft delete — check-ups and photos are kept

        $request->attributes->set('audit_description', "Deleted {$participant->full_name} from the LSJP register. Reason: {$data['reason']}");

        return redirect()->route('lsjp.index')->with('success', "{$participant->full_name} removed from the register.");
    }

    // Create or update the 1-, 3- or 6-month check-up, adding any new photos.
    public function saveCheckup(Request $request, LsjpParticipant $participant, int $month): RedirectResponse
    {
        abort_unless(array_key_exists($month, LsjpParticipant::CHECKUP_MONTHS), 404);

        $existing = $participant->checkups()->where('month', $month)->withCount('photos')->first();
        $room = self::MAX_PHOTOS - ($existing->photos_count ?? 0);

        $data = $request->validate([
            'conducted_on'   => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$participant->training_completed_on->toDateString()],
            'progress'       => ['required', Rule::in(array_keys(LsjpCheckup::PROGRESS))],
            'activity_status'=> 'nullable|string|max:255',
            'challenges'     => 'nullable|string|max:5000',
            'comment'        => 'nullable|string|max:5000',
            'referred_to'    => 'nullable|string|max:255',
            'referral_notes' => 'nullable|string|max:2000',
            'photos'         => "nullable|array|max:{$room}",
            'photos.*'       => 'image|max:5120',
        ], [
            'conducted_on.after_or_equal' => 'The check-up date can\'t be before training was completed.',
            'photos.max'                  => "A check-up can have at most ".self::MAX_PHOTOS." photos ({$room} more allowed).",
        ]);

        $checkup = DB::transaction(function () use ($participant, $month, $data, $request) {
            $checkup = LsjpCheckup::updateOrCreate(
                ['lsjp_participant_id' => $participant->id, 'month' => $month],
                collect($data)->except('photos')->all() + ['conducted_by' => $request->user()->id],
            );

            foreach ($request->file('photos', []) as $photo) {
                $checkup->photos()->create(['path' => $photo->store('lsjp-checkups', 'public')]);
            }

            return $checkup;
        });

        $label = LsjpParticipant::CHECKUP_MONTHS[$month];
        $request->attributes->set('audit_description', sprintf(
            '%s %s check-up for %s: %s%s',
            $checkup->wasRecentlyCreated ? 'Recorded' : 'Updated',
            $label,
            $participant->full_name,
            LsjpCheckup::PROGRESS[$checkup->progress],
            $checkup->referred_to ? " · referred to {$checkup->referred_to}" : '',
        ));

        return back()->with('success', "{$label} check-up saved.");
    }

    public function destroyPhoto(Request $request, LsjpCheckupPhoto $photo): RedirectResponse
    {
        $photo->load('checkup.participant');
        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        $request->attributes->set('audit_description', sprintf(
            'Removed a photo from the %s check-up of %s',
            LsjpParticipant::CHECKUP_MONTHS[$photo->checkup->month] ?? '',
            $photo->checkup->participant?->full_name,
        ));

        return back()->with('success', 'Photo removed.');
    }

    private function validateParticipant(Request $request, ?LsjpParticipant $participant = null): array
    {
        return $request->validate([
            'full_name'             => 'required|string|max:255',
            'id_number'             => ['nullable', 'string', 'max:50',
                Rule::unique('lsjp_participants', 'id_number')->whereNull('deleted_at')->ignore($participant?->id)],
            'age'                   => 'nullable|integer|min:10|max:100',
            'sex'                   => 'nullable|in:male,female,other',
            'phone'                 => 'nullable|string|max:30',
            'province'              => ['nullable', Rule::in(array_keys(Zimbabwe::PROVINCE_DISTRICTS))],
            'district'              => 'nullable|string|max:100',
            'location'              => 'nullable|string|max:255',
            'key_population'        => 'nullable|string|max:255',
            'current_activity'      => 'nullable|string|max:255',
            'skill_trained'         => 'nullable|string|max:255',
            'project'               => 'nullable|string|max:255',
            'training_completed_on' => 'required|date|before_or_equal:today',
            'notes'                 => 'nullable|string|max:5000',
        ], [
            'id_number.unique' => 'Someone with this ID number is already in the LSJP register.',
        ]);
    }

    private function options(): array
    {
        return [
            'provinceDistricts' => Zimbabwe::PROVINCE_DISTRICTS,
            'progress'          => LsjpCheckup::PROGRESS,
            'checkupMonths'     => LsjpParticipant::CHECKUP_MONTHS,
            'projects'          => LsjpParticipant::whereNotNull('project')->distinct()->orderBy('project')->pluck('project'),
            'districts'         => LsjpParticipant::whereNotNull('district')->distinct()->orderBy('district')->pluck('district'),
        ];
    }
}
