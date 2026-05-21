<?php

namespace App\Console\Commands;

use App\Services\YeastarService;
use Illuminate\Console\Command;

class SyncYeastarCalls extends Command
{
    protected $signature   = 'yeastar:sync-calls {--hours= : Hours back to sync} {--minutes= : Minutes back to sync (overrides hours)}';
    protected $description = 'Sync recent calls from Yeastar PBX database';

    public function handle(YeastarService $yeastar): int
    {
        if ($this->option('minutes')) {
            $start = now()->subMinutes((int) $this->option('minutes'))->format('Y-m-d H:i:s');
        } else {
            $hours = (int) ($this->option('hours') ?? 1);
            $start = now()->subHours($hours)->format('Y-m-d H:i:s');
        }

        $end = now()->format('Y-m-d H:i:s');

        $this->info("Syncing calls from {$start} to {$end}...");

        $count = $yeastar->syncCalls($start, $end);

        $this->info("Done. Synced {$count} calls.");

        return self::SUCCESS;
    }
}
