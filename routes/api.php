<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CallbackQueueController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExtensionController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Public: Yeastar & Twilio call these directly (no auth)
Route::post('webhooks/yeastar',  [WebhookController::class, 'yeastar']);
Route::post('webhooks/whatsapp', [WebhookController::class, 'whatsapp']);

// Auth (public)
Route::post('auth/login', [AuthController::class, 'login']);

// ─── Protected (all authenticated users) ────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('auth/me',      [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);

    // Active calls (for popup polling)
    Route::get('calls/active', [CallController::class, 'active']);

    // Dashboard
    Route::get('dashboard/stats',          [DashboardController::class, 'stats']);
    Route::get('dashboard/call-trend',     [DashboardController::class, 'callTrend']);
    Route::get('dashboard/top-extensions', [DashboardController::class, 'topExtensions']);

    // Calls — agents can read; sync is admin-only (see below)
    Route::get('calls',           [CallController::class, 'index']);
    Route::get('calls/missed',    [CallController::class, 'missed']);
    Route::get('calls/inbound',   [CallController::class, 'inbound']);
    Route::get('calls/outbound',  [CallController::class, 'outbound']);
    Route::get('calls/{call}',    [CallController::class, 'show']);
    Route::post('calls/{call}/link-client', [CallController::class, 'linkClient']);

    // Extensions — read available to all; sync admin-only (below)
    Route::get('extensions',             [ExtensionController::class, 'index']);
    Route::get('extensions/{extension}', [ExtensionController::class, 'show']);

    // Clients
    Route::get('clients',             [ClientController::class, 'index']);
    Route::get('clients/lookup',      [ClientController::class, 'findByPhone']);
    Route::post('clients',            [ClientController::class, 'store']);
    Route::get('clients/{client}',    [ClientController::class, 'show']);
    Route::put('clients/{client}',    [ClientController::class, 'update']);
    Route::delete('clients/{client}', [ClientController::class, 'destroy']);

    // Callback Queue
    Route::get('callbacks',                           [CallbackQueueController::class, 'index']);
    Route::post('callbacks',                          [CallbackQueueController::class, 'store']);
    Route::post('callbacks/{callbackQueue}/assign',   [CallbackQueueController::class, 'assign']);
    Route::post('callbacks/{callbackQueue}/complete', [CallbackQueueController::class, 'complete']);
    Route::delete('callbacks/{callbackQueue}',        [CallbackQueueController::class, 'destroy']);

    // Tickets
    Route::get('tickets',             [TicketController::class, 'index']);
    Route::post('tickets',            [TicketController::class, 'store']);
    Route::get('tickets/{ticket}',    [TicketController::class, 'show']);
    Route::put('tickets/{ticket}',    [TicketController::class, 'update']);
    Route::delete('tickets/{ticket}', [TicketController::class, 'destroy']);

    // Recordings
    Route::get('recordings/{recording}',          [RecordingController::class, 'show']);
    Route::get('recordings/{recording}/download', [RecordingController::class, 'download']);

    // Analytics
    Route::get('analytics/overview',          [AnalyticsController::class, 'overview']);
    Route::get('analytics/agent-performance', [AnalyticsController::class, 'agentPerformance']);

    // Agent stats (admin + self-view)
    Route::get('users/agent-stats', [UserController::class, 'agentStats']);

    // ─── Admin-only ──────────────────────────────────────────────────────────
    Route::middleware('admin')->group(function () {

        // User / Agent management
        Route::get('users',                        [UserController::class, 'index']);
        Route::post('users',                       [UserController::class, 'store']);
        Route::get('users/{user}',                 [UserController::class, 'show']);
        Route::put('users/{user}',                 [UserController::class, 'update']);
        Route::delete('users/{user}',              [UserController::class, 'destroy']);
        Route::post('users/{user}/toggle-active',  [UserController::class, 'toggleActive']);
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);

        // PBX sync (admin only)
        Route::post('calls/sync',                             [CallController::class, 'sync']);
        Route::post('extensions/sync',                        [ExtensionController::class, 'sync']);
        Route::put('extensions/{extension}',                  [ExtensionController::class, 'update']);
        Route::post('extensions/{extension}/assign-user',     [ExtensionController::class, 'assignUser']);
    });
});
