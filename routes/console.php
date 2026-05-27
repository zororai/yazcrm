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
