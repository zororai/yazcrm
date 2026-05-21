<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync the last 2 minutes of calls every minute for near-real-time data
Schedule::command('yeastar:sync-calls --minutes=2')->everyMinute();

// Full hourly backfill to catch any calls the 1-minute window may have missed
Schedule::command('yeastar:sync-calls --hours=2')->hourly();
