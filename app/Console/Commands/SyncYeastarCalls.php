<?php

namespace App\Console\Commands;

use App\Services\YeastarService;
use Illuminate\Console\Command;

class SyncYeastarCalls extends Command
{
    protected $signature   = 'yeastar:sync-calls {--hours=1 : How many hours back to sync}';
    protected $description = 'Sync recent calls from Yeastar PBX database';

    public function handle(YeastarService $yeastar): int
    {
        $hours = (int) $this->option('hours');
        $start = now()->subHours($hours)->format('Y-m-d H:i:s');
        $end   = now()->format('Y-m-d H:i:s');

        $this->info("Syncing calls from {$start} to {$end}...");

        $count = $yeastar->syncCalls($start, $end);

        $this->info("Done. Synced {$count} calls.");

        return self::SUCCESS;
    }
}
