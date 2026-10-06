<?php

use App\Http\Controllers\Dev\FakeBillingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('dev/fake-billing')->group(function () {
    Route::get('/bill-details', [FakeBillingController::class, 'billDetails']);
    Route::post('/extend-plan', [FakeBillingController::class, 'extendPlan']);
    Route::get('/payment-status', [FakeBillingController::class, 'paymentStatus']);
});

Route::get('/dev/wallet', function (Request $request) {
    $wallet = $request->user()->wallet;

    return response()->json([
        'balance' => $wallet?->balance,
        'transactions' => $wallet?->transactions()
            ->latest('id')
            ->limit(10)
            ->get(['id', 'transaction_no', 'type', 'status', 'amount', 'reversal_of', 'created_at']),
    ]);
})->middleware('auth:sanctum');
