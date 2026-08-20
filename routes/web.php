<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MenuPageController;
use App\Support\MenuPages;
use Illuminate\Support\Facades\Route;

Route::post('/locale/{lang}', LocaleController::class)->name('locale.update');

Route::middleware('auth:web')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'))->name('home');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    foreach (MenuPages::all() as $page) {
        Route::get($page['path'], MenuPageController::class)
            ->defaults('titleKey', $page['titleKey'])
            ->name($page['name']);
    }
});
