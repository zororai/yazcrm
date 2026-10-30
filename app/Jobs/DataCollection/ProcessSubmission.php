<?php

namespace App\Jobs\DataCollection;

use App\Models\DataCollectionSubmission;
use App\Services\DataCollection\CrmMappingService;
use App\Services\DataCollection\SubmissionProjector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// After a submission is accepted or edited: rebuild its analytics rows, then
// run the form's CRM mapping. A mapping failure is logged on the mapping log
// and never touches the stored submission.
class ProcessSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $submissionId, public readonly bool $map = true)
    {
        $this->afterCommit();
    }

    public function handle(SubmissionProjector $projector, CrmMappingService $mapper): void
    {
        $submission = DataCollectionSubmission::with('formVersion')->find($this->submissionId);
        if (! $submission) {
            return; // soft-deleted meanwhile
        }

        $projector->project($submission);

        if ($this->map) {
            $mapper->map($submission);
        }

        $submission->forceFill(['processed_at' => now()])->saveQuietly();
    }
}
