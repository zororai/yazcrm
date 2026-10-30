<?php

namespace App\Services\DataCollection;

use App\Models\DataCollectionForm;
use App\Models\DataCollectionSubmission;
use App\Models\DataCollectionSubmissionMedia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Writes a form's filtered submissions to CSV, XLSX, GeoJSON or a PDF
// summary, reading in chunks so large forms don't load into memory at once.
//   labels: choice labels (and question labels as headers) instead of names
//   select_multiple: one combined column plus a 1/0 column per option
//   repeats: a count column; XLSX adds one sheet per repeat keyed by _parent_id
//   PII: masked unless the requester may see it
class SubmissionExporter
{
    private const CHUNK = 500;

    private FormFieldCatalog $catalog;
    private array $hidden;

    public function __construct(
        private readonly DataCollectionForm $form,
        private readonly array $filters,
        private readonly bool $labels,
        bool $canSeePii,
    ) {
        $this->catalog = FormFieldCatalog::for($form);
        $this->hidden = $this->catalog->hiddenFor($canSeePii);
    }

    // Returns the number of submissions written.
    public function write(string $format, string $path): int
    {
        return match ($format) {
            'csv'     => $this->csv($path),
            'xlsx'    => $this->xlsx($path),
            'geojson' => $this->geojson($path),
            'pdf'     => $this->pdf($path),
        };
    }

    // ── Columns ────────────────────────────────────────────────────────────

    private const META = [
        '_id' => 'Submission ID', '_uuid' => 'Client UUID', '_submitted_by' => 'Enumerator',
        '_collected_at' => 'Collected at', '_received_at' => 'Received at', '_status' => 'Validation status',
        '_version' => 'Form version', '_lat' => 'Latitude', '_lng' => 'Longitude', '_completion' => 'Completion %',
    ];

    // [key => header] for a level: top level, or one repeat.
    private function columns(?string $repeat): array
    {
        $columns = $repeat === null ? self::META : ['_parent_id' => 'Submission ID', '_index' => 'Entry #'];
        $fields = $repeat === null ? $this->catalog->topLevel() : $this->catalog->inRepeat($repeat);

        foreach ($fields as $path => $field) {
            $header = $this->labels ? $field['label'] : $field['name'];
            $columns[$path] = $header;

            if ($field['type'] === 'select_multiple') {
                foreach ($field['choices'] as $name => $label) {
                    $columns[$path.'/'.$name] = $header.'/'.($this->labels ? $label : $name);
                }
            } elseif ($field['type'] === 'geopoint') {
                foreach (['lat', 'lng', 'accuracy'] as $part) {
                    $columns[$path.'#'.$part] = $header.'_'.$part;
                }
            } elseif ($field['type'] === 'photo') {
                $columns[$path.'#url'] = $header.'_url';
            }
        }

        if ($repeat === null) {
            foreach ($this->catalog->repeats as $name => $info) {
                if ($info['parent'] === null) {
                    $columns['#count/'.$name] = ($this->labels ? $info['label'] : $name).'_count';
                }
            }
        }

        return $columns;
    }

    private function row(array $columns, array $answers, string $prefix, array $meta, Collection $media, int $submissionId): array
    {
        $out = [];
        foreach ($columns as $key => $_) {
            if (array_key_exists($key, $meta)) {
                $out[] = $meta[$key];
                continue;
            }
            if (str_starts_with($key, '#count/')) {
                $instances = $answers[substr($key, 7)] ?? [];
                $out[] = is_array($instances) ? count($instances) : 0;
                continue;
            }

            [$path, $part] = array_pad(explode('#', $key, 2), 2, null);
            $field = $this->catalog->fields[$path] ?? null;
            $choiceColumn = null;
            if (! $field && str_contains($path, '/')) {
                // select_multiple split column: "<path>/<choice>"
                $choiceColumn = substr($path, strrpos($path, '/') + 1);
                $path = substr($path, 0, strrpos($path, '/'));
                $field = $this->catalog->fields[$path] ?? null;
            }
            $name = $field['name'] ?? $path;
            $value = $answers[$name] ?? null;

            if (in_array($path, $this->hidden, true)) {
                $out[] = $value === null || $value === '' ? '' : '•••';
                continue;
            }

            if ($choiceColumn !== null) {
                $out[] = $value === null ? '' : (is_array($value) && in_array($choiceColumn, array_map('strval', $value), true) ? 1 : 0);
            } elseif ($part === 'url') {
                $m = $value ? $media->first(fn ($m) => $m->submission_id === $submissionId && $m->field_name === $name && $m->original_name === $value) : null;
                $out[] = $m ? route('data-collection.media.show', $m) : '';
            } elseif ($part !== null) {
                $out[] = is_array($value) ? ($value[$part] ?? '') : '';
            } else {
                $out[] = $this->display($field, $value);
            }
        }

        return $out;
    }

