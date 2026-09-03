<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Banner\BannerController;
use App\Http\Controllers\Api\Category\CategoryController;
use App\Http\Controllers\Api\Contact\ContactController;
use App\Http\Controllers\Api\Gallery\GalleryController;
use App\Http\Controllers\Api\News\NewsController;
use App\Http\Controllers\Api\Notification\AnnouncementController;
use App\Http\Controllers\Api\Package\AddonController;
use App\Http\Controllers\Api\Package\NetworkController;
use App\Http\Controllers\Api\Package\PackageController;
use App\Http\Controllers\Api\Package\SpeedController;
use App\Http\Controllers\Api\Package\TermController;
use App\Http\Controllers\Api\Promotion\PromotionController;
use App\Http\Controllers\Api\Region\RegionController;
use App\Http\Controllers\Api\Requests\RelocationRequestController;
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
        Route::get('/packages', [PackageController::class, 'index']);
        Route::get('/packages/recommended', [PackageController::class, 'recommended']);
        Route::get('/packages/{id}', [PackageController::class, 'show']);
        Route::get('/networks', [NetworkController::class, 'index']);
        Route::get('/speeds', [SpeedController::class, 'index']);
        Route::get('/terms', [TermController::class, 'index']);
        Route::get('/addons', [AddonController::class, 'index']);
    });

    Route::get('/locations', [RegionController::class, 'locations']);
    Route::get('/states', [RegionController::class, 'states']);
    Route::get('/states/{stateId}/regions', [RegionController::class, 'regions']);
    Route::get('/regions/{regionId}/areas', [RegionController::class, 'areas']);
    Route::get('/announcements', [AnnouncementController::class, 'index']);
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('relocation-requests')->group(function () {
        Route::get('/', [RelocationRequestController::class, 'index']);
        Route::post('/create', [RelocationRequestController::class, 'store']);
        Route::put('/{relocationRequest}', [RelocationRequestController::class, 'update']);
        Route::delete('/{relocationRequest}', [RelocationRequestController::class, 'destroy']);
    });
});
