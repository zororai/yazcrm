<?php

namespace App\Jobs\DataCollection;

use App\Models\DataCollectionMappingLog;
use App\Models\DataCollectionSubmission;
use App\Services\DataCollection\CrmMappingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// "Re-run mapping for all" after a mapping change, or "retry failed".
class RemapFormSubmissions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public readonly int $formId, public readonly bool $failedOnly = false)
    {
    }

    public function handle(CrmMappingService $mapper): void
    {
        DataCollectionSubmission::with('form.mapping')
            ->where('form_id', $this->formId)
            ->when($this->failedOnly, fn ($q) => $q->whereIn('id', DataCollectionMappingLog::where('form_id', $this->formId)
                ->where('result', 'failed')->select('submission_id')))
            ->chunkById(200, function ($chunk) use ($mapper) {
                foreach ($chunk as $submission) {
                    $mapper->map($submission);
                }
            });
    }
}
