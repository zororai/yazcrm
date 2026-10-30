<?php

namespace App\Services\DataCollection;

use App\Models\DataCollectionSubmission;
use App\Models\DataCollectionSubmissionMedia;
use App\Support\DataCollection\SchemaWalker;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Stores a submission's photo. The file type is detected from its contents
// (never the client's extension or MIME header), the stored name is a fresh
// UUID, and the client's filename is kept only as the idempotency key.
class SubmissionMediaService
{
    // Detected MIME type => stored extension.
    public const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'image/gif'  => 'gif',
    ];

    // Returns [status stored|duplicate, media record, error message or null].
    public function store(DataCollectionSubmission $submission, string $field, string $filename, UploadedFile $file): array
    {
        $filename = mb_substr(basename(str_replace('\\', '/', $filename)), 0, 255);

        $node = SchemaWalker::index($submission->formVersion?->schema ?? [])[$field] ?? null;
        if (! $node || ($node['type'] ?? null) !== 'photo') {
            return [null, null, "'{$field}' is not a photo question on this form."];
        }

        if ($existing = $this->existing($submission, $field, $filename)) {
            return ['duplicate', $existing, null];
        }

        $mime = $file->getMimeType(); // finfo on the uploaded bytes
        $extension = self::IMAGE_TYPES[$mime] ?? null;
        if (! $extension) {
            return [null, null, 'Only photos (JPEG, PNG, WebP, HEIC or GIF) can be uploaded for a photo question.'];
        }

        $disk = config('data_collection.media_disk');
        $directory = "data-collection/{$submission->form_id}/{$submission->id}";
        $path = Storage::disk($disk)->putFileAs($directory, $file, Str::uuid().'.'.$extension);

        try {
            $media = DataCollectionSubmissionMedia::create([
                'submission_id' => $submission->id,
                'field_name'    => $field,
                'disk'          => $disk,
                'path'          => $path,
                'original_name' => $filename,
                'mime'          => $mime,
                'size'          => $file->getSize(),
                'checksum'      => hash_file('sha256', $file->getRealPath()),
            ]);
        } catch (UniqueConstraintViolationException) {
            // A parallel upload of the same file won: keep theirs.
            Storage::disk($disk)->delete($path);

            return ['duplicate', $this->existing($submission, $field, $filename), null];
        }

        return ['stored', $media, null];
    }

    private function existing(DataCollectionSubmission $submission, string $field, string $filename): ?DataCollectionSubmissionMedia
    {
        return DataCollectionSubmissionMedia::where('submission_id', $submission->id)
            ->where('field_name', $field)
            ->where('original_name', $filename)
            ->first();
    }
}
