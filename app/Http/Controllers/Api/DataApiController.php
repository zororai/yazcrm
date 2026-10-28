<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BeneficiaryAttendance;
use App\Models\BeneficiaryProfile;
use App\Models\DataCollectionForm;
use App\Models\DataCollectionProject;
use App\Models\DataCollectionSubmission;
use App\Support\DataCollection\QuestionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Kobo-style read-only data API for analysts: discover projects/forms, read
// a form's question definitions, and pull its submissions as flat rows —
// JSON (paged) or CSV (whole dataset, for Power BI / Excel / R / Stata).
// Every row has the same columns: _-prefixed metadata, then one column per
// question across all versions of the form.
#[OA\Tag(name: 'Data API', description: 'Read-only analyst access (Power BI, Excel, R). Basic auth with CRM email/password, or Bearer token.')]
class DataApiController extends Controller
{
    private const DEFAULT_STATUSES = ['submitted', 'under_review', 'approved', 'rejected', 'correction_required'];

    #[OA\Get(
        path: '/api/data/forms',
        tags: ['Data API'],
        summary: 'List data collection forms with links to their data',
        security: [['sanctum' => []], ['basic' => []]],
        responses: [new OA\Response(response: 200, description: 'Forms with project, status, submission count and data URLs')],
    )]
    public function forms(Request $request): JsonResponse
    {
        $forms = DataCollectionForm::with('project:id,code,name')
            ->withCount(['submissions' => fn ($q) => $q->whereIn('status', self::DEFAULT_STATUSES)])
            ->orderBy('project_id')
            ->orderBy('name')
            ->get()
            ->map(fn (DataCollectionForm $f) => [
                'id'          => $f->id,
                'code'        => $f->code,
                'name'        => $f->name,
                'status'      => $f->status,
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
        summary: 'Form definition: every question (across all versions) with its column name, type and options',
        security: [['sanctum' => []], ['basic' => []]],
        parameters: [new OA\Parameter(name: 'form', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Questions / data dictionary')],
    )]
    public function form(DataCollectionForm $form): JsonResponse
    {
        return response()->json([
            'id'        => $form->id,
            'code'      => $form->code,
            'name'      => $form->name,
            'project'   => $form->project?->only('id', 'code', 'name'),
            'versions'  => $form->versions()->get(['id', 'version_number', 'version_label', 'status', 'published_at']),
            'meta'      => ['_id', '_uid', '_status', '_version', '_submitted_by', '_started_at', '_submitted_at', '_completion'],
            'questions' => $this->questions($form)->values(),
        ]);
    }

    #[OA\Get(
        path: '/api/data/forms/{form}/submissions',
        tags: ['Data API'],
        summary: 'Submissions as flat JSON rows (paged)',
        security: [['sanctum' => []], ['basic' => []]],
        parameters: [
            new OA\Parameter(name: 'form', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', description: 'Comma list. Default: everything except draft and cancelled', schema: new OA\Schema(type: 'string', example: 'approved')),
            new OA\Parameter(name: 'submitted_after', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'submitted_before', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'headers', in: 'query', description: 'Column names: "label" (default) or "id"', schema: new OA\Schema(type: 'string', enum: ['label', 'id'])),
            new OA\Parameter(name: 'values', in: 'query', description: 'Choice answers as "label" (default) or raw "value"', schema: new OA\Schema(type: 'string', enum: ['label', 'value'])),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 500, maximum: 5000)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paged rows: { count, next, previous, results: [...] }')],
    )]
    public function submissions(Request $request, DataCollectionForm $form): JsonResponse
    {
        $questions = $this->questions($form);
        $page      = $this->filtered($request, $form)
            ->paginate(min(max($request->integer('per_page', 500), 1), 5000))
            ->withQueryString();

        return response()->json([
            'count'    => $page->total(),
            'next'     => $page->nextPageUrl(),
            'previous' => $page->previousPageUrl(),
            'results'  => collect($page->items())->map(fn ($s) => $this->row($s, $questions, $request, false)),
        ]);
    }

    #[OA\Get(
        path: '/api/data/forms/{form}/submissions.csv',
        tags: ['Data API'],
        summary: 'All submissions as one CSV (same filters as the JSON endpoint)',
        security: [['sanctum' => []], ['basic' => []]],
        parameters: [new OA\Parameter(name: 'form', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'text/csv')],
    )]
    public function submissionsCsv(Request $request, DataCollectionForm $form): StreamedResponse
    {
        $questions = $this->questions($form);
        $header    = array_keys($this->row(new DataCollectionSubmission(), $questions, $request, true));
        $query     = $this->filtered($request, $form);

        return $this->csv("{$form->code}-submissions.csv", $header, function ($out) use ($query, $questions, $request) {
            $query->chunk(1000, function ($chunk) use ($out, $questions, $request) {
                foreach ($chunk as $s) {
                    fputcsv($out, array_values($this->row($s, $questions, $request, true)));
                }
            });
        });
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

    /**
     * Every question across all versions, keyed by question id (the key
     * answers are stored under). The newest version's wording wins.
     */
    private function questions(DataCollectionForm $form): Collection
    {
        $questions = collect();

        foreach ($form->versions()->get()->reverse() as $version) { // oldest → newest
            foreach ($version->schema['sections'] ?? [] as $section) {
                foreach ($section['questions'] ?? [] as $q) {
                    $questions[$q['id']] = [
                        'id'       => $q['id'],
                        'label'    => $q['label'] ?: $q['id'],
                        'type'     => $q['type'] ?? QuestionType::TEXT,
                        'section'  => $section['title'] ?? null,
                        'required' => (bool) ($q['required'] ?? false),
                        'options'  => array_values(array_map(
                            fn ($o) => ['value' => $o['value'] ?? '', 'label' => $o['label'] ?? ($o['value'] ?? '')],
                            $q['options'] ?? []
                        )),
                    ];
                }
            }
        }

        // Unique, analyst-friendly column names: the label, suffixed if repeated.
        $seen = [];
        return $questions->map(function ($q) use (&$seen) {
            $name = trim(preg_replace('/\s+/', ' ', $q['label']));
            $seen[$name] = ($seen[$name] ?? 0) + 1;
            $q['column'] = $seen[$name] > 1 ? "{$name} ({$seen[$name]})" : $name;

            return $q;
        });
    }

    private function filtered(Request $request, DataCollectionForm $form)
    {
        $statuses = $request->filled('status')
            ? array_map('trim', explode(',', $request->query('status')))
            : self::DEFAULT_STATUSES;

        return DataCollectionSubmission::with(['submittedBy:id,name', 'formVersion:id,version_number'])
            ->where('form_id', $form->id)
            ->whereIn('status', $statuses)
            ->when($request->filled('submitted_after'), fn ($q) => $q->whereDate('submitted_at', '>=', $request->query('submitted_after')))
            ->when($request->filled('submitted_before'), fn ($q) => $q->whereDate('submitted_at', '<=', $request->query('submitted_before')))
            ->orderBy('id');
    }

    private function row(DataCollectionSubmission $s, Collection $questions, Request $request, bool $flat): array
    {
        $byId   = $request->query('headers') === 'id';
        $raw    = $request->query('values') === 'value';
        $answer = $s->answers ?? [];

        $row = [
            '_id'           => $s->id,
            '_uid'          => $s->submission_uid,
            '_status'       => $s->status,
            '_version'      => $s->formVersion?->version_number,
            '_submitted_by' => $s->submittedBy?->name,
            '_started_at'   => $s->started_at?->toIso8601String(),
            '_submitted_at' => $s->submitted_at?->toIso8601String(),
            '_completion'   => $s->completion_percentage,
        ];

        foreach ($questions as $q) {
            $value = $answer[$q['id']] ?? null;

            if (! $raw && $value !== null && $q['options']) {
                $labels = array_column($q['options'], 'label', 'value');
                $value  = is_array($value)
                    ? array_map(fn ($v) => $labels[$v] ?? $v, $value)
                    : ($labels[$value] ?? $value);
            }
            if ($q['type'] === QuestionType::YES_NO && is_bool($value)) {
                $value = $raw ? (int) $value : ($value ? 'Yes' : 'No');
            }

            $row[$byId ? $q['id'] : $q['column']] = $flat ? $this->cell($value) : $value;
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
