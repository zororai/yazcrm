<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DataCollectionFormContext;
use App\Models\DataCollectionExport;
use App\Models\DataCollectionForm;
use App\Models\DataCollectionSubmission;
use App\Services\DataCollection\FormFieldCatalog;
use App\Services\DataCollection\SubmissionFilters;
use App\Services\DataCollection\SubmissionReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// The per-form data table: server-side paging, sorting, filters, bulk
// validation status. Choice labels are mapped in the browser (toggle).
class DataCollectionDataController extends Controller
{
    use DataCollectionFormContext;

    private const META_SORTS = ['id', 'collected_at', 'received_at', 'status', 'submitted_by', 'completion'];

    public function index(Request $request, DataCollectionForm $form): Response
    {
        $this->authorize('view', $form);

        $catalog = FormFieldCatalog::for($form);
        $hidden = $this->hiddenFields($request, $catalog);
        $active = SubmissionFilters::fromRequest($request);

        $sort = (string) $request->query('sort', 'collected_at');
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->query('per_page'), [25, 50, 100], true) ? (int) $request->query('per_page') : 25;

        $query = SubmissionFilters::query($form, $active, $hidden)
            ->with(['submittedBy:id,name', 'formVersion:id,version_number'])
            ->withCount('media');

        if (in_array($sort, self::META_SORTS, true)) {
            $query->orderBy($sort, $dir);
        } elseif (isset($catalog->fields[$sort]) && $catalog->fields[$sort]['repeat'] === null && ! in_array($sort, $hidden, true)) {
            $query->orderBy('data->'.$catalog->fields[$sort]['name'], $dir);
        }
        $query->orderBy('id', $dir);

        $page = $query->paginate($perPage)->withQueryString();
        $page->getCollection()->transform(fn (DataCollectionSubmission $s) => [
            'id'           => $s->id,
            'client_uuid'  => $s->client_uuid,
            'submitted_by' => $s->submittedBy?->name,
            'collected_at' => $s->collected_at,
            'received_at'  => $s->received_at,
            'status'       => $s->status,
            'status_note'  => $s->status_note,
            'version'      => $s->formVersion?->version_number,
            'completion'   => $s->completion,
            'media_count'  => $s->media_count,
            'deleted'      => $s->trashed(),
            'data'         => $catalog->mask($s->data ?? [], $hidden),
        ]);

        return Inertia::render('DataCollection/Forms/Data', $this->formContext($request, $form, $catalog) + [
            'rows'    => $page,
            'sort'    => ['field' => $sort, 'dir' => $dir],
            'exports' => DataCollectionExport::where('form_id', $form->id)
                ->where('user_id', $request->user()->id)
                ->latest('id')->limit(5)
                ->get(['id', 'format', 'status', 'row_count', 'error', 'created_at']),
        ]);
    }

    public function bulkStatus(Request $request, DataCollectionForm $form, SubmissionReviewService $review): RedirectResponse
    {
        $this->authorize('edit', $form);

        $data = $request->validate([
            'ids'    => 'required|array|min:1|max:500',
            'ids.*'  => 'integer',
            'status' => 'required|in:'.implode(',', DataCollectionSubmission::STATUSES),
            'note'   => 'nullable|string|max:1000',
        ]);

        $submissions = DataCollectionSubmission::where('form_id', $form->id)->whereIn('id', $data['ids'])->get();
        foreach ($submissions as $submission) {
            $review->setStatus($submission, $request->user(), $data['status'], $data['note'] ?? null);
        }

        return back()->with('success', $submissions->count().' submission(s) marked '.str_replace('_', ' ', $data['status']).'.');
    }
}
