<?php

namespace App\Services\DataCollection;

use App\Models\DataCollectionMappingLog;
use App\Models\DataCollectionSubmission;
use App\Models\DataCollectionSubmissionAudit;
use App\Support\DataCollection\CrmTargets;
use App\Support\DataCollection\SchemaWalker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

// Copies a submission's answers onto a CRM record per the form's mapping:
//   create     - new record; if one already matches the key field it's a
//                duplicate and that record is linked instead
//   update     - update the record matching the key field; skipped if none
//   store_only - nothing
// Re-running (after a mapping change, or a retry) updates the record the
// submission is already linked to rather than creating another. Failures
// are written to the mapping log and never touch the submission itself.
class CrmMappingService
{
    public function map(DataCollectionSubmission $submission): ?DataCollectionMappingLog
    {
        $mapping = $submission->form?->mapping;
        if (! $mapping || $mapping->mode === 'store_only') {
            return null;
        }

        $target = CrmTargets::get($mapping->target);

        try {
            [$result, $record] = DB::transaction(fn () => $this->apply($submission, $mapping, $target));
            $error = null;
        } catch (Throwable $e) {
            [$result, $record, $error] = ['failed', null, mb_substr($e->getMessage(), 0, 1000)];
        }

        $log = DataCollectionMappingLog::firstOrNew(['submission_id' => $submission->id]);
        $log->fill([
            'form_id'         => $submission->form_id,
            'result'          => $result,
            'crm_record_type' => $record ? $mapping->target : $log->crm_record_type,
            'crm_record_id'   => $record?->getKey() ?? $log->crm_record_id,
            'error'           => $error,
            'attempts'        => $log->exists ? $log->attempts + 1 : 1,
        ])->save();

        return $log;
    }

    private function apply(DataCollectionSubmission $submission, $mapping, ?array $target): array
    {
        if (! $target) {
            throw new RuntimeException("Unknown CRM target '{$mapping->target}'.");
        }

        $values = $this->values($submission, $mapping->field_map ?? [], $target);
        /** @var class-string<Model> $model */
        $model = $target['model'];

        // Already linked by an earlier run: keep that record up to date.
        $linked = $submission->crm_record_type === $mapping->target && $submission->crm_record_id
            ? $model::find($submission->crm_record_id)
            : null;

        $matched = null;
        if (! $linked && $mapping->match_crm_field && $mapping->match_form_field) {
            $key = $this->answer($submission, $mapping->match_form_field);
            if ($key !== null && $key !== '' && ! is_array($key)) {
                $matched = $model::where($mapping->match_crm_field, (string) $key)->first();
            }
        }

        if ($mapping->mode === 'update') {
            $record = $linked ?? $matched;
            if (! $record) {
                return ['skipped', null];
            }
            $record->fill($values)->save();
            $result = 'updated';
        } elseif ($linked) {
            $linked->fill($values)->save();
            [$result, $record] = ['updated', $linked];
        } elseif ($matched) {
            [$result, $record] = ['duplicate', $matched];
        } else {
            $missing = array_filter($target['required'], fn ($f) => ($values[$f] ?? null) === null || $values[$f] === '');
            if ($missing) {
                throw new RuntimeException('No value for required CRM field(s): '.implode(', ', $missing).'.');
            }
            $record = new $model();
            $record->fill($values + $this->defaults($mapping->target, $submission))->save();
            $result = 'created';
        }

        $submission->forceFill(['crm_record_type' => $mapping->target, 'crm_record_id' => $record->getKey()])->saveQuietly();

        DataCollectionSubmissionAudit::create([
            'submission_id' => $submission->id,
            'user_id'       => null,
            'action'        => 'mapped',
            'new'           => ['result' => $result, 'crm_record_type' => $mapping->target, 'crm_record_id' => $record->getKey()],
        ]);

        return [$result, $record];
    }

    // crm field => converted answer, for every mapped field that has one.
    private function values(DataCollectionSubmission $submission, array $fieldMap, array $target): array
    {
        $out = [];
        foreach ($fieldMap as $crmField => $formField) {
            if (! $formField || ! isset($target['fields'][$crmField])) {
                continue;
            }
            $answer = $this->answer($submission, $formField);
            if ($answer === null || $answer === '' || $answer === []) {
                continue;
            }
            $out[$crmField] = $this->convert($answer, $target['fields'][$crmField][1]);
        }

        return array_filter($out, fn ($v) => $v !== null);
    }

    // Top-level answers only (groups are already flat in the data).
    private function answer(DataCollectionSubmission $submission, string $field): mixed
    {
        return ($submission->data ?? [])[$field] ?? null;
    }

    private function convert(mixed $value, string $type): mixed
    {
        if (is_array($value) && array_key_exists('lat', $value)) {
            $value = trim(($value['lat'] ?? '').' '.($value['lng'] ?? ''));
        }

        return match ($type) {
            'array'   => is_array($value) ? array_values($value) : preg_split('/\s+/', trim((string) $value)),
            'int'     => is_numeric($value) ? (int) $value : null,
            'decimal' => is_numeric($value) ? (float) $value : null,
            'bool'    => is_bool($value) ? $value : in_array(strtolower((string) $value), ['yes', 'true', '1', 'y'], true),
            'date'    => $this->date($value)?->toDateString(),
            'datetime' => $this->date($value),
            'string'  => mb_substr(is_array($value) ? implode(', ', $value) : (string) $value, 0, 255),
            default   => is_array($value) ? implode(', ', $value) : (string) $value,
        };
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        try {
            return is_string($value) ? CarbonImmutable::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    // Columns the CRM record needs that don't come from the form.
    private function defaults(string $target, DataCollectionSubmission $submission): array
    {
        return match ($target) {
            'beneficiary' => [
                'client_uuid'        => $submission->client_uuid,
                'created_by_user_id' => $submission->submitted_by,
                'captured_at'        => $submission->collected_at,
            ],
            'activity' => [
                'client_uuid'        => $submission->client_uuid,
                'created_by_user_id' => $submission->submitted_by,
            ],
            'case' => [
                'agent_id' => $submission->submitted_by,
                'status'   => 'open',
            ],
            default => [],
        };
    }
}
