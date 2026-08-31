<?php

use App\Http\Controllers\Api\Banner\BannerController;
use App\Http\Controllers\Api\Category\CategoryController;
use App\Http\Controllers\Api\Contact\ContactController;
use App\Http\Controllers\Api\News\NewsController;
use App\Http\Controllers\Api\Gallery\GalleryController;
use App\Http\Controllers\Api\Promotion\PromotionController;
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
        Route::get('/banners', [BannerController::class, 'show']);
        Route::get('/contacts', [ContactController::class, 'show']);
        Route::get('/gallery', [GalleryController::class, 'index']);
        Route::get('/promotions', [PromotionController::class, 'index']);
        Route::get('/promotions/{slug}', [PromotionController::class, 'show']);
    });
});
