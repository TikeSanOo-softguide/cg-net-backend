<?php

use App\Http\Controllers\Cms\BannerController;
use App\Http\Controllers\Cms\CategoryController;
use App\Http\Controllers\Cms\ContactController;
use App\Http\Controllers\Cms\GalleryController;
use App\Http\Controllers\Cms\NewsController;
use App\Http\Controllers\Cms\PromotionController;
use App\Http\Controllers\Cms\TagController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Locale\LocaleController;
use App\Http\Controllers\MenuPage\MenuPageController;
use App\Support\MenuPages;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/locale/{lang}', LocaleController::class)->name('locale.update');

Route::middleware('auth:web')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'))->name('home');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::patch('/customers/{customer}/status', [CustomerController::class, 'updateStatus'])->name('customers.status');
    Route::post('/customers/{customer}/accounts', [CustomerController::class, 'bindAccount'])->name('customers.accounts.bind');
    Route::delete('/customers/{customer}/accounts/{account}', [CustomerController::class, 'unbindAccount'])->name('customers.accounts.unbind');

    Route::prefix('cms')->name('cms.')->group(function () {
        Route::resource('promotions', PromotionController::class)->except(['show']);
        Route::resource('banners', BannerController::class)->except(['show']);
        Route::resource('tags', TagController::class)->except(['show']);
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('news', NewsController::class)->except(['show'])->parameters(['news' => 'news']);
        Route::resource('gallery', GalleryController::class)->except(['show'])->parameters(['gallery' => 'gallery']);
        Route::resource('contacts', ContactController::class)->except(['show']);
    });

    foreach (MenuPages::all() as $page) {
        Route::get($page['path'], MenuPageController::class)
            ->defaults('titleKey', $page['titleKey'])
            ->name($page['name']);
    }
});

Route::fallback(function () {
    return Inertia::render('Errors/NotFound')
        ->toResponse(request())
        ->setStatusCode(404);
});
