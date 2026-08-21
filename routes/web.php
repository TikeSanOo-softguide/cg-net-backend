<?php

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
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::patch('/customers/{customer}/status', [CustomerController::class, 'updateStatus'])->name('customers.status');
    Route::post('/customers/{customer}/accounts', [CustomerController::class, 'bindAccount'])->name('customers.accounts.bind');
    Route::delete('/customers/{customer}/accounts/{account}', [CustomerController::class, 'unbindAccount'])->name('customers.accounts.unbind');

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