    private function display(?array $field, mixed $value): string|int|float
    {
        if ($value === null) {
            return '';
        }
        if (is_array($value)) {
            if (array_key_exists('lat', $value)) {
                return trim(($value['lat'] ?? '').' '.($value['lng'] ?? ''));
            }
            $value = array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value);
            if ($this->labels && $field) {
                $value = array_map(fn ($v) => $field['choices'][$v] ?? $v, $value);
            }

            return implode($this->labels ? '; ' : ' ', $value);
        }
        if ($this->labels && $field && $field['choices'] && is_scalar($value)) {
            return $field['choices'][(string) $value] ?? $value;
        }

        return is_bool($value) ? ($value ? 'true' : 'false') : $value;
    }

    private function meta(DataCollectionSubmission $s): array
    {
        return [
            '_id'           => $s->id,
            '_uuid'         => $s->client_uuid,
            '_submitted_by' => $s->submittedBy?->name ?? '',
            '_collected_at' => $s->collected_at?->format('Y-m-d H:i:s') ?? '',
            '_received_at'  => $s->received_at?->format('Y-m-d H:i:s') ?? '',
            '_status'       => $s->status,
            '_version'      => $s->formVersion?->version_number,
            '_lat'          => $s->lat,
            '_lng'          => $s->lng,
            '_completion'   => $s->completion,
        ];
    }

    // Calls $fn(submission, media for the chunk) for every matching submission.
    private function each(callable $fn): int
    {
        $count = 0;
        SubmissionFilters::query($this->form, $this->filters, $this->hidden)
            ->with(['submittedBy:id,name', 'formVersion:id,version_number'])
            ->chunkById(self::CHUNK, function ($chunk) use ($fn, &$count) {
                $media = DataCollectionSubmissionMedia::whereIn('submission_id', $chunk->pluck('id'))->get();
                foreach ($chunk as $submission) {
                    $fn($submission, $media);
                    $count++;
                }
            }, 'data_collection_submissions.id', 'id');

        return $count;
    }

    // ── Formats ────────────────────────────────────────────────────────────

    private function csv(string $path): int
    {
        $columns = $this->columns(null);
        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads names correctly
        fputcsv($handle, array_values($columns));

        $count = $this->each(function (DataCollectionSubmission $s, Collection $media) use ($handle, $columns) {
            fputcsv($handle, $this->row($columns, $s->data ?? [], '', $this->meta($s), $media, $s->id));
        });

        fclose($handle);

        return $count;
    }

    private function xlsx(string $path): int
    {
        $book = new Spreadsheet();
        $main = $book->getActiveSheet()->setTitle(self::sheetTitle($this->form->name ?: 'Submissions'));
        $columns = $this->columns(null);
        $main->fromArray(array_values($columns), null, 'A1');
        $mainRow = 2;

        $sheets = [];
        $titles = [mb_strtolower($main->getTitle())];
        foreach ($this->catalog->repeats as $name => $info) {
            // Sheet names must be unique (and ≤31 chars): fall back to the
            // repeat's name, then number it.
            $title = self::sheetTitle($this->labels ? $info['label'] : $name);
            if (in_array(mb_strtolower($title), $titles, true)) {
                $title = self::sheetTitle($name);
            }
            for ($n = 2; in_array(mb_strtolower($title), $titles, true); $n++) {
                $title = self::sheetTitle(mb_substr($name, 0, 27).' '.$n);
            }
            $titles[] = mb_strtolower($title);
            $sheet = $book->createSheet()->setTitle($title);
            $repeatColumns = $this->columns($name);
            $sheet->fromArray(array_values($repeatColumns), null, 'A1');
            $sheets[$name] = ['sheet' => $sheet, 'columns' => $repeatColumns, 'row' => 2];
        }

        $count = $this->each(function (DataCollectionSubmission $s, Collection $media) use ($main, $columns, &$mainRow, &$sheets) {
            $data = $s->data ?? [];
            $main->fromArray($this->row($columns, $data, '', $this->meta($s), $media, $s->id), null, 'A'.$mainRow++, true);

            foreach ($sheets as $name => &$entry) {
                foreach ($this->instances($name, $data) as $i => $instance) {
                    $meta = ['_parent_id' => $s->id, '_index' => $i + 1];
                    $entry['sheet']->fromArray($this->row($entry['columns'], $instance, '', $meta, $media, $s->id), null, 'A'.$entry['row']++, true);
                }
            }
        });

        foreach ($book->getAllSheets() as $sheet) {
            $sheet->getStyle('1:1')->getFont()->setBold(true);
            $sheet->freezePane('A2');
        }
        $book->setActiveSheetIndex(0);
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $count;
    }

    // Instances of a (possibly nested) repeat within one submission.
    private function instances(string $repeat, array $data): array
    {
        $parent = $this->catalog->repeats[$repeat]['parent'] ?? null;
        if ($parent === null) {
            return is_array($data[$repeat] ?? null) ? array_values(array_filter($data[$repeat], 'is_array')) : [];
        }

        $out = [];
        foreach ($this->instances($parent, $data) as $outer) {
            if (is_array($outer[$repeat] ?? null)) {
                array_push($out, ...array_values(array_filter($outer[$repeat], 'is_array')));
            }
        }

        return $out;
    }

    private function geojson(string $path): int
    {
        $geo = collect($this->catalog->topLevel())->first(fn ($f) => $f['type'] === 'geopoint');
        $columns = array_filter($this->columns(null), fn ($k) => ! str_contains($k, '#') && ! str_starts_with($k, '_lat') && ! str_starts_with($k, '_lng'), ARRAY_FILTER_USE_KEY);

        $handle = fopen($path, 'w');
        fwrite($handle, '{"type":"FeatureCollection","features":[');
        $first = true;

        $count = $this->each(function (DataCollectionSubmission $s, Collection $media) use ($handle, $columns, $geo, &$first) {
            $point = $geo && ! in_array($geo['path'], $this->hidden, true) ? (($s->data ?? [])[$geo['name']] ?? null) : null;
            [$lat, $lng] = is_array($point) && isset($point['lat'], $point['lng'])
                ? [(float) $point['lat'], (float) $point['lng']]
                : [$s->lat, $s->lng];
            if ($lat === null || $lng === null) {
                return;
            }

            $values = $this->row($columns, $s->data ?? [], '', $this->meta($s), $media, $s->id);
            $feature = [
                'type'       => 'Feature',
                'geometry'   => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
                'properties' => array_combine(array_values($columns), $values),
            ];
            fwrite($handle, ($first ? '' : ',').json_encode($feature, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $first = false;
        });

        fwrite($handle, ']}');
        fclose($handle);

        return $count;
    }

    private function pdf(string $path): int
    {
        $analytics = SubmissionAnalytics::make($this->form, $this->filters, $this->hidden === []);
        $summary = $analytics->summary();

        Pdf::loadView('exports.data-collection-summary-pdf', [
            'form'    => $this->form,
            'summary' => $summary,
            'filters' => $this->filters,
        ])->setPaper('a4')->save($path);

        return $summary['total'];
    }

    private static function sheetTitle(string $title): string
    {
        return mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $title), 0, 31) ?: 'Sheet';
    }
}
