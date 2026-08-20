<?php

namespace App\Console\Commands;

use App\Models\Appraisal;
use App\Models\User;
use App\Services\AppraisalTaskGenerator;
use Illuminate\Console\Command;

class BackfillAppraisalDevelopmentTasks extends Command
{
    protected $signature   = 'appraisals:backfill-development-tasks {--dry-run : List what would be generated without writing anything}';
    protected $description = 'Generate development tasks for already-completed appraisals that predate the Appraisal-to-Task integration';

    public function handle(AppraisalTaskGenerator $generator): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $appraisals = Appraisal::where('status', 'completed')
            ->whereDoesntHave('tasks')
            ->with('user', 'supervisor')
            ->get();

        if ($appraisals->isEmpty()) {
            $this->info('Nothing to backfill — every completed appraisal already has generated tasks (or none to generate).');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Found {$appraisals->count()} completed appraisal(s) without generated tasks.");

        foreach ($appraisals as $appraisal) {
            // The supervisor who completed the review; fall back to the assigned
            // supervisor, then any admin, since the actual actor wasn't recorded
            // for appraisals completed before the activity log existed.
            $actor = $appraisal->supervisor ?? User::where('role', 'admin')->first();

            if (! $actor) {
                $this->warn("Skipping appraisal #{$appraisal->id} ({$appraisal->user?->name}) — no supervisor or admin available to attribute the tasks to.");
                continue;
            }

            if ($dryRun) {
                $fields = array_filter($appraisal->supervisor_responses ?? [], fn ($v, $k) => in_array($k, [
                    'training_needs', 'mentorship', 'action_plan', 'recommendations_improvement',
                ], true) && trim((string) $v) !== '', ARRAY_FILTER_USE_BOTH);

                $this->line("  #{$appraisal->id} {$appraisal->user?->name}: would generate ".count($fields).' task(s)');

                continue;
            }

            $generator->generate($appraisal, $actor);

            $count = $appraisal->tasks()->count();
            $this->line("  #{$appraisal->id} {$appraisal->user?->name}: generated {$count} task(s)");
        }

        $this->info($dryRun ? 'Dry run complete — no tasks were created.' : 'Backfill complete.');

        return self::SUCCESS;
    }
}
