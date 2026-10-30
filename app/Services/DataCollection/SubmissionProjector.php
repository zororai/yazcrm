<?php

namespace App\Services\DataCollection;

use App\Models\DataCollectionSubmission;
use App\Models\DataCollectionSubmissionValue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

// Rebuilds a submission's rows in data_collection_submission_values (one
// per answer; one per chosen option of a select_multiple; repeat answers
// keyed "repeat/child" with their 1-based instance number). Analytics query
// these rows with plain indexed SQL on MySQL and SQLite alike.
class SubmissionProjector
{
    public function project(DataCollectionSubmission $submission): void
    {
        $schema = $submission->formVersion?->schema ?? [];
        $rows = [];
        $this->collect($schema['children'] ?? [], $submission->data ?? [], '', null, $rows);

        $base = ['submission_id' => $submission->id, 'form_id' => $submission->form_id];
        $rows = array_map(fn ($row) => $base + $row, $rows);

        DB::transaction(function () use ($submission, $rows) {
            DataCollectionSubmissionValue::where('submission_id', $submission->id)->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DataCollectionSubmissionValue::insert($chunk);
            }
        });
    }

    private function collect(array $nodes, array $scope, string $prefix, ?int $instance, array &$rows): void
    {
        foreach ($nodes as $node) {
            $type = $node['type'] ?? null;
            $name = $node['name'] ?? null;
            if (! $name) {
                continue;
            }

            if ($type === 'group') {
                $this->collect($node['children'] ?? [], $scope, $prefix, $instance, $rows);
                continue;
            }

            $value = $scope[$name] ?? null;

            if ($type === 'repeat') {
                if (is_array($value) && array_is_list($value)) {
                    foreach ($value as $i => $entry) {
                        if (is_array($entry)) {
                            $this->collect($node['children'] ?? [], $entry, $prefix.$name.'/', $i + 1, $rows);
                        }
                    }
                }
                continue;
            }

            if ($type === 'note' || $value === null || $value === '' || $value === []) {
                continue;
            }

            $field = $prefix.$name;
            if ($type === 'select_multiple' && is_array($value)) {
                foreach ($value as $choice) {
                    if (is_scalar($choice)) {
                        $rows[] = $this->row($field, $instance, (string) $choice);
                    }
                }
                continue;
            }

            if ($type === 'geopoint' && is_array($value)) {
                $rows[] = $this->row($field, $instance, trim(($value['lat'] ?? '').' '.($value['lng'] ?? '')));
                continue;
            }

            if (is_array($value)) {
                continue;
            }

            $rows[] = $this->row($field, $instance, (string) $value, $type);
        }
    }

    private function row(string $field, ?int $instance, string $value, ?string $type = null): array
    {
        $num = null;
        $date = null;

        if (in_array($type, ['integer', 'decimal', 'calculate'], true) && is_numeric($value) && abs((float) $value) < 1e14) {
            $num = (float) $value;
        }
        if (in_array($type, ['date', 'datetime'], true)) {
            try {
                $date = CarbonImmutable::parse($value)->toDateString();
            } catch (Throwable) {
                $date = null;
            }
        }

        return [
            'field'      => mb_substr($field, 0, 191),
            'instance'   => $instance,
            'value'      => mb_substr($value, 0, 191),
            'value_num'  => $num,
            'value_date' => $date,
        ];
    }
}
