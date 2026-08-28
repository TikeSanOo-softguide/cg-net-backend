<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\NewsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('throttle:60,1')->group(function () {
    Route::prefix('web-app')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{slug}', [CategoryController::class, 'show']);
        Route::get('/news', [NewsController::class, 'index']);
        Route::get('/news/feed', [NewsController::class, 'feed']);
        Route::get('/news/{slug}', [NewsController::class, 'show']);
    });
});
