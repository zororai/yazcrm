<?php

namespace App\Services\DataCollection;

use App\Jobs\DataCollection\ProcessSubmission;
use App\Models\DataCollectionForm;
use App\Models\DataCollectionFormVersion;
use App\Models\DataCollectionRejection;
use App\Models\DataCollectionSubmission;
use App\Models\DataCollectionSubmissionAudit;
use App\Models\User;
use App\Support\DataCollection\FormAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

// Takes one item of POST /submissions/batch and returns its result:
//   {client_uuid, status: accepted|duplicate|rejected, server_id, reason, errors}
// Idempotent on client_uuid: a resend, or two resends racing each other,
// gets "duplicate" and the original server_id; never a second row.
// Data problems are always "rejected" for that item, never an exception.
class SubmissionIngestService
{
    public function __construct(private readonly SubmissionValidator $validator)
    {
    }

    public function ingest(mixed $item, User $user): array
    {
        $uuid = is_array($item) && is_string($item['client_uuid'] ?? null) ? $item['client_uuid'] : null;

        if (! is_array($item) || ($item !== [] && array_is_list($item))) {
            return $this->reject($user, $item, $uuid, 'Each submission must be a JSON object.');
        }

        $envelope = Validator::make($item, [
            'client_uuid'         => 'required|uuid',
            'form_id'             => 'required|integer',
            'form_version'        => ['required', 'regex:/^\d+$/'],
            'collected_at'        => 'required|date',
            'started_at'          => 'nullable|date',
            'device_id'           => 'nullable|string|max:255',
            'app_version'         => 'nullable|string|max:50',
            'lat'                 => 'nullable|numeric|between:-90,90',
            'lng'                 => 'nullable|numeric|between:-180,180',
            'data'                => 'present|array',
            'media'               => 'nullable|array',
            'media.*'             => 'array',
            'media.*.field_name'  => 'required_without:media.*.field|nullable|string|max:255',
            'media.*.field'       => 'required_without:media.*.field_name|nullable|string|max:255',
            'media.*.filename'    => 'required|string|max:255',
        ]);
        if ($envelope->fails()) {
            $errors = [];
            foreach ($envelope->errors()->messages() as $field => $messages) {
                $errors[] = ['field' => $field, 'message' => $messages[0]];
            }

            return $this->reject($user, $item, $uuid, 'The submission is missing required details.', $errors);
        }

        if ($existing = $this->existing($uuid)) {
            return $this->result($uuid, 'duplicate', $existing->id);
        }

        $form = DataCollectionForm::find($item['form_id']);
        if (! $form) {
            return $this->reject($user, $item, $uuid, 'This form does not exist on the server.');
        }

        $version = DataCollectionFormVersion::where('form_id', $form->id)
            ->where('version_number', (int) $item['form_version'])
            ->first();
        if (! $version || ! $version->acceptsSubmissions()) {
            return $this->reject($user, $item, $uuid, "Version {$item['form_version']} of this form does not exist on the server.");
        }

        if (! FormAccess::can($user, $form, 'collect')) {
            return $this->reject($user, $item, $uuid, 'You are not assigned to collect data for this form.');
        }

        $data = $item['data'];
        if ($data !== [] && array_is_list($data)) {
            return $this->reject($user, $item, $uuid, 'data must be an object of answers keyed by field name.');
        }
        $media = $this->mediaNames($item['media'] ?? []);
        $check = $this->validator->validate($version->schema ?? [], $data, $media);
        if ($check['errors']) {
            return $this->reject($user, $item, $uuid, 'Some answers do not match the form.', $check['errors']);
        }

        try {
            $submission = DB::transaction(function () use ($item, $uuid, $form, $version, $user, $data, $check) {
                $submission = DataCollectionSubmission::create([
                    'client_uuid'     => $uuid,
                    'form_id'         => $form->id,
                    'form_version_id' => $version->id,
                    'submitted_by'    => $user->id,
                    'data'            => $data,
                    'started_at'      => $this->time($item['started_at'] ?? null),
                    'collected_at'    => $this->time($item['collected_at']),
                    'received_at'     => now(),
                    'device_id'       => $item['device_id'] ?? null,
                    'app_version'     => $item['app_version'] ?? null,
                    'lat'             => $item['lat'] ?? null,
                    'lng'             => $item['lng'] ?? null,
                    'completion'      => $check['completion'],
                    'status'          => 'pending',
                ]);

                DataCollectionSubmissionAudit::create([
                    'submission_id' => $submission->id,
                    'user_id'       => $user->id,
                    'action'        => 'received',
                    'new'           => ['status' => 'pending', 'form_version' => $version->version_number],
                ]);

                return $submission;
            });
        } catch (UniqueConstraintViolationException) {
            // Another request with the same client_uuid won the race.
            return $this->result($uuid, 'duplicate', $this->existing($uuid)?->id);
        }

        ProcessSubmission::dispatch($submission->id);

        return $this->result($uuid, 'accepted', $submission->id);
    }

    private function existing(string $uuid): ?DataCollectionSubmission
    {
        return DataCollectionSubmission::withTrashed()->where('client_uuid', $uuid)->first(['id']);
    }

    // field name => [filenames], from media[] entries (field_name or field).
    private function mediaNames(array $media): array
    {
        $out = [];
        foreach ($media as $entry) {
            $field = $entry['field_name'] ?? $entry['field'] ?? null;
            if ($field !== null && isset($entry['filename'])) {
                $out[$field][] = $entry['filename'];
            }
        }

        return $out;
    }

    // Stored in the app's timezone, whatever offset the device sent.
    private function time(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value)->setTimezone(config('app.timezone'));
    }

    private function reject(User $user, mixed $item, ?string $uuid, string $reason, array $errors = []): array
    {
        // Only the reason and field errors are kept, never the answers.
        try {
            DataCollectionRejection::create([
                'client_uuid' => $uuid !== null ? mb_substr($uuid, 0, 64) : null,
                'form_id'     => is_array($item) && is_int($item['form_id'] ?? null) ? $item['form_id'] : null,
                'user_id'     => $user->id,
                'device_id'   => is_array($item) && is_string($item['device_id'] ?? null) ? mb_substr($item['device_id'], 0, 255) : null,
                'reason'      => $reason,
                'errors'      => $errors ?: null,
            ]);
        } catch (Throwable $e) {
            Log::warning('Data collection: could not record a rejected submission', ['error' => $e->getMessage()]);
        }

        return $this->result($uuid, 'rejected', null, $reason, $errors);
    }

    private function result(?string $uuid, string $status, ?int $serverId, ?string $reason = null, array $errors = []): array
    {
        return [
            'client_uuid' => $uuid,
            'status'      => $status,
            'server_id'   => $serverId,
            'reason'      => $reason,
            'errors'      => $errors,
        ];
    }
}
