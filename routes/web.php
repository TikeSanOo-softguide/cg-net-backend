<?php

use App\Http\Controllers\ActivityLog\ActivityLogController;
use App\Http\Controllers\AdminNotification\AdminNotificationController;
use App\Http\Controllers\BillPayment\BillPaymentController;
use App\Http\Controllers\Cms\BannerController;
use App\Http\Controllers\Cms\CategoryController;
use App\Http\Controllers\Cms\ContactController;
use App\Http\Controllers\Cms\GalleryController;
use App\Http\Controllers\Cms\NewsController;
use App\Http\Controllers\Cms\PromotionController;
use App\Http\Controllers\Cms\ServiceController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Locale\LocaleController;
use App\Http\Controllers\Log\SecurityLogController;
use App\Http\Controllers\Log\UserLogController;
use App\Http\Controllers\MenuPage\MenuPageController;
use App\Http\Controllers\Notification\AnnouncementController;
use App\Http\Controllers\Notification\PushNotificationController;
use App\Http\Controllers\Package\AddonController;
use App\Http\Controllers\Package\NetworkController;
use App\Http\Controllers\Package\PackageController;
use App\Http\Controllers\Package\SpeedController;
use App\Http\Controllers\Package\TermController;
use App\Http\Controllers\Region\RegionManagementController;
use App\Http\Controllers\Reports\BillingReportController;
use App\Http\Controllers\Reports\CustomerReportController;
use App\Http\Controllers\Reports\EodReportController;
use App\Http\Controllers\Reports\LedgerHealthReportController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\TopUpReport\TopUpReportController;
use App\Http\Controllers\ServiceRequest\BroadbandApplicationRequestController;
use App\Http\Controllers\ServiceRequest\ChangePasswordRequestController;
use App\Http\Controllers\ServiceRequest\ChangePlanRequestController;
use App\Http\Controllers\ServiceRequest\FailureReportController;
use App\Http\Controllers\ServiceRequest\RelocationRequestController;
use App\Http\Controllers\ServiceRequest\ServiceRequestController;
use App\Http\Controllers\Settings\AppVersionController;
use App\Http\Controllers\Staff\RoleController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Support\ChatConversations\ChatConversationsController;
use App\Http\Controllers\Support\ChatFlows\ChatbotFlowsController;
use App\Http\Controllers\TopUpCard\OfficeController;
use App\Http\Controllers\Support\QuickReplies\QuickRepliesController;
use App\Http\Controllers\TopUpCard\TopUpCardController;
use App\Http\Controllers\BillPayment\TransactionController;
use App\Support\AdminHome;
use App\Support\AppPermissions;
use App\Support\MenuPages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/locale/{lang}', LocaleController::class)->name('locale.update');

