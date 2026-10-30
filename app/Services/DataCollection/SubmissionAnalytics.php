<?php

namespace App\Services\DataCollection;

use App\Models\DataCollectionForm;
use App\Models\DataCollectionRejection;
use App\Models\DataCollectionSubmissionValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

// Summary report, group-by, cross-tab, dashboard and map points for a form.
// Everything is aggregated in SQL over the flattened answer rows, restricted
// to the submissions matching the active filters.
class SubmissionAnalytics
{
    private const TEXT_SAMPLE = 25;

    public function __construct(
        private readonly DataCollectionForm $form,
        private readonly FormFieldCatalog $catalog,
        private readonly array $filters,
        private readonly array $hidden,
    ) {
    }

    public static function make(DataCollectionForm $form, array $filters, bool $canSeePii): self
    {
        $catalog = FormFieldCatalog::for($form);

        return new self($form, $catalog, $filters, $catalog->hiddenFor($canSeePii));
    }

    public function catalog(): FormFieldCatalog
    {
        return $this->catalog;
    }

    private function submissions(): Builder
    {
        return SubmissionFilters::query($this->form, $this->filters, $this->hidden);
    }

    private function ids(): Builder
    {
        return $this->submissions()->select('data_collection_submissions.id');
    }

    private function values(string $field)
    {
        return DataCollectionSubmissionValue::query()
            ->where('form_id', $this->form->id)
            ->where('field', $field)
            ->whereIn('submission_id', $this->ids());
    }

    // ── Summary ────────────────────────────────────────────────────────────

    public function summary(?string $groupBy = null): array
    {
        $groupBy = $groupBy && isset($this->catalog->fields[$groupBy]) && ! in_array($groupBy, $this->hidden, true)
            && in_array($this->catalog->fields[$groupBy]['type'], ['select_one', 'select_multiple'], true)
            && $this->catalog->fields[$groupBy]['repeat'] === null ? $groupBy : null;

        $out = [];
        foreach ($this->catalog->fields as $path => $field) {
            $out[] = $this->summariseField($field, $groupBy);
        }

        $repeats = [];
        foreach ($this->catalog->repeats as $name => $repeat) {
            $repeats[] = $this->repeatCounts($name) + ['name' => $name, 'label' => $repeat['label']];
        }

        return [
            'total'    => $this->submissions()->count(),
            'group_by' => $groupBy,
            'groups'   => $groupBy ? $this->catalog->fields[$groupBy]['choices'] : null,
            'fields'   => $out,
            'repeats'  => $repeats,
        ];
    }

    private function summariseField(array $field, ?string $groupBy): array
    {
        $base = [
            'path'   => $field['path'],
            'label'  => $field['label'],
            'type'   => $field['type'],
            'repeat' => $field['repeat'],
        ];

        if (in_array($field['path'], $this->hidden, true)) {
            return $base + ['kind' => 'hidden'];
        }

        // Repeat answers count per instance (rows); top-level per submission.
        $countExpr = $field['repeat'] ? 'count(*)' : 'count(distinct submission_id)';
        $answered = (int) $this->values($field['path'])->selectRaw("{$countExpr} as n")->value('n');

        switch ($field['type']) {
            case 'select_one':
            case 'select_multiple':
                $counts = $this->values($field['path'])
                    ->selectRaw("value, {$countExpr} as n")
                    ->groupBy('value')
                    ->pluck('n', 'value')
                    ->map(fn ($n) => (int) $n);

                $rows = [];
                foreach ($field['choices'] + $counts->map(fn () => null)->all() as $name => $label) {
                    $n = $counts[$name] ?? 0;
                    $rows[] = [
                        'value' => (string) $name,
                        'label' => $label ?? (string) $name,
                        'count' => $n,
                        'pct'   => $answered ? round($n / $answered * 100, 1) : 0,
                    ];
                }

                $result = $base + ['kind' => 'choices', 'answered' => $answered, 'rows' => $rows];
                if ($groupBy && $groupBy !== $field['path'] && ! $field['repeat']) {
                    $result['grouped'] = $this->crossCounts($field['path'], $groupBy);
                }

                return $result;

            case 'integer':
            case 'decimal':
            case 'calculate':
                $numbers = $this->values($field['path'])->whereNotNull('value_num')->pluck('value_num')->map(fn ($v) => (float) $v)->all();
                if ($field['type'] === 'calculate' && ! $numbers) {
                    return $base + ['kind' => 'text', 'answered' => $answered, 'samples' => $this->samples($field['path'])];
                }

                $result = $base + ['kind' => 'numeric', 'answered' => $answered] + self::stats($numbers);
                if ($groupBy && ! $field['repeat']) {
                    $result['grouped'] = $this->groupedStats($field['path'], $groupBy);
                }

                return $result;

            case 'date':
            case 'datetime':
                $timeline = $this->values($field['path'])
                    ->whereNotNull('value_date')
                    ->selectRaw("value_date as day, {$countExpr} as n")
                    ->groupBy('value_date')
                    ->orderBy('value_date')
                    ->get()
                    ->map(fn ($r) => ['day' => substr((string) $r->day, 0, 10), 'count' => (int) $r->n]);

                return $base + ['kind' => 'timeline', 'answered' => $answered, 'timeline' => $timeline];

            case 'text':
            case 'time':
                return $base + ['kind' => 'text', 'answered' => $answered, 'samples' => $this->samples($field['path'])];

            default: // geopoint, photo
                return $base + ['kind' => 'count', 'answered' => $answered];
        }
    }

