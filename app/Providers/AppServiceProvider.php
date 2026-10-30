<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Beneficiary app data collection: per device (each device logs in
        // with its own token) and per user across all their devices.
        RateLimiter::for('data-collection', function (Request $request) {
            $perMinute = config('data_collection.rate_limit_per_minute');
            $user = $request->user();
            $token = $user?->currentAccessToken();
            $device = $token instanceof PersonalAccessToken ? $token->id : $request->ip();

            return [
                Limit::perMinute($perMinute)->by('dc-device:'.$device),
                Limit::perMinute($perMinute * 3)->by('dc-user:'.($user?->id ?? $request->ip())),
            ];
        });
    }
}