Route::middleware(['auth:web', 'admin.active'])->group(function () {
    Route::get('/', fn(Request $request) => redirect()->to(AdminHome::path($request->user())))->name('home');
    Route::prefix('dashboard')->group(function (): void {
        Route::get('/', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
        Route::get('/notifications', [AdminNotificationController::class, 'index'])
            ->middleware('can:notifications.view')
            ->name('dashboard.notifications.index');
        Route::put('/notifications/{notification}/read', [AdminNotificationController::class, 'markRead'])
            ->middleware('can:notifications.view')
            ->name('dashboard.notifications.read');
    });
    Route::delete('/dashboard/requests/bulk-destroy', [DashboardController::class, 'bulkDestroy'])
        ->middleware('can:service-requests.delete')
        ->name('dashboard.requests.bulk-destroy');

    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware('can:customers.view')
        ->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])
        ->middleware('can:customers.create')
        ->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware('can:customers.create')
        ->name('customers.store');
    Route::delete('/customers/bulk-destroy', [CustomerController::class, 'bulkDestroy'])
        ->middleware('can:customers.delete')
        ->name('customers.bulk-destroy');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
        ->middleware('can:customers.update')
        ->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('can:customers.update')
        ->name('customers.update');
    Route::patch('/customers/{customer}/status', [CustomerController::class, 'updateStatus'])
        ->middleware('can:customers.update')
        ->name('customers.status');
    Route::post('/customers/{customer}/account', [CustomerController::class, 'bindAccount'])
        ->middleware('can:customers.update')
        ->name('customers.account.bind');
    Route::delete('/customers/{customer}/accounts', [CustomerController::class, 'unbindAccount'])
        ->middleware('can:customers.update')
        ->name('customers.accounts.unbind');
    Route::post('/customers/{customer}/wallet/adjust', [CustomerController::class, 'adjustWallet'])->name(
        'customers.wallet.adjust',
    );
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->middleware('can:customers.delete')
        ->name('customers.destroy');
    Route::get('/customers/{customer}/transactions/export', [TransactionController::class, 'exportCustomer'])
        ->middleware(['can:customers.view', 'can:' . AppPermissions::SystemExport])
        ->name('customers.transactions.export');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])
        ->middleware('can:customers.view')
        ->name('customers.show');

    Route::prefix('billing')
        ->name('billing.')
        ->middleware('can:billing.view')
        ->group(function () {
            Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
            Route::get('/bill-payments', [BillPaymentController::class, 'index'])->name('bill-payments');
            Route::get('/bill-payments/export', [BillPaymentController::class, 'export'])
                ->middleware('can:' . AppPermissions::SystemExport)
                ->name('bill-payments.export');
            Route::get('/transactions/export', [TransactionController::class, 'export'])
                ->middleware('can:' . AppPermissions::SystemExport)
                ->name('transactions.export');
        });

    Route::prefix('regions')
        ->name('regions.')
        ->group(function () {
            Route::get('/', [RegionManagementController::class, 'index'])
                ->middleware('can:regions.view')
                ->name('index');

            Route::post('/states', [RegionManagementController::class, 'storeState'])
                ->middleware('can:regions.create')
                ->name('states.store');
            Route::put('/states/{state}', [RegionManagementController::class, 'updateState'])
                ->middleware('can:regions.update')
                ->name('states.update');
            Route::delete('/states/bulk-destroy', [RegionManagementController::class, 'bulkDestroyStates'])
                ->middleware('can:regions.delete')
                ->name('states.bulk-destroy');
            Route::delete('/states/{state}', [RegionManagementController::class, 'destroyState'])
                ->middleware('can:regions.delete')
                ->name('states.destroy');

            Route::post('/regions', [RegionManagementController::class, 'storeRegion'])
                ->middleware('can:regions.create')
                ->name('regions.store');
            Route::put('/regions/{region}', [RegionManagementController::class, 'updateRegion'])
                ->middleware('can:regions.update')
                ->name('regions.update');
            Route::delete('/regions/bulk-destroy', [RegionManagementController::class, 'bulkDestroyRegions'])
                ->middleware('can:regions.delete')
                ->name('regions.bulk-destroy');
            Route::delete('/regions/{region}', [RegionManagementController::class, 'destroyRegion'])
                ->middleware('can:regions.delete')
                ->name('regions.destroy');

            Route::post('/areas', [RegionManagementController::class, 'storeArea'])
                ->middleware('can:regions.create')
                ->name('areas.store');
            Route::put('/areas/{area}', [RegionManagementController::class, 'updateArea'])
                ->middleware('can:regions.update')
                ->name('areas.update');
            Route::delete('/areas/bulk-destroy', [RegionManagementController::class, 'bulkDestroyAreas'])
                ->middleware('can:regions.delete')
                ->name('areas.bulk-destroy');
            Route::delete('/areas/{area}', [RegionManagementController::class, 'destroyArea'])
                ->middleware('can:regions.delete')
                ->name('areas.destroy');
        });

    Route::prefix('cms')
        ->name('cms.')
        ->group(function () {
            foreach (
                [
                    'promotions' => [PromotionController::class, 'promotion'],
                    'banners' => [BannerController::class, 'banner'],
                    'categories' => [CategoryController::class, 'category'],
                    'news' => [NewsController::class, 'news'],
                    'gallery' => [GalleryController::class, 'gallery'],
                    'contacts' => [ContactController::class, 'contact'],
                    'services' => [ServiceController::class, 'service'],
                ]
                as $name => [$controller, $parameter]
            ) {
                Route::get($name, [$controller, 'index'])
                    ->middleware('can:cms.view')
                    ->name($name . '.index');
                Route::get($name . '/create', [$controller, 'create'])
                    ->middleware('can:cms.create')
                    ->name($name . '.create');
                Route::post($name, [$controller, 'store'])
                    ->middleware('can:cms.create')
                    ->name($name . '.store');
                Route::delete($name . '/bulk-destroy', [$controller, 'bulkDestroy'])
                    ->middleware('can:cms.delete')
                    ->name($name . '.bulk-destroy');
                Route::get($name . '/{' . $parameter . '}/edit', [$controller, 'edit'])
                    ->middleware('can:cms.update')
                    ->name($name . '.edit');
                Route::put($name . '/{' . $parameter . '}', [$controller, 'update'])
                    ->middleware('can:cms.update')
                    ->name($name . '.update');
                Route::delete($name . '/{' . $parameter . '}', [$controller, 'destroy'])
                    ->middleware('can:cms.delete')
                    ->name($name . '.destroy');
            }
        });

    Route::prefix('notifications')
        ->name('notifications.')
        ->group(function () {
            Route::prefix('announcement')
                ->name('announcement.')
                ->group(function () {
                    Route::get('/', [AnnouncementController::class, 'index'])
                        ->middleware('can:notifications.view')
                        ->name('index');
                    Route::get('/create', [AnnouncementController::class, 'create'])
                        ->middleware('can:notifications.create')
                        ->name('create');
                    Route::post('/', [AnnouncementController::class, 'store'])
                        ->middleware('can:notifications.create')
                        ->name('store');
                    Route::delete('/bulk-destroy', [AnnouncementController::class, 'bulkDestroy'])
                        ->middleware('can:notifications.delete')
                        ->name('bulk-destroy');
                    Route::get('/{announcement}/edit', [AnnouncementController::class, 'edit'])
                        ->middleware('can:notifications.update')
                        ->name('edit');
                    Route::put('/{announcement}', [AnnouncementController::class, 'update'])
                        ->middleware('can:notifications.update')
                        ->name('update');
                    Route::delete('/{announcement}', [AnnouncementController::class, 'destroy'])
                        ->middleware('can:notifications.delete')
                        ->name('destroy');
                    Route::get('/{announcement}', [AnnouncementController::class, 'show'])
                        ->middleware('can:notifications.view')
                        ->name('show');
                });

            Route::prefix('promotions')
                ->name('promotions.')
                ->group(function () {
                    Route::get('/', [PromotionController::class, 'index'])
                        ->middleware('can:notifications.view')
                        ->name('index');
                    Route::get('/create', [PromotionController::class, 'create'])
                        ->middleware('can:notifications.create')
                        ->name('create');
                    Route::post('/', [PromotionController::class, 'store'])
                        ->middleware('can:notifications.create')
                        ->name('store');
                    Route::delete('/bulk-destroy', [PromotionController::class, 'bulkDestroy'])
                        ->middleware('can:notifications.delete')
                        ->name('bulk-destroy');
                    Route::get('/{promotion}/edit', [PromotionController::class, 'edit'])
                        ->middleware('can:notifications.update')
                        ->name('edit');
                    Route::put('/{promotion}', [PromotionController::class, 'update'])
                        ->middleware('can:notifications.update')
                        ->name('update');
                    Route::delete('/{promotion}', [PromotionController::class, 'destroy'])
                        ->middleware('can:notifications.delete')
                        ->name('destroy');
                });

            Route::get('/compose', [PushNotificationController::class, 'index'])
                ->middleware('can:notifications.view')
                ->name('compose');
            Route::post('/compose/push-now', [PushNotificationController::class, 'pushNow'])
                ->middleware('can:notifications.create')
                ->name('compose.push-now');
            Route::post('/compose/schedule', [PushNotificationController::class, 'schedule'])
                ->middleware('can:notifications.create')
                ->name('compose.schedule');
            Route::post('/compose/{schedule}/cancel', [PushNotificationController::class, 'cancel'])
                ->middleware('can:notifications.update')
                ->name('compose.cancel');
        });

    Route::prefix('support')
        ->name('support.')
        ->group(function () {
            Route::prefix('conversations')
                ->name('conversations.')
                ->middleware('can:support.view')
                ->group(function () {
                    Route::get('/', [ChatConversationsController::class, 'index'])->name('chat-conversations.index');

                    Route::get('/{conversation}', [ChatConversationsController::class, 'show'])->name(
                        'chat-conversations.show',
                    );

                    Route::post('/{conversation}/messages', [ChatConversationsController::class, 'sendMessage'])
                        ->name('chat-conversations.messages.store')
                        ->middleware('can:support.create');

                    Route::put('/{conversation}/status', [ChatConversationsController::class, 'updateStatus'])
                        ->name('chat-conversations.status')
                        ->middleware('can:support.update');

                    Route::post('/{conversation}/quick-replies/{quickReply}', [
                        ChatConversationsController::class,
                        'useQuickReply',
                    ])
                        ->middleware('can:support.create')
                        ->name('chat-conversations.quick-reply');
                });
            Route::prefix('quick-replies')
                ->name('quick-replies.')
                ->group(function () {
                    Route::get('/', [QuickRepliesController::class, 'index'])
                        ->middleware('can:support.view')
                        ->name('index');
                    Route::post('/replies', [QuickRepliesController::class, 'store'])
                        ->middleware('can:support.create')
                        ->name('store');
                    Route::put('/replies/{quickReply}', [QuickRepliesController::class, 'update'])
                        ->middleware('can:support.update')
                        ->name('update');
                    Route::delete('/bulk-destroy', [QuickRepliesController::class, 'bulkDestroy'])
                        ->middleware('can:support.delete')
                        ->name('bulk-destroy');
                    Route::delete('/replies/{quickReply}', [QuickRepliesController::class, 'destroy'])
                        ->middleware('can:support.delete')
                        ->name('destroy');
                });

            Route::prefix('chatbot-flows')
                ->name('chatbot-flows.')
                ->controller(ChatbotFlowsController::class)
                ->group(function () {
                    Route::get('/', 'index')->middleware('can:support.view')->name('index');
                    Route::post('/steps', 'storeStep')->middleware('can:support.create')->name('steps.store');
                    Route::put('/steps/{step}', 'updateStep')->middleware('can:support.update')->name('steps.update');
                    Route::delete('/steps/{step}', 'destroyStep')
                        ->middleware('can:support.delete')
                        ->name('steps.destroy');
                    Route::post('/steps/{step}/options', 'storeOption')
                        ->middleware('can:support.create')
                        ->name('options.store');
                    Route::put('/steps/{step}/options/{option}', 'updateOption')
                        ->middleware('can:support.update')
                        ->name('options.update');
                    Route::delete('/steps/{step}/options/{option}', 'destroyOption')
                        ->middleware('can:support.delete')
                        ->name('options.destroy');
                });
        });

    Route::prefix('top-up-cards')
        ->name('top-up-cards.')
        ->group(function () {
            Route::get('/batch', [TopUpCardController::class, 'index'])
                ->middleware('can:top-up-cards.view')
                ->name('batch');
            Route::post('/batch', [TopUpCardController::class, 'store'])
                ->middleware(['can:top-up-cards.create', 'throttle:top-up-card-generation'])
                ->name('store');
            Route::get('/generation-status', [TopUpCardController::class, 'generationStatus'])
                ->middleware('can:top-up-cards.view')
                ->name('generation-status');
            Route::get('/offices', [OfficeController::class, 'index'])
                ->middleware('can:top-up-cards.view')
                ->name('offices');
            Route::get('/card-import', [OfficeController::class, 'cardImport'])
                ->middleware('can:top-up-cards.view')
                ->name('card-import');
            Route::post('/offices', [OfficeController::class, 'store'])
                ->middleware('can:top-up-cards.create')
                ->name('offices.store');
            Route::put('/offices/{office}', [OfficeController::class, 'update'])
                ->middleware('can:top-up-cards.update')
                ->name('offices.update');
            Route::delete('/offices/{office}', [OfficeController::class, 'destroy'])
                ->middleware('can:top-up-cards.delete')
                ->name('offices.destroy');
            Route::post('/cards/import', [OfficeController::class, 'import'])
                ->middleware('can:top-up-cards.create')
                ->name('cards.import');
            Route::patch('/batches/{batch}/void', [OfficeController::class, 'voidBatch'])
                ->middleware('can:top-up-cards.update')
                ->name('batches.void');
            Route::get('/export', [TopUpCardController::class, 'export'])
                ->middleware(['can:top-up-cards.view', 'can:' . AppPermissions::SystemExport])
                ->name('export');
            Route::post('/offices/validate-import', [OfficeController::class, 'validateImport'])
                ->middleware('can:top-up-cards.create')
                ->name('offices.validate-import');
            Route::get('/card-history', [TopUpCardController::class, 'cardHistory'])
                ->middleware('can:top-up-cards.view')
                ->name('card-history');
            Route::get('/redeem-history', [TopUpCardController::class, 'history'])
                ->middleware('can:top-up-cards.view')
                ->name('redeem-history');
            Route::patch('/{topUpCard}/void', [TopUpCardController::class, 'void'])
                ->middleware('can:top-up-cards.update')
                ->name('void');
        });
    Route::prefix('staff')
        ->name('staff.')
        ->group(function () {
            Route::get('/', [StaffController::class, 'index'])
                ->middleware('can:staff.view')
                ->name('index');
            Route::get('/create', [StaffController::class, 'create'])
                ->middleware('can:staff.create')
                ->name('create');
            Route::post('/', [StaffController::class, 'store'])
                ->middleware('can:staff.create')
                ->name('store');
            Route::delete('/bulk-destroy', [StaffController::class, 'bulkDestroy'])
                ->middleware('can:staff.delete')
                ->name('bulk-destroy');
            Route::get('/{admin}/edit', [StaffController::class, 'edit'])
                ->middleware('can:staff.update')
                ->name('edit');
            Route::put('/{admin}', [StaffController::class, 'update'])
                ->middleware('can:staff.update')
                ->name('update');
            Route::delete('/{admin}', [StaffController::class, 'destroy'])
                ->middleware('can:staff.delete')
                ->name('destroy');
            Route::get('/{admin}', [StaffController::class, 'show'])
                ->middleware('can:staff.view')
                ->name('show');
        });

    Route::prefix('roles')
        ->name('roles.')
        ->group(function () {
            Route::get('/', [RoleController::class, 'index'])
                ->middleware('can:roles.view')
                ->name('index');
            Route::get('/create', [RoleController::class, 'create'])
                ->middleware('can:roles.create')
                ->name('create');
            Route::post('/', [RoleController::class, 'store'])
                ->middleware('can:roles.create')
                ->name('store');
            Route::delete('/bulk-destroy', [RoleController::class, 'bulkDestroy'])
                ->middleware('can:roles.delete')
                ->name('bulk-destroy');
            Route::get('/{role}/edit', [RoleController::class, 'edit'])
                ->middleware('can:roles.update')
                ->name('edit');
            Route::put('/{role}', [RoleController::class, 'update'])
                ->middleware('can:roles.update')
                ->name('update');
            Route::delete('/{role}', [RoleController::class, 'destroy'])
                ->middleware('can:roles.delete')
                ->name('destroy');
        });

    Route::prefix('service-requests')
        ->name('service-requests.')
        ->group(function () {
            Route::prefix('/installations')
                ->name('installations.')
                ->group(function () {
                    Route::get('/', [BroadbandApplicationRequestController::class, 'index'])
                        ->middleware('can:service-requests.view')
                        ->name('index');
                    Route::patch('/{installationApplication}/status', [
                        BroadbandApplicationRequestController::class,
                        'updateStatus',
                    ])
                        ->middleware('can:service-requests.update')
                        ->name('status');
                });
            Route::prefix('failures')
                ->name('failures.')
                ->group(function () {
                    Route::get('/', [FailureReportController::class, 'index'])
                        ->middleware('can:service-requests.view')
                        ->name('index');
                    Route::patch('/{failureReport}/status', [FailureReportController::class, 'updateStatus'])
                        ->middleware('can:service-requests.update')
                        ->name('status');
                });
            Route::prefix('change-plan')
                ->name('change-plan.')
                ->group(function () {
                    Route::get('/', [ChangePlanRequestController::class, 'index'])
                        ->middleware('can:service-requests.view')
                        ->name('index');
                    Route::patch('/{changePlanRequest}/status', [ChangePlanRequestController::class, 'updateStatus'])
                        ->middleware('can:service-requests.update')
                        ->name('status');
                });
            Route::prefix('relocations')
                ->name('relocations.')
                ->group(function () {
                    Route::get('/', [RelocationRequestController::class, 'index'])
                        ->middleware('can:service-requests.view')
                        ->name('index');
                    Route::patch('/{relocationRequest}/status', [RelocationRequestController::class, 'updateStatus'])
                        ->middleware('can:service-requests.update')
                        ->name('status');
                });
            Route::prefix('change-password')
                ->name('change-password.')
                ->group(function () {
                    Route::get('/', [ChangePasswordRequestController::class, 'index'])
                        ->middleware('can:service-requests.view')
                        ->name('index');
                    Route::patch('/{changePasswordRequest}/status', [
                        ChangePasswordRequestController::class,
                        'updateStatus',
                    ])
                        ->middleware('can:service-requests.update')
                        ->name('status');
                });
        });

    foreach (MenuPages::all() as $page) {
        Route::get($page['path'], MenuPageController::class)
            ->defaults('titleKey', $page['titleKey'])
            ->middleware('can:' . $page['permission'])
            ->name($page['name']);
    }

    Route::prefix('packages')
        ->name('packages.')
        ->group(function () {
            Route::get('/', [PackageController::class, 'index'])
                ->middleware('can:packages.view')
                ->name('index');
            Route::get('/create', [PackageController::class, 'create'])
                ->middleware('can:packages.create')
                ->name('create');
            Route::post('/', [PackageController::class, 'store'])
                ->middleware('can:packages.create')
                ->name('store');
            Route::delete('/bulk-destroy', [PackageController::class, 'bulkDestroy'])
                ->middleware('can:packages.delete')
                ->name('bulk-destroy');
            Route::get('/{package}/edit', [PackageController::class, 'edit'])
                ->middleware('can:packages.update')
                ->name('edit');
            Route::put('/{package}', [PackageController::class, 'update'])
                ->middleware('can:packages.update')
                ->name('update');
            Route::delete('/{package}', [PackageController::class, 'destroy'])
                ->middleware('can:packages.delete')
                ->name('destroy');
            Route::get('/{package}', [PackageController::class, 'show'])
                ->middleware('can:packages.view')
                ->name('show');
        });

    Route::prefix('networks')
        ->name('networks.')
        ->group(function () {
            Route::post('/', [NetworkController::class, 'store'])
                ->middleware('can:packages.create')
                ->name('store');
            Route::put('/{network}', [NetworkController::class, 'update'])
                ->middleware('can:packages.update')
                ->name('update');
            Route::delete('/bulk-destroy', [NetworkController::class, 'bulkDestroy'])
                ->middleware('can:packages.delete')
                ->name('bulk-destroy');
            Route::delete('/{network}', [NetworkController::class, 'destroy'])
                ->middleware('can:packages.delete')
                ->name('destroy');
        });

    Route::prefix('speeds')
        ->name('speeds.')
        ->group(function () {
            Route::post('/', [SpeedController::class, 'store'])
                ->middleware('can:packages.create')
                ->name('store');
            Route::put('/{speed}', [SpeedController::class, 'update'])
                ->middleware('can:packages.update')
                ->name('update');
            Route::delete('/bulk-destroy', [SpeedController::class, 'bulkDestroy'])
                ->middleware('can:packages.delete')
                ->name('bulk-destroy');
            Route::delete('/{speed}', [SpeedController::class, 'destroy'])
                ->middleware('can:packages.delete')
                ->name('destroy');
        });

    Route::prefix('terms')
        ->name('terms.')
        ->group(function () {
            Route::post('/', [TermController::class, 'store'])
                ->middleware('can:packages.create')
                ->name('store');
            Route::put('/{term}', [TermController::class, 'update'])
                ->middleware('can:packages.update')
                ->name('update');
            Route::delete('/bulk-destroy', [TermController::class, 'bulkDestroy'])
                ->middleware('can:packages.delete')
                ->name('bulk-destroy');
            Route::delete('/{term}', [TermController::class, 'destroy'])
                ->middleware('can:packages.delete')
                ->name('destroy');
        });

    Route::prefix('addons')
        ->name('addons.')
        ->group(function () {
            Route::post('/', [AddonController::class, 'store'])
                ->middleware('can:packages.create')
                ->name('store');
            Route::put('/{addon}', [AddonController::class, 'update'])
                ->middleware('can:packages.update')
                ->name('update');
            Route::delete('/bulk-destroy', [AddonController::class, 'bulkDestroy'])
                ->middleware('can:packages.delete')
                ->name('bulk-destroy');
            Route::delete('/{addon}', [AddonController::class, 'destroy'])
                ->middleware('can:packages.delete')
                ->name('destroy');
        });

    Route::prefix('reports')
        ->name('reports.')
        ->middleware('can:reports.view')
        ->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/customers', [CustomerReportController::class, 'index'])->name('customers');
            Route::get('/billing', [BillingReportController::class, 'index'])->name('reports.billing');
            Route::get('/top-ups', [TopUpReportController::class, 'index'])->name('top-ups');
            Route::get('/eod', [EodReportController::class, 'index'])->name('eod');
            Route::prefix('ledger-health')
                ->name('ledger-health.')
                ->controller(LedgerHealthReportController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/check', 'check')->name('check');
                    Route::post('/daily-scan', 'updateDailyScan')->name('daily-scan');
                });
            Route::get('/service-requests', [ServiceRequestController::class, 'index'])->name(
                'reports.service-requests',
            );
        });

    Route::prefix('settings')
        ->name('settings.')
        ->group(function () {
            Route::get('/app-version', [AppVersionController::class, 'index'])
                ->middleware('can:settings.view')
                ->name('app-version.index');
            Route::post('/app-version', [AppVersionController::class, 'store'])
                ->middleware('can:settings.create')
                ->name('app-version.store');
            Route::put('/app-version/{appVersion}', [AppVersionController::class, 'update'])
                ->middleware('can:settings.update')
                ->name('app-version.update');
            Route::delete('/app-version/bulk-destroy', [AppVersionController::class, 'bulkDestroy'])
                ->middleware('can:settings.delete')
                ->name('app-version.bulk-destroy');
            Route::delete('/app-version/{appVersion}', [AppVersionController::class, 'destroy'])
                ->middleware('can:settings.delete')
                ->name('app-version.destroy');
        });
    Route::prefix('logs')
        ->name('logs.')
        ->group(function () {
            Route::get('/activity', [ActivityLogController::class, 'index'])
                ->middleware('can:activity.view')
                ->name('activity.index');
            Route::get('/activity/export', [ActivityLogController::class, 'export'])
                ->middleware(['can:activity.view', 'can:' . AppPermissions::SystemExport])
                ->name('activity.export');
            Route::get('/security/export', [SecurityLogController::class, 'export'])
                ->middleware(['can:activity.view', 'can:' . AppPermissions::SystemExport])
                ->name('security.export');
            Route::get('/security', [SecurityLogController::class, 'index'])
                ->middleware('can:activity.view')
                ->name('security');
            Route::get('/users', [UserLogController::class, 'index'])
                ->middleware('can:activity.view')
                ->name('users.index');
            Route::get('/users/export', [UserLogController::class, 'export'])
                ->middleware(['can:activity.view', 'can:' . AppPermissions::SystemExport])
                ->name('users.export');
        });
});

Route::fallback(function () {
    return Inertia::render('Errors/NotFound')->toResponse(request())->setStatusCode(404);
});
