<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\OtpController;
use App\Http\Controllers\Api\Auth\RegistrationController;
use App\Http\Controllers\Api\Banner\BannerController;
use App\Http\Controllers\Api\BroadbandAccount\BroadbandAccountController;
use App\Http\Controllers\Api\Category\CategoryController;
use App\Http\Controllers\Api\ChatFlow\ChatFlowController;
use App\Http\Controllers\Api\Contact\ContactController;
use App\Http\Controllers\Api\Customer\ProfileController;
use App\Http\Controllers\Api\DeviceToken\DeviceTokenController;
use App\Http\Controllers\Api\FtthBill\FtthBillController;
use App\Http\Controllers\Api\Gallery\GalleryController;
use App\Http\Controllers\Api\News\NewsController;
use App\Http\Controllers\Api\Notification\AnnouncementController;
use App\Http\Controllers\Api\Notification\InboxController;
use App\Http\Controllers\Api\Package\AddonController;
use App\Http\Controllers\Api\Package\BuyPackageController;
use App\Http\Controllers\Api\Package\NetworkController;
use App\Http\Controllers\Api\Package\PackageController;
use App\Http\Controllers\Api\Package\SpeedController;
use App\Http\Controllers\Api\Package\TermController;
use App\Http\Controllers\Api\Promotion\PromotionController;
use App\Http\Controllers\Api\Redeem\RedeemController;
use App\Http\Controllers\Api\Region\RegionController;
use App\Http\Controllers\Api\Service\ServiceController;
use App\Http\Controllers\Api\ServiceRequest\BroadbandApplicationRequestController;
use App\Http\Controllers\Api\ServiceRequest\ChangePasswordRequestController;
use App\Http\Controllers\Api\ServiceRequest\ChangePlanRequestController;
use App\Http\Controllers\Api\ServiceRequest\FailureReportController;
use App\Http\Controllers\Api\ServiceRequest\RelocationRequestController;
use App\Http\Controllers\Api\Settings\AppVersionController;
use App\Http\Controllers\Api\Transaction\TransactionHistoryController;
use App\Http\Controllers\ChangePassword\ChangePasswordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')
    ->middleware('throttle:60,1')
    ->group(function () {
        Route::post('/otp/request', [OtpController::class, 'request']);
        Route::post('/otp/verify', [OtpController::class, 'verify']);
        Route::post('/login', LoginController::class)->middleware('throttle:30,1');
        Route::post('/register', RegistrationController::class)->middleware('throttle:30,1');
    });

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('throttle:60,1')->group(function () {
    Route::prefix('web-app')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{slug}', [CategoryController::class, 'show']);
        Route::get('/services', [ServiceController::class, 'index']);
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
    Route::get('/inbox', [InboxController::class, 'index']);
    Route::get('/app-version', [AppVersionController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/auth/logout', [LoginController::class, 'logout']);

    Route::prefix('customer')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::get('/transactions', TransactionHistoryController::class);
    });

    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

    Route::prefix('broadband-account')->group(function () {
        Route::post('/connect', [BroadbandAccountController::class, 'connect']);
    });

    Route::prefix('redeem')->group(function () {
        Route::post('/check-serial-no', [RedeemController::class, 'checkSerialNo'])->middleware(
            'throttle:serial-check',
        );
        Route::post('/top-up-account', [RedeemController::class, 'topUpAccount']);
    });

    Route::post('/packages/buy', [BuyPackageController::class, 'buy']);

    Route::prefix('ftth-bills')->group(function () {
        Route::post('/pay', [FtthBillController::class, 'pay']);
        Route::get('/pending-slip', [FtthBillController::class, 'pendingSlip']);
        Route::get('/paid-slips', [FtthBillController::class, 'paidSlips']);
    });

    Route::prefix('redeem')->group(function () {
        Route::post('/check-serial-no', [RedeemController::class, 'checkSerialNo'])->middleware(
            'throttle:serial-check',
        );
        Route::post('/top-up-account', [RedeemController::class, 'topUpAccount']);
    });

    Route::prefix('relocation-requests')->group(function () {
        Route::get('/', [RelocationRequestController::class, 'index']);
        Route::post('/create', [RelocationRequestController::class, 'store']);
        Route::put('/{relocationRequest}', [RelocationRequestController::class, 'update']);
        Route::patch('/{relocationRequest}/cancel', [RelocationRequestController::class, 'cancel']);
        Route::delete('/{relocationRequest}', [RelocationRequestController::class, 'destroy']);
    });

    Route::prefix('failure-reports')->group(function () {
        Route::get('/', [FailureReportController::class, 'index']);
        Route::post('/create', [FailureReportController::class, 'store']);
        Route::get('/{failureReport}', [FailureReportController::class, 'show']);
        Route::put('/{failureReport}', [FailureReportController::class, 'update']);
        Route::patch('/{failureReport}/cancel', [FailureReportController::class, 'cancel']);
        Route::delete('/{failureReport}', [FailureReportController::class, 'destroy']);
    });

    Route::prefix('change-plan-requests')->group(function () {
        Route::get('/', [ChangePlanRequestController::class, 'index']);
        Route::post('/create', [ChangePlanRequestController::class, 'store']);
        Route::get('/{changePlanRequest}', [ChangePlanRequestController::class, 'show']);
        Route::put('/{changePlanRequest}', [ChangePlanRequestController::class, 'update']);
        Route::delete('/{changePlanRequest}', [ChangePlanRequestController::class, 'destroy']);
    });

    Route::prefix('change-password-requests')->group(function () {
        Route::get('/', [ChangePasswordRequestController::class, 'index']);
        Route::post('/create', [ChangePasswordRequestController::class, 'store']);
        Route::get('/{changePasswordRequest}', [ChangePasswordRequestController::class, 'show']);
        Route::put('/{changePasswordRequest}', [ChangePasswordRequestController::class, 'update']);
        Route::delete('/{changePasswordRequest}', [ChangePasswordRequestController::class, 'destroy']);
    });

    Route::prefix('broadband-applications')->group(function () {
        Route::get('/', [BroadbandApplicationRequestController::class, 'index']);
        Route::post('/create', [BroadbandApplicationRequestController::class, 'store']);
        Route::get('/{installationApplication}', [BroadbandApplicationRequestController::class, 'show']);
        Route::put('/{installationApplication}', [BroadbandApplicationRequestController::class, 'update']);
        Route::patch('/{installationApplication}/cancel', [BroadbandApplicationRequestController::class, 'cancel']);
        Route::delete('/{installationApplication}', [BroadbandApplicationRequestController::class, 'destroy']);
    });

    Route::prefix('change-password')->group(function () {
        Route::post('/', [ChangePasswordController::class, 'store']);
        Route::post('/verify-otp', [ChangePasswordController::class, 'verifyOtp']);
    });

    Route::prefix('chat-flow')->group(function () {
        Route::post('/start', [ChatFlowController::class, 'start']);
        Route::get('/current', [ChatFlowController::class, 'current']);
        Route::post('/select-option', [ChatFlowController::class, 'selectOption']);
    });
});

if (app()->isLocal()) {
    require __DIR__ . '/dev.php';
}
