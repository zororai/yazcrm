<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1,localhost:5173',
        Illuminate\Support\Str::contains(env('APP_URL', ''), 'http://') ? ','.parse_url(env('APP_URL', ''), PHP_URL_HOST) : '',
        env('SANCTUM_STATEFUL_DOMAINS') ? '' : ''
    ))),

    'guard' => ['web'],

    // API tokens (beneficiary app, Data API) expire after 30 days; the app's
    // data collection endpoints then answer 401 {"code":"token_expired"}.
    'expiration' => (int) env('SANCTUM_EXPIRATION_MINUTES', 60 * 24 * 30) ?: null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies'      => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token'  => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
