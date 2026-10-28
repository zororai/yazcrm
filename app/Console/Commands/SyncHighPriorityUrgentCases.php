<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\UrgentCase;
use Illuminate\Console\Command;

class SyncHighPriorityUrgentCases extends Command
{
    protected $signature   = 'tickets:sync-urgent-cases {--dry-run : Count what would be added without writing anything}';
    protected $description = 'Put existing High / Urgent tickets on the Urgent Cases board (resolved/closed tickets go on as resolved)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Ticket::whereIn('priority', ['high', 'urgent'])
            ->whereNotIn('id', UrgentCase::whereNotNull('source_ticket_id')->select('source_ticket_id'))
            ->whereNotIn('id', UrgentCase::whereNotNull('created_ticket_id')->select('created_ticket_id'));

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Nothing to add — every High / Urgent ticket is already on the Urgent Cases board.');

            return self::SUCCESS;
        }

        $open = (clone $query)->whereNotIn('status', ['resolved', 'closed'])->count();
        $this->info(($dryRun ? '[dry-run] ' : '')."{$total} High / Urgent ticket(s) to add: {$open} open, ".($total - $open).' resolved.');
        if ($dryRun) return self::SUCCESS;

        $query->orderBy('id')->chunkById(200, fn ($tickets) => $tickets->each->openUrgentCase());

        $this->info('Done.');

        return self::SUCCESS;
    }
}