    private function samples(string $path): array
    {
        return $this->values($path)->orderByDesc('submission_id')->limit(self::TEXT_SAMPLE)->pluck('value')->all();
    }

    // For each repeat: how many instances submissions have.
    private function repeatCounts(string $repeat): array
    {
        $perSubmission = DataCollectionSubmissionValue::query()
            ->where('form_id', $this->form->id)
            ->where('field', 'like', str_replace(['%', '_'], ['\%', '\_'], $repeat).'/%')
            ->whereIn('submission_id', $this->ids())
            ->selectRaw('submission_id, max(instance) as n')
            ->groupBy('submission_id')
            ->pluck('n')
            ->map(fn ($n) => (float) $n)
            ->all();

        return [
            'submissions' => count($perSubmission),
            'instances'   => (int) array_sum($perSubmission),
        ] + self::stats($perSubmission);
    }

    // value of $field × value of $groupBy → submissions.
    private function crossCounts(string $field, string $groupBy): array
    {
        $rows = DataCollectionSubmissionValue::query()
            ->from('data_collection_submission_values as a')
            ->join('data_collection_submission_values as g', 'g.submission_id', '=', 'a.submission_id')
            ->where('a.form_id', $this->form->id)
            ->where('a.field', $field)
            ->where('g.field', $groupBy)
            ->whereIn('a.submission_id', $this->ids())
            ->selectRaw('a.value as v, g.value as g, count(distinct a.submission_id) as n')
            ->groupBy('a.value', 'g.value')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->v][$row->g] = (int) $row->n;
        }

        return $out;
    }

    private function groupedStats(string $field, string $groupBy): array
    {
        $pairs = DataCollectionSubmissionValue::query()
            ->from('data_collection_submission_values as a')
            ->join('data_collection_submission_values as g', 'g.submission_id', '=', 'a.submission_id')
            ->where('a.form_id', $this->form->id)
            ->where('a.field', $field)
            ->where('g.field', $groupBy)
            ->whereNotNull('a.value_num')
            ->whereIn('a.submission_id', $this->ids())
            ->get(['g.value as g', 'a.value_num as n']);

        $byGroup = [];
        foreach ($pairs as $pair) {
            $byGroup[$pair->g][] = (float) $pair->n;
        }

        return array_map(fn ($numbers) => array_intersect_key(self::stats($numbers), array_flip(['count', 'mean', 'median', 'min', 'max'])), $byGroup);
    }

    // ── Cross-tabulation ───────────────────────────────────────────────────

    public function crosstab(string $rowField, string $colField): ?array
    {
        $a = $this->catalog->fields[$rowField] ?? null;
        $b = $this->catalog->fields[$colField] ?? null;
        if (! $a || ! $b || $rowField === $colField || array_intersect([$rowField, $colField], $this->hidden)) {
            return null;
        }

        // Two answers in the same repeat pair up per instance.
        $sameRepeat = $a['repeat'] !== null && $a['repeat'] === $b['repeat'];

        $rows = DataCollectionSubmissionValue::query()
            ->from('data_collection_submission_values as a')
            ->join('data_collection_submission_values as b', function ($join) use ($sameRepeat) {
                $join->on('b.submission_id', '=', 'a.submission_id');
                if ($sameRepeat) {
                    $join->on('b.instance', '=', 'a.instance');
                }
            })
            ->where('a.form_id', $this->form->id)
            ->where('a.field', $rowField)
            ->where('b.field', $colField)
            ->whereIn('a.submission_id', $this->ids())
            ->selectRaw('a.value as r, b.value as c, count(*) as n')
            ->groupBy('a.value', 'b.value')
            ->get();

        $cells = [];
        $rowValues = [];
        $colValues = [];
        foreach ($rows as $row) {
            $cells[$row->r][$row->c] = (int) $row->n;
            $rowValues[$row->r] = true;
            $colValues[$row->c] = true;
        }

        $labels = fn (array $field, array $seen) => collect($field['choices'] + array_map(fn () => null, $seen))
            ->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label ?? (string) $value])
            ->filter(fn ($v) => isset($seen[$v['value']]) || $field['choices'])
            ->values()->all();

        return [
            'row'     => ['path' => $rowField, 'label' => $a['label'], 'values' => $labels($a, $rowValues)],
            'col'     => ['path' => $colField, 'label' => $b['label'], 'values' => $labels($b, $colValues)],
            'cells'   => $cells,
            'per'     => $sameRepeat ? 'instance' : 'submission',
        ];
    }

    // ── Dashboard ──────────────────────────────────────────────────────────

    public function dashboard(): array
    {
        $total = $this->submissions()->count();

        $perDay = $this->submissions()
            ->whereNotNull('collected_at')
            ->selectRaw('DATE(collected_at) as day, count(*) as n')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => ['day' => (string) $r->day, 'count' => (int) $r->n]);

        $perEnumerator = $this->submissions()
            ->selectRaw('submitted_by, count(*) as n, avg(completion) as completion')
            ->groupBy('submitted_by')
            ->orderByDesc('n')
            ->get();
        $names = User::whereIn('id', $perEnumerator->pluck('submitted_by'))->pluck('name', 'id');

        $durations = $this->submissions()
            ->whereNotNull('started_at')
            ->whereNotNull('collected_at')
            ->limit(20000)
            ->get(['started_at', 'collected_at'])
            ->map(fn ($s) => $s->collected_at->getTimestamp() - $s->started_at->getTimestamp())
            ->filter(fn ($seconds) => $seconds >= 0 && $seconds < 86400)
            ->values()->all();

        $rejections = DataCollectionRejection::where('form_id', $this->form->id)
            ->when($this->filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($this->filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->when($this->filters['enumerator'] ?? null, fn ($q, $u) => $q->where('user_id', (int) $u));

        return [
            'total'            => $total,
            'by_status'        => $this->submissions()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n),
            'per_day'          => $perDay,
            'per_enumerator'   => $perEnumerator->map(fn ($r) => [
                'user_id'    => $r->submitted_by,
                'name'       => $names[$r->submitted_by] ?? 'Unknown',
                'count'      => (int) $r->n,
                'completion' => $r->completion !== null ? round((float) $r->completion, 1) : null,
            ])->values(),
            'completion_rate'  => $total ? round((float) $this->submissions()->avg('completion'), 1) : null,
            'avg_duration_sec' => $durations ? (int) round(array_sum($durations) / count($durations)) : null,
            'timed'            => count($durations),
            'rejected'         => (clone $rejections)->count(),
            'recent_rejections' => (clone $rejections)->with('user:id,name')->latest('id')->limit(10)
                ->get(['id', 'user_id', 'client_uuid', 'reason', 'errors', 'created_at']),
        ];
    }

    // ── Map ────────────────────────────────────────────────────────────────

    // Points from a geopoint question (or the submission's own location when
    // $field is null), optionally coloured by a select_one answer.
    public function points(?string $field, ?string $colorBy, int $limit = 5000): array
    {
        $colorBy = $colorBy && isset($this->catalog->fields[$colorBy]) && ! in_array($colorBy, $this->hidden, true) ? $colorBy : null;
        $colors = $colorBy
            ? DataCollectionSubmissionValue::where('form_id', $this->form->id)->where('field', $colorBy)
                ->whereIn('submission_id', $this->ids())->pluck('value', 'submission_id')
            : collect();

        if ($field && isset($this->catalog->fields[$field]) && $this->catalog->fields[$field]['type'] === 'geopoint') {
            $rows = $this->values($field)->limit($limit)->get(['submission_id', 'value', 'instance']);
            $points = $rows->map(function ($row) use ($colors) {
                [$lat, $lng] = array_map('floatval', array_pad(explode(' ', (string) $row->value), 2, null));

                return ['id' => $row->submission_id, 'lat' => $lat, 'lng' => $lng, 'color' => $colors[$row->submission_id] ?? null];
            });
        } else {
            $points = $this->submissions()->whereNotNull('lat')->whereNotNull('lng')->limit($limit)
                ->get(['id', 'lat', 'lng'])
                ->map(fn ($s) => ['id' => $s->id, 'lat' => $s->lat, 'lng' => $s->lng, 'color' => $colors[$s->id] ?? null]);
        }

        return [
            'points'  => $points->filter(fn ($p) => abs($p['lat']) <= 90 && abs($p['lng']) <= 180 && ($p['lat'] || $p['lng']))->values(),
            'legend'  => $colorBy ? $this->catalog->fields[$colorBy]['choices'] : [],
        ];
    }

    // ── Statistics ─────────────────────────────────────────────────────────

    public static function stats(array $numbers): array
    {
        $count = count($numbers);
        if (! $count) {
            return ['count' => 0, 'mean' => null, 'median' => null, 'mode' => null, 'min' => null, 'max' => null, 'stddev' => null, 'histogram' => []];
        }

        sort($numbers);
        $mean = array_sum($numbers) / $count;
        $mid = intdiv($count, 2);
        $median = $count % 2 ? $numbers[$mid] : ($numbers[$mid - 1] + $numbers[$mid]) / 2;

        $frequency = [];
        foreach ($numbers as $n) {
            $key = (string) $n;
            $frequency[$key] = ($frequency[$key] ?? 0) + 1;
        }
        arsort($frequency);
        $mode = (float) array_key_first($frequency);

        $variance = $count > 1 ? array_sum(array_map(fn ($n) => ($n - $mean) ** 2, $numbers)) / ($count - 1) : 0.0;

        return [
            'count'     => $count,
            'mean'      => round($mean, 2),
            'median'    => round($median, 2),
            'mode'      => $mode,
            'min'       => $numbers[0],
            'max'       => $numbers[$count - 1],
            'stddev'    => round(sqrt($variance), 2),
            'histogram' => self::histogram($numbers),
        ];
    }

    // Up to 10 equal-width bins over the sorted numbers.
    private static function histogram(array $sorted): array
    {
        $min = $sorted[0];
        $max = end($sorted);
        if ($min == $max) {
            return [['from' => $min, 'to' => $max, 'count' => count($sorted)]];
        }

        $bins = min(10, max(1, count(array_unique($sorted))));
        $width = ($max - $min) / $bins;
        $counts = array_fill(0, $bins, 0);
        foreach ($sorted as $n) {
            $counts[min($bins - 1, (int) floor(($n - $min) / $width))]++;
        }

        $out = [];
        foreach ($counts as $i => $c) {
            $out[] = ['from' => round($min + $i * $width, 2), 'to' => round($min + ($i + 1) * $width, 2), 'count' => $c];
        }

        return $out;
    }
}
