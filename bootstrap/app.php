<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\EnsurePasswordIsChanged::class,
            \App\Http\Middleware\RecordAuditTrail::class,
        ]);

        $middleware->statefulApi();

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'nav'   => \App\Http\Middleware\EnsureNavPermission::class,
            'data.api' => \App\Http\Middleware\AuthenticateDataApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The beneficiary app tells an expired login from a missing one so it
        // can ask the user to sign in again without dropping queued data.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if (! $request->is('api/'.config('data_collection.api_prefix').'/*')) {
                return null;
            }

            $expired = \App\Support\DataCollection\TokenState::isExpired($request);

            return response()->json([
                'code'    => $expired ? 'token_expired' : 'unauthenticated',
                'message' => $expired ? 'Your login has expired. Please sign in again.' : 'Unauthenticated.',
            ], 401);
        });
    })->create();
