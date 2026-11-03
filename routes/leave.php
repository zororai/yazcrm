<?php

use App\Http\Controllers\Api\V1\Leave\LeaveApprovalController;
use App\Http\Controllers\Api\V1\Leave\LeaveCalendarController;
use App\Http\Controllers\Api\V1\Leave\LeaveReportController;
use App\Http\Controllers\Api\V1\Leave\LeaveRequestController;
use App\Http\Controllers\Api\V1\Leave\LeaveSettingsController;
use Illuminate\Support\Facades\Route;

// Leave, for the staff app. Loaded from routes/api.php, so these sit under
// /api/v1/leave with the same Sanctum token and 401 {code} format as the
// other app routes. Every logged-in, active user reaches every route; who may
// do what is decided per request by LeaveAccess / LeaveRequestPolicy.
Route::prefix(config('data_collection.api_prefix').'/leave')
    ->middleware(['auth:sanctum', 'active.user'])
    ->name('api.leave.')
    ->group(function () {
        // Anyone: their own leave
        Route::get('balances', [LeaveRequestController::class, 'balances'])->name('balances');
        Route::get('working-days', [LeaveRequestController::class, 'workingDays'])->name('working-days');
        Route::get('requests', [LeaveRequestController::class, 'index'])->name('requests.index');
        Route::post('requests', [LeaveRequestController::class, 'store'])->name('requests.store');
        Route::get('requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('requests.show');
        Route::get('requests/{leaveRequest}/attachment', [LeaveRequestController::class, 'attachment'])->name('requests.attachment');
        Route::patch('requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('requests.cancel');

        // Approvers and admins (others get an empty list or a 403)
        Route::get('pending', [LeaveApprovalController::class, 'pending'])->name('pending');
        Route::patch('requests/{leaveRequest}/approve', [LeaveApprovalController::class, 'approve'])->name('requests.approve');
        Route::patch('requests/{leaveRequest}/reject', [LeaveApprovalController::class, 'reject'])->name('requests.reject');
        Route::get('calendar', [LeaveCalendarController::class, 'index'])->name('calendar');

        // Leave admins
        Route::patch('requests/{leaveRequest}/correct', [LeaveApprovalController::class, 'correct'])->name('requests.correct');
        Route::get('reports', [LeaveReportController::class, 'index'])->name('reports');

        // Settings: read by anyone, changed by leave admins
        Route::get('types', [LeaveSettingsController::class, 'types'])->name('types.index');
        Route::post('types', [LeaveSettingsController::class, 'storeType'])->name('types.store');
        Route::put('types/{leaveType}', [LeaveSettingsController::class, 'updateType'])->name('types.update');
        Route::delete('types/{leaveType}', [LeaveSettingsController::class, 'destroyType'])->name('types.destroy');

        Route::get('holidays', [LeaveSettingsController::class, 'holidays'])->name('holidays.index');
        Route::post('holidays', [LeaveSettingsController::class, 'storeHoliday'])->name('holidays.store');
        Route::put('holidays/{publicHoliday}', [LeaveSettingsController::class, 'updateHoliday'])->name('holidays.update');
        Route::delete('holidays/{publicHoliday}', [LeaveSettingsController::class, 'destroyHoliday'])->name('holidays.destroy');

        Route::get('entitlements', [LeaveSettingsController::class, 'entitlements'])->name('entitlements.index');
        Route::post('entitlements', [LeaveSettingsController::class, 'storeEntitlement'])->name('entitlements.store');
        Route::delete('entitlements/{leaveBalance}', [LeaveSettingsController::class, 'destroyEntitlement'])->name('entitlements.destroy');
    });
