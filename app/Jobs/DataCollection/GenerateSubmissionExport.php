<?php

namespace App\Jobs\DataCollection;

use App\Models\DataCollectionExport;
use App\Notifications\DataCollectionExportReady;
use App\Services\DataCollection\SubmissionExporter;
use App\Support\DataCollection\FormAccess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

// Builds one requested export in the background and tells the requester in
// the bell when it's ready to download.
class GenerateSubmissionExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $exportId)
    {
    }

    public function handle(): void
    {
        $export = DataCollectionExport::with(['form', 'user'])->find($this->exportId);
        if (! $export || ! $export->form || ! $export->user) {
            return;
        }

        $export->update(['status' => 'running']);

        $extension = $export->format === 'geojson' ? 'geojson' : $export->format;
        $tmp = tempnam(sys_get_temp_dir(), 'dcexport');
        $options = $export->options ?? [];

        try {
            $count = (new SubmissionExporter(
                $export->form,
                $options['filters'] ?? [],
                ($options['labels'] ?? true) === true,
                FormAccess::canSeePii($export->user),
            ))->write($export->format, $tmp);

            $disk = config('data_collection.export_disk');
            $path = "data-collection/exports/{$export->form_id}/".Str::uuid().'.'.$extension;
            $stream = fopen($tmp, 'r');
            Storage::disk($disk)->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $export->update(['status' => 'ready', 'disk' => $disk, 'path' => $path, 'row_count' => $count, 'error' => null]);
            $export->user->notify(new DataCollectionExportReady($export));
        } catch (Throwable $e) {
            $export->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            report($e);
        } finally {
            @unlink($tmp);
        }
    }
}
