<?php

use App\Http\Controllers\Web;
use Illuminate\Support\Facades\Route;

// ─── Public (no auth) ────────────────────────────────────────────────────────
Route::get('screen',               [Web\PublicDashboardController::class, 'index'])->name('public.dashboard');
Route::get('screen/data',          [Web\PublicDashboardController::class, 'data'])->name('public.dashboard.data');
Route::get('screen/uchat-history', [Web\PublicDashboardController::class, 'uchatHistory'])->name('public.dashboard.uchat-history');

// Token-gated certificate download (max 2 downloads, then URL is dead)
Route::get('cert/{token}',         [Web\SbcController::class, 'downloadCert'])->name('cert.download');

// ─── Guest ───────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login', [Web\AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [Web\AuthController::class, 'login']);
});

// ─── Authenticated ────────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('logout', [Web\AuthController::class, 'logout'])->name('logout');
    Route::redirect('/', '/dashboard');

    Route::get('change-password',  [Web\AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('change-password', [Web\AuthController::class, 'changePassword'])->name('password.change.store');

    Route::get('profile',           [Web\ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile/password',  [Web\ProfileController::class, 'updatePassword'])->name('profile.password.update');

    Route::get('audit-log', [Web\AuditLogController::class, 'index'])->name('audit-log.index');

    // ─── Data Collection — Phase 1 (Projects, Forms, Form Versions) ──────────
    Route::get('data-collection',                    [Web\DataCollectionProjectController::class, 'index'])->name('data-collection.projects.index');
    Route::post('data-collection/projects',           [Web\DataCollectionProjectController::class, 'store'])->name('data-collection.projects.store');
    Route::get('data-collection/projects/{project}',  [Web\DataCollectionProjectController::class, 'show'])->name('data-collection.projects.show');
    Route::put('data-collection/projects/{project}',  [Web\DataCollectionProjectController::class, 'update'])->name('data-collection.projects.update');
    Route::delete('data-collection/projects/{project}', [Web\DataCollectionProjectController::class, 'destroy'])->name('data-collection.projects.destroy');

    Route::post('data-collection/projects/{project}/forms', [Web\DataCollectionFormController::class, 'store'])->name('data-collection.forms.store');
    Route::get('data-collection/forms/{form}',        [Web\DataCollectionFormController::class, 'show'])->name('data-collection.forms.show');
    Route::put('data-collection/forms/{form}',        [Web\DataCollectionFormController::class, 'update'])->name('data-collection.forms.update');
    Route::delete('data-collection/forms/{form}',     [Web\DataCollectionFormController::class, 'destroy'])->name('data-collection.forms.destroy');

    Route::post('data-collection/forms/{form}/versions', [Web\DataCollectionFormVersionController::class, 'store'])->name('data-collection.versions.store');
    Route::put('data-collection/forms/{form}/versions/{version}', [Web\DataCollectionFormVersionController::class, 'update'])->name('data-collection.versions.update');
    Route::post('data-collection/forms/{form}/versions/{version}/publish', [Web\DataCollectionFormVersionController::class, 'publish'])->name('data-collection.versions.publish');

    Route::post('data-collection/forms/{form}/assignments', [Web\DataCollectionAssignmentController::class, 'store'])->name('data-collection.assignments.store');

    Route::get('my-collection', [Web\DataCollectionSubmissionController::class, 'index'])->name('data-collection.my-collection');
    Route::post('data-collection/assignments/{assignment}/start', [Web\DataCollectionSubmissionController::class, 'start'])->name('data-collection.submissions.start');
    Route::get('data-collection/submissions/{submission}',        [Web\DataCollectionSubmissionController::class, 'show'])->name('data-collection.submissions.show');
    Route::put('data-collection/submissions/{submission}',        [Web\DataCollectionSubmissionController::class, 'update'])->name('data-collection.submissions.update');
    Route::post('data-collection/submissions/{submission}/submit', [Web\DataCollectionSubmissionController::class, 'submit'])->name('data-collection.submissions.submit');

    Route::get('data-collection/review-queue', [Web\DataCollectionSubmissionController::class, 'reviewQueue'])->name('data-collection.review-queue');
    Route::post('data-collection/submissions/{submission}/start-review',        [Web\DataCollectionSubmissionController::class, 'startReview'])->name('data-collection.submissions.start-review');
    Route::post('data-collection/submissions/{submission}/approve',             [Web\DataCollectionSubmissionController::class, 'approve'])->name('data-collection.submissions.approve');
    Route::post('data-collection/submissions/{submission}/reject',              [Web\DataCollectionSubmissionController::class, 'reject'])->name('data-collection.submissions.reject');
    Route::post('data-collection/submissions/{submission}/request-correction',  [Web\DataCollectionSubmissionController::class, 'requestCorrection'])->name('data-collection.submissions.request-correction');

    Route::get('dashboard', [Web\DashboardController::class, 'index'])->name('dashboard');
    Route::post('announcements', [Web\AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('my-work',   [Web\MyWorkController::class, 'index'])->name('my-work');
    Route::get('dialer',           fn() => inertia('Dialer/Index'))->name('dialer');
    Route::get('dialer/sip-config', [Web\ExtensionController::class, 'mySipConfig'])->name('dialer.sip-config');

    // Service Directory — visible to all authenticated users; edits admin-only
    Route::get('service-directory', [Web\ServiceProviderController::class, 'index'])->name('service-providers.index');
    Route::middleware('admin')->group(function () {
        Route::post('service-directory', [Web\ServiceProviderController::class, 'store'])->name('service-providers.store');
        Route::put('service-directory/{serviceProvider}', [Web\ServiceProviderController::class, 'update'])->name('service-providers.update');
        Route::delete('service-directory/{serviceProvider}', [Web\ServiceProviderController::class, 'destroy'])->name('service-providers.destroy');
    });

    // Calls
    Route::get('calls', [Web\CallController::class, 'index'])->name('calls.index');
    Route::get('calls/number-search', [Web\TicketController::class, 'searchNumbers'])->name('calls.number-search');
    Route::get('calls/export', [Web\CallController::class, 'export'])->name('calls.export');
    Route::get('calls/{call}', [Web\CallController::class, 'show'])->name('calls.show');
    Route::post('calls/{call}/link-client', [Web\CallController::class, 'linkClient'])->name('calls.link-client');

    Route::get('recordings', [Web\RecordingController::class, 'index'])->name('recordings.index');

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
    Route::patch('urgent-cases/{urgentCase}', [Web\UrgentCaseController::class, 'updateStatus'])->name('urgent-cases.update');
    Route::post('urgent-cases/{urgentCase}/ticket', [Web\UrgentCaseController::class, 'createTicket'])->name('urgent-cases.ticket');

    // Tickets
    Route::get('tickets', [Web\TicketController::class, 'index'])->name('tickets.index');
    Route::post('tickets', [Web\TicketController::class, 'store'])->name('tickets.store');
    Route::post('tickets/draft-notes', [Web\TicketController::class, 'draftNotes'])->name('tickets.draft-notes');
    Route::get('tickets/export', [Web\TicketController::class, 'export'])->name('tickets.export');
    Route::get('tickets/import', [Web\TicketImportController::class, 'create'])->name('tickets.import')->middleware('admin');
    Route::post('tickets/import', [Web\TicketImportController::class, 'store'])->name('tickets.import.store')->middleware('admin');
    Route::get('tickets/{ticket}', [Web\TicketController::class, 'show'])->name('tickets.show');
    Route::put('tickets/{ticket}', [Web\TicketController::class, 'update'])->name('tickets.update');
    Route::delete('tickets/{ticket}', [Web\TicketController::class, 'destroy'])->name('tickets.destroy');

    // ─── Blocked Numbers (all authenticated users) ────────────────────────────
    Route::get('blocked-numbers',           [Web\BlockedNumberController::class, 'index'])->name('blocked-numbers.index');
    Route::post('blocked-numbers',          [Web\BlockedNumberController::class, 'store'])->name('blocked-numbers.store');
    Route::put('blocked-numbers/{id}',      [Web\BlockedNumberController::class, 'update'])->name('blocked-numbers.update');
    Route::delete('blocked-numbers/{id}',   [Web\BlockedNumberController::class, 'destroy'])->name('blocked-numbers.destroy');

    // ─── Blocked Number Requests (agents submit, admins approve) ─────────────
    Route::get('blocked-number-requests',                          [Web\BlockedNumberRequestController::class, 'index'])->name('blocked-number-requests.index');
    Route::post('blocked-number-requests',                         [Web\BlockedNumberRequestController::class, 'store'])->name('blocked-number-requests.store');
    Route::post('blocked-number-requests/{blockedNumberRequest}/approve', [Web\BlockedNumberRequestController::class, 'approve'])->name('blocked-number-requests.approve');
    Route::post('blocked-number-requests/{blockedNumberRequest}/reject',  [Web\BlockedNumberRequestController::class, 'reject'])->name('blocked-number-requests.reject');
    Route::delete('blocked-number-requests/{blockedNumberRequest}', [Web\BlockedNumberRequestController::class, 'destroy'])->name('blocked-number-requests.destroy');

    // ─── Staff Performance Appraisals (all authenticated users, scoped) ──────
    Route::get('appraisals',                    [Web\AppraisalController::class, 'index'])->name('appraisals.index');
    Route::post('appraisals',                   [Web\AppraisalController::class, 'store'])->name('appraisals.store');
    Route::get('appraisals/{appraisal}',        [Web\AppraisalController::class, 'show'])->name('appraisals.show');
    Route::put('appraisals/{appraisal}',        [Web\AppraisalController::class, 'update'])->name('appraisals.update');
    Route::post('appraisals/{appraisal}/submit',  [Web\AppraisalController::class, 'submit'])->name('appraisals.submit');
    Route::post('appraisals/{appraisal}/complete', [Web\AppraisalController::class, 'complete'])->name('appraisals.complete');
    Route::post('appraisals/{appraisal}/reopen',   [Web\AppraisalController::class, 'reopen'])->name('appraisals.reopen');
    Route::delete('appraisals/{appraisal}',        [Web\AppraisalController::class, 'destroy'])->name('appraisals.destroy');

    // ─── Supervisor Reviews (separate page + sidebar entry) ──────────────────
    Route::get('appraisal-reviews',                [Web\AppraisalController::class, 'reviewIndex'])->name('appraisal-reviews.index');
    Route::get('appraisals/{appraisal}/review',     [Web\AppraisalController::class, 'review'])->name('appraisals.review.show');
    Route::put('appraisals/{appraisal}/review',     [Web\AppraisalController::class, 'updateReview'])->name('appraisals.review.update');

    // ─── Admin/Director archive — view & download every appraisal ────────────
    Route::get('appraisal-archive',              [Web\AppraisalController::class, 'adminIndex'])->name('appraisals.archive');
    Route::get('appraisals/{appraisal}/document', [Web\AppraisalController::class, 'document'])->name('appraisals.document');

    // ─── Work Management (workspaces → boards → groups → tasks) ─────────────
    Route::get('workspaces',                    [Web\WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('workspaces',                   [Web\WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('workspaces/{workspace}',        [Web\WorkspaceController::class, 'show'])->name('workspaces.show');
    Route::put('workspaces/{workspace}',        [Web\WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('workspaces/{workspace}',     [Web\WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

    Route::get('boards',                    [Web\BoardController::class, 'index'])->name('boards.index');
    Route::post('boards',                   [Web\BoardController::class, 'store'])->name('boards.store');
    Route::get('boards/{board}',            [Web\BoardController::class, 'show'])->name('boards.show');
    Route::put('boards/{board}',            [Web\BoardController::class, 'update'])->name('boards.update');
    Route::delete('boards/{board}',         [Web\BoardController::class, 'destroy'])->name('boards.destroy');

    Route::post('boards/{board}/groups',                 [Web\TaskGroupController::class, 'store'])->name('boards.groups.store');
    Route::put('boards/{board}/groups/{group}',           [Web\TaskGroupController::class, 'update'])->name('boards.groups.update');
    Route::delete('boards/{board}/groups/{group}',        [Web\TaskGroupController::class, 'destroy'])->name('boards.groups.destroy');

    Route::get('tasks/{task}',                  [Web\TaskController::class, 'show'])->name('tasks.show');
    Route::post('tasks',                        [Web\TaskController::class, 'store'])->name('tasks.store');
    Route::put('tasks/{task}',                  [Web\TaskController::class, 'update'])->name('tasks.update');
    Route::delete('tasks/{task}',               [Web\TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('tasks/{task}/assign',          [Web\TaskController::class, 'assign'])->name('tasks.assign');
    Route::post('tasks/{task}/status',          [Web\TaskController::class, 'changeStatus'])->name('tasks.status');
    Route::post('tasks/{task}/priority',        [Web\TaskController::class, 'changePriority'])->name('tasks.priority');
    Route::post('tasks/{task}/complete',        [Web\TaskController::class, 'complete'])->name('tasks.complete');
    Route::post('tasks/{task}/reopen',          [Web\TaskController::class, 'reopen'])->name('tasks.reopen');
    Route::post('tasks/{task}/archive',         [Web\TaskController::class, 'archive'])->name('tasks.archive');
    Route::post('tasks/{task}/restore',         [Web\TaskController::class, 'restore'])->name('tasks.restore');
    Route::post('tasks/{task}/comments',        [Web\TaskController::class, 'comment'])->name('tasks.comments.store');

    Route::get('team/tasks', [Web\TeamTaskController::class, 'index'])->name('team.tasks');

    // ─── Stores & Assets Management — Phase 1 (foundation) ───────────────────
    Route::get('departments',              [Web\DepartmentController::class, 'index'])->name('departments.index');
    Route::post('departments',             [Web\DepartmentController::class, 'store'])->name('departments.store');
    Route::put('departments/{department}', [Web\DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('departments/{department}', [Web\DepartmentController::class, 'destroy'])->name('departments.destroy');

    Route::get('locations',            [Web\LocationController::class, 'index'])->name('locations.index');
    Route::post('locations',           [Web\LocationController::class, 'store'])->name('locations.store');
    Route::put('locations/{location}', [Web\LocationController::class, 'update'])->name('locations.update');
    Route::delete('locations/{location}', [Web\LocationController::class, 'destroy'])->name('locations.destroy');

    Route::get('stores',         [Web\StoreController::class, 'index'])->name('stores.index');
    Route::post('stores',        [Web\StoreController::class, 'store'])->name('stores.store');
    Route::get('stores/{store}', [Web\StoreController::class, 'show'])->name('stores.show');
    Route::put('stores/{store}', [Web\StoreController::class, 'update'])->name('stores.update');
    Route::delete('stores/{store}', [Web\StoreController::class, 'destroy'])->name('stores.destroy');

    Route::get('item-categories',                  [Web\ItemCategoryController::class, 'index'])->name('item-categories.index');
    Route::post('item-categories',                 [Web\ItemCategoryController::class, 'store'])->name('item-categories.store');
    Route::put('item-categories/{itemCategory}',    [Web\ItemCategoryController::class, 'update'])->name('item-categories.update');
    Route::delete('item-categories/{itemCategory}', [Web\ItemCategoryController::class, 'destroy'])->name('item-categories.destroy');

    Route::get('items',        [Web\ItemController::class, 'index'])->name('items.index');
    Route::post('items',       [Web\ItemController::class, 'store'])->name('items.store');
    Route::get('items/{item}', [Web\ItemController::class, 'show'])->name('items.show');
    Route::put('items/{item}', [Web\ItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [Web\ItemController::class, 'destroy'])->name('items.destroy');

    // ─── Stores & Assets Management — Phase 2 (stock movements) ──────────────
    Route::post('stores/{store}/receipts', [Web\StockReceiptController::class, 'store'])->name('stores.receipts.store');
    Route::post('stores/{store}/issues',   [Web\StockIssueController::class, 'store'])->name('stores.issues.store');
    Route::post('stores/{store}/items/{item}/adjust', [Web\StockAdjustmentController::class, 'store'])->name('stores.items.adjust');

    Route::get('stock-transfers',                    [Web\StockTransferController::class, 'index'])->name('stock-transfers.index');
    Route::post('stock-transfers',                   [Web\StockTransferController::class, 'store'])->name('stock-transfers.store');
    Route::get('stock-transfers/{stockTransfer}',     [Web\StockTransferController::class, 'show'])->name('stock-transfers.show');
    Route::post('stock-transfers/{stockTransfer}/dispatch', [Web\StockTransferController::class, 'dispatch'])->name('stock-transfers.dispatch');
    Route::post('stock-transfers/{stockTransfer}/receive',  [Web\StockTransferController::class, 'receive'])->name('stock-transfers.receive');
    Route::post('stock-transfers/{stockTransfer}/cancel',   [Web\StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');

    Route::get('stocktakes',                  [Web\StocktakeController::class, 'index'])->name('stocktakes.index');
    Route::post('stocktakes',                 [Web\StocktakeController::class, 'store'])->name('stocktakes.store');
    Route::get('stocktakes/{stocktake}',      [Web\StocktakeController::class, 'show'])->name('stocktakes.show');
    Route::put('stocktakes/{stocktake}',      [Web\StocktakeController::class, 'update'])->name('stocktakes.update');
    Route::post('stocktakes/{stocktake}/complete', [Web\StocktakeController::class, 'complete'])->name('stocktakes.complete');

    // ─── Fixed Assets — Phase 3 (register, assign, return, transfer, dispose) ─
    Route::get('asset-categories',                  [Web\AssetCategoryController::class, 'index'])->name('asset-categories.index');
    Route::post('asset-categories',                 [Web\AssetCategoryController::class, 'store'])->name('asset-categories.store');
    Route::put('asset-categories/{assetCategory}',    [Web\AssetCategoryController::class, 'update'])->name('asset-categories.update');
    Route::delete('asset-categories/{assetCategory}', [Web\AssetCategoryController::class, 'destroy'])->name('asset-categories.destroy');

    Route::get('fixed-assets',              [Web\FixedAssetController::class, 'index'])->name('fixed-assets.index');
    Route::post('fixed-assets',             [Web\FixedAssetController::class, 'store'])->name('fixed-assets.store');
    Route::get('fixed-assets/{fixedAsset}', [Web\FixedAssetController::class, 'show'])->name('fixed-assets.show');
    Route::put('fixed-assets/{fixedAsset}', [Web\FixedAssetController::class, 'update'])->name('fixed-assets.update');
    Route::post('fixed-assets/{fixedAsset}/assign',   [Web\FixedAssetController::class, 'assign'])->name('fixed-assets.assign');
    Route::post('fixed-assets/{fixedAsset}/return',   [Web\FixedAssetController::class, 'returnAsset'])->name('fixed-assets.return');
    Route::post('fixed-assets/{fixedAsset}/transfer', [Web\FixedAssetController::class, 'transfer'])->name('fixed-assets.transfer');
    Route::post('fixed-assets/{fixedAsset}/dispose',  [Web\FixedAssetController::class, 'dispose'])->name('fixed-assets.dispose');

    Route::post('fixed-assets/{fixedAsset}/maintenance', [Web\FixedAssetMaintenanceController::class, 'store'])->name('fixed-assets.maintenance.store');
    Route::post('fixed-assets/{fixedAsset}/maintenance/{maintenance}/complete', [Web\FixedAssetMaintenanceController::class, 'complete'])->name('fixed-assets.maintenance.complete');
    Route::post('fixed-assets/{fixedAsset}/maintenance/{maintenance}/cancel',   [Web\FixedAssetMaintenanceController::class, 'cancel'])->name('fixed-assets.maintenance.cancel');

    Route::post('fixed-assets/{fixedAsset}/inspections', [Web\FixedAssetInspectionController::class, 'store'])->name('fixed-assets.inspections.store');

    // ─── Notifications (bell dropdown, JSON) ─────────────────────────────────
    Route::get('notifications',              [Web\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read',   [Web\NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all',    [Web\NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // ─── Activity Reports (compile → review → approve, scoped) ───────────────
    Route::get('activity-reports',                          [Web\ActivityReportController::class, 'index'])->name('activity-reports.index');
    Route::post('activity-reports',                         [Web\ActivityReportController::class, 'store'])->name('activity-reports.store');
    Route::get('activity-reports/{activityReport}',         [Web\ActivityReportController::class, 'show'])->name('activity-reports.show');
    Route::put('activity-reports/{activityReport}',         [Web\ActivityReportController::class, 'update'])->name('activity-reports.update');
    Route::put('activity-reports/{activityReport}/viewers', [Web\ActivityReportController::class, 'updateViewers'])->name('activity-reports.viewers');
    Route::post('activity-reports/{activityReport}/submit',  [Web\ActivityReportController::class, 'submit'])->name('activity-reports.submit');
    Route::post('activity-reports/{activityReport}/review',  [Web\ActivityReportController::class, 'review'])->name('activity-reports.review');
    Route::post('activity-reports/{activityReport}/approve', [Web\ActivityReportController::class, 'approve'])->name('activity-reports.approve');
    Route::post('activity-reports/{activityReport}/reopen',  [Web\ActivityReportController::class, 'reopen'])->name('activity-reports.reopen');
    Route::delete('activity-reports/{activityReport}',       [Web\ActivityReportController::class, 'destroy'])->name('activity-reports.destroy');

    // ─── SBC / YALeP — all authenticated users ───────────────────────────────
    Route::get('sbc',                         [Web\SbcController::class, 'index'])->name('sbc.index');
    Route::post('sbc/import',                 [Web\SbcController::class, 'import'])->name('sbc.import');
    Route::get('sbc/import-template',         [Web\SbcController::class, 'importTemplate'])->name('sbc.import-template');
    Route::get('sbc/{signup}/certificate',    [Web\SbcController::class, 'certificate'])->name('sbc.certificate');

    // ─── Asset Register (admin only) ─────────────────────────────────────────
    Route::middleware('admin')->prefix('registry')->group(function () {
        Route::get('/', [Web\AssetRegisterController::class, 'index'])->name('registry.index');
        Route::get('/assets/create', [Web\AssetController::class, 'create'])->name('assets.create');
        Route::post('/assets', [Web\AssetController::class, 'store'])->name('assets.store');
        Route::get('/assets/{asset}', [Web\AssetController::class, 'show'])->name('assets.show');
        Route::get('/assets/{asset}/edit', [Web\AssetController::class, 'edit'])->name('assets.edit');
        Route::put('/assets/{asset}', [Web\AssetController::class, 'update'])->name('assets.update');
        Route::delete('/assets/{asset}', [Web\AssetController::class, 'destroy'])->name('assets.destroy');
        Route::get('/export', [Web\AssetRegisterController::class, 'export'])->name('registry.export');
    });

    // ─── Risk Register (admin only) ──────────────────────────────────────────
    Route::middleware('admin')->prefix('risk')->group(function () {
        Route::get('/', [Web\RiskDashboardController::class, 'index'])->name('risk.dashboard');
        Route::get('/risks', [Web\RiskController::class, 'index'])->name('risks.index');
        Route::post('/risks', [Web\RiskController::class, 'store'])->name('risks.store');
        Route::put('/risks/{risk}', [Web\RiskController::class, 'update'])->name('risks.update');
        Route::delete('/risks/{risk}', [Web\RiskController::class, 'destroy'])->name('risks.destroy');
        Route::post('/controls', [Web\ControlController::class, 'store'])->name('controls.store');
        Route::put('/controls/{control}', [Web\ControlController::class, 'update'])->name('controls.update');
        Route::delete('/controls/{control}', [Web\ControlController::class, 'destroy'])->name('controls.destroy');
        Route::get('/actions', [Web\PriorityActionController::class, 'index'])->name('actions.index');
        Route::post('/actions', [Web\PriorityActionController::class, 'store'])->name('actions.store');
        Route::put('/actions/{action}', [Web\PriorityActionController::class, 'update'])->name('actions.update');
        Route::delete('/actions/{action}', [Web\PriorityActionController::class, 'destroy'])->name('actions.destroy');
        Route::get('/report', [Web\RiskReportController::class, 'export'])->name('risk.report');
    });

    // ─── Admin only ───────────────────────────────────────────────────────────
    Route::middleware('admin')->group(function () {
        Route::get('analytics', [Web\AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('uchat-contacts', [Web\UchatContactsController::class, 'index'])->name('uchat-contacts.index');

        // SBC — admin-only actions (sync, template upload, WhatsApp send)
        Route::post('sbc/sync',                   [Web\SbcController::class, 'sync'])->name('sbc.sync');
        Route::post('sbc/upload-template',        [Web\SbcController::class, 'uploadTemplate'])->name('sbc.upload-template');
        Route::post('sbc/{signup}/send-whatsapp', [Web\SbcController::class, 'sendWhatsapp'])->name('sbc.send-whatsapp');

        // Roles management
        Route::get('roles',             [Web\RoleController::class, 'index'])->name('roles.index');
        Route::post('roles',            [Web\RoleController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}',      [Web\RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}',   [Web\RoleController::class, 'destroy'])->name('roles.destroy');

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
