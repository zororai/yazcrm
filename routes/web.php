<?php

use App\Http\Controllers\Web;
use Illuminate\Support\Facades\Route;

// ─── Public (no auth) ────────────────────────────────────────────────────────
Route::get('screen',      [Web\PublicDashboardController::class, 'index'])->name('public.dashboard');
Route::get('screen/data', [Web\PublicDashboardController::class, 'data'])->name('public.dashboard.data');

// ─── Guest ───────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login', [Web\AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [Web\AuthController::class, 'login']);
});

// ─── Authenticated ────────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('logout', [Web\AuthController::class, 'logout'])->name('logout');
    Route::redirect('/', '/dashboard');

    Route::get('dashboard', [Web\DashboardController::class, 'index'])->name('dashboard');

    // Calls
    Route::get('calls', [Web\CallController::class, 'index'])->name('calls.index');
    Route::get('calls/number-search', [Web\TicketController::class, 'searchNumbers'])->name('calls.number-search');
    Route::get('calls/{call}', [Web\CallController::class, 'show'])->name('calls.show');
    Route::post('calls/{call}/link-client', [Web\CallController::class, 'linkClient'])->name('calls.link-client');

    // Clients
    Route::get('clients', [Web\ClientController::class, 'index'])->name('clients.index');
    Route::get('clients/create', [Web\ClientController::class, 'create'])->name('clients.create');
    Route::post('clients', [Web\ClientController::class, 'store'])->name('clients.store');
    Route::get('clients/{client}', [Web\ClientController::class, 'show'])->name('clients.show');
    Route::get('clients/{client}/edit', [Web\ClientController::class, 'edit'])->name('clients.edit');
    Route::put('clients/{client}', [Web\ClientController::class, 'update'])->name('clients.update');
    Route::delete('clients/{client}', [Web\ClientController::class, 'destroy'])->name('clients.destroy');

    // Callbacks
    Route::get('callbacks', [Web\CallbackController::class, 'index'])->name('callbacks.index');
    Route::post('callbacks', [Web\CallbackController::class, 'store'])->name('callbacks.store');
    Route::post('callbacks/{callbackQueue}/assign', [Web\CallbackController::class, 'assign'])->name('callbacks.assign');
    Route::post('callbacks/{callbackQueue}/complete', [Web\CallbackController::class, 'complete'])->name('callbacks.complete');
    Route::delete('callbacks/{callbackQueue}', [Web\CallbackController::class, 'destroy'])->name('callbacks.destroy');

    // Urgent Cases (visible to all agents)
    Route::get('urgent-cases', [Web\UrgentCaseController::class, 'index'])->name('urgent-cases.index');
    Route::post('urgent-cases', [Web\UrgentCaseController::class, 'store'])->name('urgent-cases.store');
    Route::patch('urgent-cases/{urgentCase}/resolve', [Web\UrgentCaseController::class, 'resolve'])->name('urgent-cases.resolve');
    Route::post('urgent-cases/{urgentCase}/ticket', [Web\UrgentCaseController::class, 'createTicket'])->name('urgent-cases.ticket');

    // Tickets
    Route::get('tickets', [Web\TicketController::class, 'index'])->name('tickets.index');
    Route::post('tickets', [Web\TicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/import', [Web\TicketImportController::class, 'create'])->name('tickets.import')->middleware('admin');
    Route::post('tickets/import', [Web\TicketImportController::class, 'store'])->name('tickets.import.store')->middleware('admin');
    Route::get('tickets/{ticket}', [Web\TicketController::class, 'show'])->name('tickets.show');
    Route::put('tickets/{ticket}', [Web\TicketController::class, 'update'])->name('tickets.update');
    Route::delete('tickets/{ticket}', [Web\TicketController::class, 'destroy'])->name('tickets.destroy');

    // ─── Admin only ───────────────────────────────────────────────────────────
    Route::middleware('admin')->group(function () {
        Route::get('analytics', [Web\AnalyticsController::class, 'index'])->name('analytics.index');

        // Distress domains / lookup settings
        Route::get('distress-domains', [Web\DistressDomainController::class, 'index'])->name('distress-domains.index');
        Route::get('distress-domains/section/{type}', [Web\DistressDomainController::class, 'section'])->name('distress-domains.section');
        Route::post('distress-domains', [Web\DistressDomainController::class, 'store'])->name('distress-domains.store');
        Route::put('distress-domains/{distressDomain}', [Web\DistressDomainController::class, 'update'])->name('distress-domains.update');
        Route::delete('distress-domains/{distressDomain}', [Web\DistressDomainController::class, 'destroy'])->name('distress-domains.destroy');

        // Lookup items (purpose of call, services, radio channel, project)
        Route::post('lookup-items', [Web\LookupItemController::class, 'store'])->name('lookup-items.store');
        Route::put('lookup-items/{lookupItem}', [Web\LookupItemController::class, 'update'])->name('lookup-items.update');
        Route::delete('lookup-items/{lookupItem}', [Web\LookupItemController::class, 'destroy'])->name('lookup-items.destroy');

        // Call targets
        Route::get('call-targets', [Web\CallTargetController::class, 'index'])->name('call-targets.index');
        Route::post('call-targets', [Web\CallTargetController::class, 'store'])->name('call-targets.store');
        Route::delete('call-targets/{user}', [Web\CallTargetController::class, 'destroy'])->name('call-targets.destroy');

        Route::get('extensions', [Web\ExtensionController::class, 'index'])->name('extensions.index');
        Route::get('users', [Web\UserController::class, 'index'])->name('users.index');
        Route::post('users', [Web\UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [Web\UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [Web\UserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/toggle-active', [Web\UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::post('users/{user}/reset-password', [Web\UserController::class, 'resetPassword'])->name('users.reset-password');

        Route::post('extensions/sync', [Web\ExtensionController::class, 'sync'])->name('extensions.sync');
        Route::put('extensions/{extension}', [Web\ExtensionController::class, 'update'])->name('extensions.update');
        Route::post('extensions/{extension}/assign-user', [Web\ExtensionController::class, 'assignUser'])->name('extensions.assign-user');

        Route::post('calls/sync', [Web\CallController::class, 'sync'])->name('calls.sync');

        Route::get('yeastar-settings', [Web\YeastarSettingsController::class, 'index'])->name('yeastar-settings.index');
        Route::post('yeastar-settings', [Web\YeastarSettingsController::class, 'update'])->name('yeastar-settings.update');
        Route::post('yeastar-settings/test', [Web\YeastarSettingsController::class, 'testConnection'])->name('yeastar-settings.test');
        Route::post('yeastar-settings/register-webhook', [Web\YeastarSettingsController::class, 'registerWebhook'])->name('yeastar-settings.register-webhook');
    });
});
