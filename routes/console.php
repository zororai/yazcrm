<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync latest calls from Yeastar every 5 minutes
Schedule::command('yeastar:sync-calls --hours=1')->everyFiveMinutes();
