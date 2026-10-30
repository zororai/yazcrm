<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DataCollectionActivityLog;
use App\Models\DataCollectionForm;
use App\Models\DataCollectionFormAssignment;
use App\Models\DataCollectionFormVersion;
use App\Models\DataCollectionProject;
use App\Models\Role;
use App\Models\User;
use App\Services\DataCollection\DataCollectionFormService;
use App\Services\DataCollection\XlsForm\XlsFormSamples;
use App\Support\DataCollection\FormAccess;
use App\Support\DataCollection\SchemaWalker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

// A form's overview: its versions (XLSForm upload, errors, deploy), who it's
// assigned to, and links to its data, reports and mapping.
class DataCollectionFormController extends Controller
{
    public function __construct(private readonly DataCollectionFormService $service)
    {
    }

    public function store(Request $request, DataCollectionProject $project): RedirectResponse
    {
        $this->authorize('design', DataCollectionForm::class);

        $data = $request->validate([
            'code'        => 'required|string|max:50|unique:data_collection_forms,code',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $form = $this->service->createForm($project, $request->user(), $data);

        return redirect()->route('data-collection.forms.show', $form)->with('success', 'Form created. Upload its XLSForm to add the questions.');
    }

    public function show(Request $request, DataCollectionForm $form): Response
    {
        $user = $request->user();
        $permissions = FormAccess::permissions($user, $form);
        abort_unless($permissions, 403);
        $design = FormAccess::isDesigner($user);

        return Inertia::render('DataCollection/Forms/Show', [
            'form'        => $form->load('project:id,name')->loadCount('submissions'),
            'versions'    => $form->versions()->with(['deployedBy:id,name', 'creator:id,name'])->get()
                ->map(fn (DataCollectionFormVersion $v) => [
                    'id'             => $v->id,
                    'version_number' => $v->version_number,
                    'version_label'  => $v->version_label,
                    'status'         => $v->status,
                    'checksum'       => $v->checksum,
                    'errors'         => $v->errors ?? [],
                    'source_name'    => $v->source_name,
                    'questions'      => $v->questionCount(),
                    'deployed_at'    => $v->deployed_at,
                    'deployed_by'    => $v->deployedBy?->name,
                    'created_by'     => $v->creator?->name,
                    'created_at'     => $v->created_at,
                    'submissions'    => $form->submissions()->where('form_version_id', $v->id)->count(),
                ]),
            'preview'     => $this->preview($form),
            'assignments' => $design
                ? $form->assignments()->with('user:id,name,email,role')->latest()->get()
                : [],
            'users'       => $design ? User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']) : [],
            'roles'       => $design ? Role::orderBy('name')->pluck('name') : [],
            'presets'     => DataCollectionFormAssignment::PRESETS,
            'activity'    => $design
                ? DataCollectionActivityLog::with('user:id,name')->where('form_id', $form->id)->latest('id')->limit(20)->get()
                : [],
            'can'         => ['permissions' => $permissions, 'design' => $design],
        ]);
    }

    public function update(Request $request, DataCollectionForm $form): RedirectResponse
    {
        $this->authorize('design', $form);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $this->service->updateForm($form, $request->user(), $data);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, DataCollectionForm $form): RedirectResponse
    {
        $this->authorize('design', $form);

        if ($form->submissions()->withTrashed()->exists()) {
            return back()->with('error', 'This form has submissions, so it can only be archived, not deleted.');
        }

        $projectId = $form->project_id;
        $form->delete();

        return redirect()->route('data-collection.projects.show', $projectId)->with('success', 'Form deleted.');
    }

    public function upload(Request $request, DataCollectionForm $form): RedirectResponse
    {
        $this->authorize('design', $form);

        $request->validate([
            'xlsform' => 'required|file|max:5120|mimes:xlsx',
        ]);

        $version = $this->service->uploadXlsForm($form, $request->user(), $request->file('xlsform'));

        return $version->errors
            ? back()->with('error', 'The XLSForm has '.count($version->errors).' problem(s). Fix them and upload it again.')
            : back()->with('success', "Version {$version->version_number} uploaded with no problems. Deploy it to send it to the app.");
    }

    public function deploy(Request $request, DataCollectionForm $form, DataCollectionFormVersion $version): RedirectResponse
    {
        $this->authorize('design', $form);
        abort_unless($version->form_id === $form->id, 404);

        try {
            $this->service->deployVersion($version, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Version {$version->version_number} deployed. Devices pick it up on their next sync.");
    }

    public function archive(Request $request, DataCollectionForm $form): RedirectResponse
    {
        $this->authorize('design', $form);

        $archive = $request->boolean('archived');
        $this->service->setArchived($form, $request->user(), $archive);

        return back()->with('success', $archive ? 'Form archived: it no longer appears in the app.' : 'Form restored.');
    }

    // The uploaded XLSForm of a version, as the designer sent it.
    public function source(DataCollectionForm $form, DataCollectionFormVersion $version)
    {
        $this->authorize('design', $form);
        abort_unless($version->form_id === $form->id && $version->source_path, 404);

        return Storage::disk('local')->download($version->source_path, $version->source_name ?: "form-{$form->id}-v{$version->version_number}.xlsx");
    }

    public function assign(Request $request, DataCollectionForm $form): RedirectResponse
    {
        $this->authorize('design', $form);

        $data = $request->validate([
            'user_ids'      => 'array',
            'user_ids.*'    => 'integer|exists:users,id',
            'crm_role'      => 'nullable|string|max:100',
            'permissions'   => 'required|array|min:1',
            'permissions.*' => 'in:'.implode(',', DataCollectionFormAssignment::PERMISSIONS),
        ]);

        if (empty($data['user_ids']) && empty($data['crm_role'])) {
            return back()->with('error', 'Choose people or a role to assign the form to.');
        }

        foreach ($data['user_ids'] ?? [] as $userId) {
            $this->service->assign($form, $request->user(), (int) $userId, null, $data['permissions']);
        }
        if (! empty($data['crm_role'])) {
            $this->service->assign($form, $request->user(), null, $data['crm_role'], $data['permissions']);
        }

        return back()->with('success', 'Access updated.');
    }

    public function unassign(Request $request, DataCollectionForm $form, DataCollectionFormAssignment $assignment): RedirectResponse
    {
        $this->authorize('design', $form);
        abort_unless($assignment->form_id === $form->id, 404);

        $this->service->unassign($assignment, $request->user());

        return back()->with('success', 'Access removed.');
    }

    public function template(): StreamedResponse
    {
        return $this->xlsxDownload(XlsFormSamples::blank(), 'xlsform-template.xlsx');
    }

    public function sample(): StreamedResponse
    {
        return $this->xlsxDownload(XlsFormSamples::householdSurvey(), 'household-survey-sample.xlsx');
    }

    private function xlsxDownload($book, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            fn () => (new Xlsx($book))->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    // The questions of the current (or latest) version, for a read-only preview.
    private function preview(DataCollectionForm $form): array
    {
        $version = $form->currentVersion ?? $form->versions()->first();
        if (! $version) {
            return [];
        }

        $rows = [];
        $walk = function (array $nodes, int $depth) use (&$walk, &$rows) {
            foreach ($nodes as $node) {
                $rows[] = [
                    'depth'    => $depth,
                    'type'     => $node['type'] ?? '',
                    'name'     => $node['name'] ?? '',
                    'label'    => SchemaWalker::label($node['label'] ?? null),
                    'required' => isset($node['required']),
                    'relevant' => $node['relevant'] ?? null,
                    'pii'      => ! empty($node['pii']),
                ];
                if (in_array($node['type'] ?? null, ['group', 'repeat'], true)) {
                    $walk($node['children'] ?? [], $depth + 1);
                }
            }
        };
        $walk($version->schema['children'] ?? [], 0);

        return ['version' => $version->version_number, 'rows' => $rows];
    }
}
