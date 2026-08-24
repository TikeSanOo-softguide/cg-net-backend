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
use App\Http\Controllers\Staff\RoleController;
use App\Http\Controllers\Staff\StaffController;
use App\Support\MenuPages;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/locale/{lang}', LocaleController::class)->name('locale.update');

Route::middleware('auth:web')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'))->name('home');
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

    Route::prefix('cms')->name('cms.')->group(function () {
        foreach ([
            'promotions' => [PromotionController::class, 'promotion'],
            'banners' => [BannerController::class, 'banner'],
            'categories' => [CategoryController::class, 'category'],
            'news' => [NewsController::class, 'news'],
            'gallery' => [GalleryController::class, 'gallery'],
            'contacts' => [ContactController::class, 'contact'],
        ] as $name => [$controller, $parameter]) {
            Route::get($name, [$controller, 'index'])->middleware('can:cms.view')->name($name.'.index');
            Route::get($name.'/create', [$controller, 'create'])->middleware('can:cms.create')->name($name.'.create');
            Route::post($name, [$controller, 'store'])->middleware('can:cms.create')->name($name.'.store');
            Route::delete($name.'/bulk-destroy', [$controller, 'bulkDestroy'])->middleware('can:cms.delete')->name($name.'.bulk-destroy');
            Route::get($name.'/{'.$parameter.'}/edit', [$controller, 'edit'])->middleware('can:cms.update')->name($name.'.edit');
            Route::put($name.'/{'.$parameter.'}', [$controller, 'update'])->middleware('can:cms.update')->name($name.'.update');
            Route::delete($name.'/{'.$parameter.'}', [$controller, 'destroy'])->middleware('can:cms.delete')->name($name.'.destroy');
        }
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

    foreach (MenuPages::all() as $page) {
        Route::get($page['path'], MenuPageController::class)
            ->defaults('titleKey', $page['titleKey'])
            ->middleware('can:'.$page['permission'])
            ->name($page['name']);
    }
});

Route::fallback(function () {
    return Inertia::render('Errors/NotFound')
        ->toResponse(request())
        ->setStatusCode(404);
});
