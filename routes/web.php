<?php

use App\Http\Controllers\Cms\BannerController;
use App\Http\Controllers\Cms\CategoryController;
use App\Http\Controllers\Cms\ContactController;
use App\Http\Controllers\Cms\GalleryController;
use App\Http\Controllers\Cms\NewsController;
use App\Http\Controllers\Cms\PromotionController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Locale\LocaleController;
use App\Http\Controllers\MenuPage\MenuPageController;
use App\Http\Controllers\Notification\AnnouncementController;
use App\Http\Controllers\Package\AddonController;
use App\Http\Controllers\ServiceRequest\FailureReportController;
use App\Http\Controllers\Package\NetworkController;
use App\Http\Controllers\Package\PackageController;
use App\Http\Controllers\Package\SpeedController;
use App\Http\Controllers\Package\TermController;
use App\Http\Controllers\Region\RegionManagementController;
use App\Http\Controllers\Staff\RoleController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\TopUpCard\TopUpCardController;
use App\Support\AdminHome;
use App\Support\MenuPages;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/locale/{lang}', LocaleController::class)->name('locale.update');

Route::middleware(['auth:web', 'admin.active'])->group(function () {
    Route::get('/', fn () => redirect()->to(AdminHome::path(auth()->user())))->name('home');
    Route::get('/dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
    Route::delete('/dashboard/requests/bulk-destroy', [DashboardController::class, 'bulkDestroy'])
        ->middleware('can:service-requests.delete')
        ->name('dashboard.requests.bulk-destroy');

    Route::get('/customers', [CustomerController::class, 'index'])->middleware('can:customers.view')->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->middleware('can:customers.create')->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('can:customers.create')->name('customers.store');
    Route::delete('/customers/bulk-destroy', [CustomerController::class, 'bulkDestroy'])->middleware('can:customers.delete')->name('customers.bulk-destroy');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->middleware('can:customers.update')->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('can:customers.update')->name('customers.update');
    Route::patch('/customers/{customer}/status', [CustomerController::class, 'updateStatus'])->middleware('can:customers.update')->name('customers.status');
    Route::post('/customers/{customer}/accounts', [CustomerController::class, 'bindAccount'])->middleware('can:customers.update')->name('customers.accounts.bind');
    Route::delete('/customers/{customer}/accounts/{account}', [CustomerController::class, 'unbindAccount'])->middleware('can:customers.update')->name('customers.accounts.unbind');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('can:customers.delete')->name('customers.destroy');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('can:customers.view')->name('customers.show');

    Route::prefix('regions')->middleware('can:regions.view')->group(function () {
        Route::get('/', [RegionManagementController::class, 'index'])->name('regions.index');
        Route::post('/states', [RegionManagementController::class, 'storeState'])->name('regions.states.store');
        Route::put('/states/{state}', [RegionManagementController::class, 'updateState'])->name('regions.states.update');
        Route::delete('/states/{state}', [RegionManagementController::class, 'destroyState'])->name('regions.states.destroy');

        Route::post('/regions', [RegionManagementController::class, 'storeRegion'])->name('regions.regions.store');
        Route::put('/regions/{region}', [RegionManagementController::class, 'updateRegion'])->name('regions.regions.update');
        Route::delete('/regions/{region}', [RegionManagementController::class, 'destroyRegion'])->name('regions.regions.destroy');

        Route::post('/areas', [RegionManagementController::class, 'storeArea'])->name('regions.areas.store');
        Route::put('/areas/{area}', [RegionManagementController::class, 'updateArea'])->name('regions.areas.update');
        Route::delete('/areas/{area}', [RegionManagementController::class, 'destroyArea'])->name('regions.areas.destroy');
    });



    Route::prefix('cms')->name('cms.')->group(function () {
        foreach (
            [
                'promotions' => [PromotionController::class, 'promotion'],
                'banners' => [BannerController::class, 'banner'],
                'categories' => [CategoryController::class, 'category'],
                'news' => [NewsController::class, 'news'],
                'gallery' => [GalleryController::class, 'gallery'],
                'contacts' => [ContactController::class, 'contact'],
            ] as $name => [$controller, $parameter]
        ) {
            Route::get($name, [$controller, 'index'])->middleware('can:cms.view')->name($name . '.index');
            Route::get($name . '/create', [$controller, 'create'])->middleware('can:cms.create')->name($name . '.create');
            Route::post($name, [$controller, 'store'])->middleware('can:cms.create')->name($name . '.store');
            Route::delete($name . '/bulk-destroy', [$controller, 'bulkDestroy'])->middleware('can:cms.delete')->name($name . '.bulk-destroy');
            Route::get($name . '/{' . $parameter . '}/edit', [$controller, 'edit'])->middleware('can:cms.update')->name($name . '.edit');
            Route::put($name . '/{' . $parameter . '}', [$controller, 'update'])->middleware('can:cms.update')->name($name . '.update');
            Route::delete($name . '/{' . $parameter . '}', [$controller, 'destroy'])->middleware('can:cms.delete')->name($name . '.destroy');
        }
    });

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::prefix('announcement')->name('announcement.')->group(function () {
            Route::get('/', [AnnouncementController::class, 'index'])->middleware('can:notifications.view')->name('index');
            Route::get('/create', [AnnouncementController::class, 'create'])->middleware('can:notifications.create')->name('create');
            Route::post('/', [AnnouncementController::class, 'store'])->middleware('can:notifications.create')->name('store');
            Route::delete('/bulk-destroy', [AnnouncementController::class, 'bulkDestroy'])->middleware('can:notifications.delete')->name('bulk-destroy');
            Route::get('/{announcement}/edit', [AnnouncementController::class, 'edit'])->middleware('can:notifications.update')->name('edit');
            Route::put('/{announcement}', [AnnouncementController::class, 'update'])->middleware('can:notifications.update')->name('update');
            Route::delete('/{announcement}', [AnnouncementController::class, 'destroy'])->middleware('can:notifications.delete')->name('destroy');
            Route::get('/{announcement}', [AnnouncementController::class, 'show'])->middleware('can:notifications.view')->name('show');
        });
    });

    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->middleware('can:staff.view')->name('index');
        Route::get('/create', [StaffController::class, 'create'])->middleware('can:staff.create')->name('create');
        Route::post('/', [StaffController::class, 'store'])->middleware('can:staff.create')->name('store');
        Route::delete('/bulk-destroy', [StaffController::class, 'bulkDestroy'])->middleware('can:staff.delete')->name('bulk-destroy');
        Route::get('/{admin}/edit', [StaffController::class, 'edit'])->middleware('can:staff.update')->name('edit');
        Route::put('/{admin}', [StaffController::class, 'update'])->middleware('can:staff.update')->name('update');
        Route::delete('/{admin}', [StaffController::class, 'destroy'])->middleware('can:staff.delete')->name('destroy');
        Route::get('/{admin}', [StaffController::class, 'show'])->middleware('can:staff.view')->name('show');
    });

    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->middleware('can:roles.view')->name('index');
        Route::get('/create', [RoleController::class, 'create'])->middleware('can:roles.create')->name('create');
        Route::post('/', [RoleController::class, 'store'])->middleware('can:roles.create')->name('store');
        Route::delete('/bulk-destroy', [RoleController::class, 'bulkDestroy'])->middleware('can:roles.delete')->name('bulk-destroy');
        Route::get('/{role}/edit', [RoleController::class, 'edit'])->middleware('can:roles.update')->name('edit');
        Route::put('/{role}', [RoleController::class, 'update'])->middleware('can:roles.update')->name('update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->middleware('can:roles.delete')->name('destroy');
    });

    Route::prefix('top-up-cards')->name('top-up-cards.')->group(function () {
        Route::get('/batch', [TopUpCardController::class, 'index'])->middleware('can:top-up-cards.view')->name('batch');
        Route::post('/batch', [TopUpCardController::class, 'store'])->middleware('can:top-up-cards.create')->name('store');
        Route::get('/export', [TopUpCardController::class, 'export'])->middleware('can:top-up-cards.view')->name('export');
        Route::get('/redeem-history', [TopUpCardController::class, 'history'])->middleware('can:top-up-cards.view')->name('redeem-history');
        Route::patch('/{topUpCard}/void', [TopUpCardController::class, 'void'])->middleware('can:top-up-cards.update')->name('void');
    });

    Route::prefix('service-requests')->name('service-requests.')->group(function () {
        Route::prefix('failures')->name('failures.')->group(function () {
            Route::get('/', [FailureReportController::class, 'index'])->middleware('can:service-requests.view')->name('index');
            Route::patch('/{failureReport}/edit', [FailureReportController::class, 'updateStatus'])->middleware('can:service-requests.update')->name('edit');
        });
    });

    foreach (MenuPages::all() as $page) {
        Route::get($page['path'], MenuPageController::class)
            ->defaults('titleKey', $page['titleKey'])
            ->middleware('can:' . $page['permission'])
            ->name($page['name']);
    }

    Route::prefix('packages')->name('packages.')->group(function () {
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

    Route::prefix('networks')->name('networks.')->group(function () {
        Route::post('/', [NetworkController::class, 'store'])->middleware('can:packages.create')->name('store');
        Route::put('/{network}', [NetworkController::class, 'update'])->middleware('can:packages.update')->name('update');
        Route::delete('/{network}', [NetworkController::class, 'destroy'])->middleware('can:packages.delete')->name('destroy');
    });

    Route::prefix('speeds')->name('speeds.')->group(function () {
        Route::post('/', [SpeedController::class, 'store'])->middleware('can:packages.create')->name('store');
        Route::put('/{speed}', [SpeedController::class, 'update'])->middleware('can:packages.update')->name('update');
        Route::delete('/{speed}', [SpeedController::class, 'destroy'])->middleware('can:packages.delete')->name('destroy');
    });

    Route::prefix('terms')->name('terms.')->group(function () {
        Route::post('/', [TermController::class, 'store'])->middleware('can:packages.create')->name('store');
        Route::put('/{term}', [TermController::class, 'update'])->middleware('can:packages.update')->name('update');
        Route::delete('/{term}', [TermController::class, 'destroy'])->middleware('can:packages.delete')->name('destroy');
    });

    Route::prefix('addons')->name('addons.')->group(function () {
        Route::post('/', [AddonController::class, 'store'])->middleware('can:packages.create')->name('store');
        Route::put('/{addon}', [AddonController::class, 'update'])->middleware('can:packages.update')->name('update');
        Route::delete('/{addon}', [AddonController::class, 'destroy'])->middleware('can:packages.delete')->name('destroy');
    });
});

Route::fallback(function () {
    return Inertia::render('Errors/NotFound')
        ->toResponse(request())
        ->setStatusCode(404);
});
