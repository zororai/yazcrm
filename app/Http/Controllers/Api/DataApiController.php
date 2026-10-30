<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BeneficiaryAttendance;
use App\Models\BeneficiaryProfile;
use App\Models\DataCollectionForm;
use App\Models\DataCollectionProject;
use App\Models\DataCollectionSubmission;
use App\Services\DataCollection\FormFieldCatalog;
use App\Services\DataCollection\SubmissionExporter;
use App\Services\DataCollection\SubmissionFilters;
use App\Support\DataCollection\FormAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Kobo-style read-only data feed for analysts (Power BI, Excel, R): discover
// projects/forms, read a form's data dictionary, and pull its submissions as
// flat rows: OData-style JSON pages ($top/$skip, @odata.nextLink) or one CSV.
// Personal data (PII questions) is masked unless the account may see it.
// Filters: from, to (collected date), status, enumerator, as in the CRM.
#[OA\Tag(name: 'Data API', description: 'Read-only analyst access (Power BI, Excel, R). Basic auth with CRM email/password, or Bearer token.')]
class DataApiController extends Controller
{
    #[OA\Get(
        path: '/api/data/forms',
        tags: ['Data API'],
        summary: 'List data collection forms with links to their data',
        security: [['sanctum' => []], ['basic' => []]],
        responses: [new OA\Response(response: 200, description: 'Forms with project, status, submission count and data URLs')],
    )]
    public function forms(Request $request): JsonResponse
    {
        $forms = DataCollectionForm::with(['project:id,code,name', 'currentVersion:id,version_number'])
            ->withCount('submissions')
            ->orderBy('project_id')
            ->orderBy('name')
            ->get()
            ->map(fn (DataCollectionForm $f) => [
                'id'          => $f->id,
                'code'        => $f->code,
                'name'        => $f->name,
                'status'      => $f->status,
                'version'     => $f->currentVersion?->version_number,
                'project'     => $f->project?->only('id', 'code', 'name'),
                'submissions' => $f->submissions_count,
                'urls'        => [
                    'definition' => url("/api/data/forms/{$f->id}"),
                    'data_json'  => url("/api/data/forms/{$f->id}/submissions"),
                    'data_csv'   => url("/api/data/forms/{$f->id}/submissions.csv"),
                ],
            ]);

        return response()->json([
            'forms'    => $forms,
            'datasets' => [
                'beneficiary_profiles'    => url('/api/data/beneficiary-profiles.csv'),
                'beneficiary_attendances' => url('/api/data/beneficiary-attendances.csv'),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/data/projects',
        tags: ['Data API'],
        summary: 'List data collection projects and their forms',
        security: [['sanctum' => []], ['basic' => []]],
        responses: [new OA\Response(response: 200, description: 'Projects with forms')],
    )]
    public function projects(): JsonResponse
    {
        return response()->json([
            'projects' => DataCollectionProject::with('forms:id,project_id,code,name,status')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'description', 'status', 'start_date', 'end_date']),
        ]);
    }

    #[OA\Get(
        path: '/api/data/forms/{form}',
        tags: ['Data API'],
        summary: 'Data dictionary: every question across all versions, with its column name, type and choices',
        security: [['sanctum' => []], ['basic' => []]],
        parameters: [new OA\Parameter(name: 'form', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Questions / data dictionary')],
    )]
    public function form(DataCollectionForm $form): JsonResponse
    {
        $catalog = FormFieldCatalog::for($form);

        return response()->json([
            'id'        => $form->id,
            'code'      => $form->code,
            'name'      => $form->name,
            'project'   => $form->project?->only('id', 'code', 'name'),
            'versions'  => $form->versions()->get(['id', 'version_number', 'version_label', 'status', 'deployed_at']),
            'meta'      => ['_id', '_uuid', '_status', '_version', '_submitted_by', '_collected_at', '_received_at', '_lat', '_lng', '_completion'],
            'questions' => array_values(array_map(fn ($f) => [
                'column'  => $f['path'],
                'label'   => $f['label'],
                'type'    => $f['type'],
                'repeat'  => $f['repeat'],
                'pii'     => $f['pii'],
                'choices' => $f['choices'],
            ], $catalog->fields)),
            'repeats'   => array_values($catalog->repeats),
        ]);
    }

    #[OA\Get(
        path: '/api/data/forms/{form}/submissions',
        tags: ['Data API'],
        summary: 'Submissions as OData-style JSON pages',
        description: 'Returns {"@odata.context", "@odata.count", "value": [rows], "@odata.nextLink"}. Rows: _-prefixed metadata, then one key per top-level question; repeats are arrays of objects.',
        security: [['sanctum' => []], ['basic' => []]],
        parameters: [
            new OA\Parameter(name: 'form', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: '$top', in: 'query', schema: new OA\Schema(type: 'integer', default: 500, maximum: 5000)),
            new OA\Parameter(name: '$skip', in: 'query', schema: new OA\Schema(type: 'integer', default: 0)),
            new OA\Parameter(name: 'from', in: 'query', description: 'Collected on or after (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', description: 'Collected on or before (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'not_approved', 'on_hold'])),
            new OA\Parameter(name: 'values', in: 'query', description: 'Choice answers as "label" (default) or raw "value"', schema: new OA\Schema(type: 'string', enum: ['label', 'value'])),
        ],
        responses: [new OA\Response(response: 200, description: 'OData-style page', content: new OA\JsonContent(example: [
            '@odata.context'  => 'https://crm.example/api/data/forms/12/submissions',
            '@odata.count'    => 1250,
            'value'           => [['_id' => 481, '_status' => 'approved', '_collected_at' => '2026-10-30T09:15:00+02:00', 'respondent_name' => '•••', 'ward' => 'Ward 2']],
            '@odata.nextLink' => 'https://crm.example/api/data/forms/12/submissions?%24top=500&%24skip=500',
        ]))],
    )]
    public function submissions(Request $request, DataCollectionForm $form): JsonResponse
    {
        $catalog = FormFieldCatalog::for($form);
        $hidden = $catalog->hiddenFor(FormAccess::canSeePii($request->user()));
        $labels = $request->query('values') !== 'value';

        $top = min(max((int) ($request->query('$top') ?? $request->query('per_page', 500)), 1), 5000);
        $skip = max((int) ($request->query('$skip') ?? 0), 0);

        $query = SubmissionFilters::query($form, $this->filters($request), $hidden)
            ->with(['submittedBy:id,name', 'formVersion:id,version_number']);
        $count = (clone $query)->count();
        $rows = $query->orderBy('id')->skip($skip)->take($top)->get();

        $next = $skip + $top < $count
            ? $request->fullUrlWithQuery(['$top' => $top, '$skip' => $skip + $top])
            : null;

        return response()->json([
            '@odata.context'  => $request->url(),
            '@odata.count'    => $count,
            'value'           => $rows->map(fn ($s) => $this->row($s, $catalog, $hidden, $labels))->values(),
            '@odata.nextLink' => $next,
        ]);
    }

    #[OA\Get(
        path: '/api/data/forms/{form}/submissions.csv',
        tags: ['Data API'],
        summary: 'All submissions as one CSV (same filters as the JSON endpoint; select_multiple split into 1/0 columns)',
        security: [['sanctum' => []], ['basic' => []]],
        parameters: [new OA\Parameter(name: 'form', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'text/csv')],
    )]
    public function submissionsCsv(Request $request, DataCollectionForm $form): StreamedResponse
    {
        $exporter = new SubmissionExporter(
            $form,
            $this->filters($request),
            $request->query('values') !== 'value',
            FormAccess::canSeePii($request->user()),
        );

        return response()->streamDownload(
            fn () => $exporter->write('csv', 'php://output'),
            "{$form->code}-submissions.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    #[OA\Get(
        path: '/api/data/beneficiary-profiles.csv',
        tags: ['Data API'],
        summary: 'Beneficiary profiling tool dataset (CSV)',
        security: [['sanctum' => []], ['basic' => []]],
        responses: [new OA\Response(response: 200, description: 'text/csv')],
    )]
    public function beneficiaryProfilesCsv(): StreamedResponse
    {
        $columns = array_diff((new BeneficiaryProfile())->getFillable(), ['signature_path']);
        $header  = ['id', ...$columns, 'has_signature'];

        return $this->csv('beneficiary-profiles.csv', $header, function ($out) use ($columns) {
            BeneficiaryProfile::orderBy('id')->chunk(1000, function ($chunk) use ($out, $columns) {
                foreach ($chunk as $p) {
                    $row = [$p->id];
                    foreach ($columns as $c) {
                        $row[] = $this->cell($p->getAttribute($c));
                    }
                    $row[] = $p->signature_path ? 1 : 0;
                    fputcsv($out, $row);
                }
            });
        });
    }

    #[OA\Get(
        path: '/api/data/beneficiary-attendances.csv',
        tags: ['Data API'],
        summary: 'Beneficiary attendance dataset, one row per attendee with the activity (CSV)',
        security: [['sanctum' => []], ['basic' => []]],
        responses: [new OA\Response(response: 200, description: 'text/csv')],
    )]
    public function beneficiaryAttendancesCsv(): StreamedResponse
    {
        $header = [
            'id', 'activity_id', 'activity_name', 'activity_date', 'compiled_by', 'reviewed_by',
            'full_name', 'sex', 'age', 'district', 'phone_number', 'id_number_or_dob', 'has_signature', 'captured_at',
        ];

        return $this->csv('beneficiary-attendances.csv', $header, function ($out) {
            BeneficiaryAttendance::with('activity')->orderBy('id')->chunk(1000, function ($chunk) use ($out) {
                foreach ($chunk as $a) {
                    fputcsv($out, [
                        $a->id, $a->beneficiary_activity_id, $a->activity?->activity_name,
                        $a->activity?->activity_date?->toDateString(), $a->activity?->compiled_by, $a->activity?->reviewed_by,
                        $a->full_name, $a->sex, $a->age, $a->district, $a->phone_number, $a->id_number_or_dob,
                        $a->signature_path ? 1 : 0, $a->captured_at?->toIso8601String(),
                    ]);
                }
            });
        });
    }

    // ── Helpers ──

    // The CRM's filters, plus the older submitted_after/submitted_before names.
    private function filters(Request $request): array
    {
        $filters = SubmissionFilters::fromRequest($request);
        $filters['from'] ??= $request->query('submitted_after');
        $filters['to'] ??= $request->query('submitted_before');

        return array_filter($filters, fn ($v) => $v !== null && $v !== '');
    }

    private function row(DataCollectionSubmission $s, FormFieldCatalog $catalog, array $hidden, bool $labels): array
    {
        $data = $catalog->mask($s->data ?? [], $hidden);

        $row = [
            '_id'           => $s->id,
            '_uuid'         => $s->client_uuid,
            '_status'       => $s->status,
            '_version'      => $s->formVersion?->version_number,
            '_submitted_by' => $s->submittedBy?->name,
            '_collected_at' => $s->collected_at?->toIso8601String(),
            '_received_at'  => $s->received_at?->toIso8601String(),
            '_lat'          => $s->lat,
            '_lng'          => $s->lng,
            '_completion'   => $s->completion,
        ];

        foreach ($catalog->topLevel() as $path => $field) {
            $value = $data[$field['name']] ?? null;
            if ($labels && $field['choices'] && $value !== null && $value !== '•••') {
                $value = is_array($value)
                    ? array_map(fn ($v) => $field['choices'][$v] ?? $v, $value)
                    : ($field['choices'][$value] ?? $value);
            }
            $row[$path] = $value;
        }
        foreach ($catalog->repeats as $name => $repeat) {
            if ($repeat['parent'] === null) {
                $row[$name] = $data[$name] ?? [];
            }
        }

        return $row;
    }

    // One CSV cell: lists joined with "; " (select_multiple), booleans as 1/0.
    private function cell(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => implode('; ', array_map(fn ($v) => is_scalar($v) ? $v : json_encode($v), $value)),
            is_bool($value)  => (int) $value,
            $value instanceof \DateTimeInterface => $value->format($value->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i:s'),
            default          => $value,
        };
    }

    private function csv(string $filename, array $header, callable $writeRows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $writeRows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows accents correctly
            fputcsv($out, $header);
            $writeRows($out);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
