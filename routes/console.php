<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync every second for real-time call data
Schedule::command('yeastar:sync-calls --minutes=1')->everySecond();

// Full hourly backfill to catch any missed calls
Schedule::command('yeastar:sync-calls --hours=2')->hourly();

// Deep daily backfill — safety net in case schedule:run stops firing
// (e.g. cron/Task Scheduler outage) for an extended period.
Schedule::command('yeastar:sync-calls --hours=100')->daily();

// Follow-up reminders in the agent's bell: first round at 07:00, then hourly
// to 17:00 so follow-ups set for "today" later in the day are still caught.
// Each ticket is only reminded once per follow-up date.
Schedule::command('tickets:follow-up-reminders')->hourlyAt(0)->between('7:00', '17:00')->withoutOverlapping();

// API tokens expire after 30 days (config/sanctum.php); clear out the dead ones.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
