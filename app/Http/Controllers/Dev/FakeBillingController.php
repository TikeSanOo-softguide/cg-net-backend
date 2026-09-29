<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Local-only stand-in for the external billing server. The broadband account number picks the scenario:
 *
 *  - NOBILL…       bill lookup fails (404)
 *  - BIG…          bill amount larger than any wallet (insufficient balance)
 *  - REJECT…       extend-plan rejected (422) → wallet refunded
 *  - PENDING-OK…   extend-plan times out (504), reconcile later finds it completed
 *  - PENDING-FAIL… extend-plan times out (504), reconcile later finds it failed → refund
 *  - PENDING-LOST… extend-plan times out (504), reconcile gets 404 → refund
 *  - PENDING…      extend-plan times out (504), status stays unknown → timeout refund after max age
 *  - anything else succeeds
 */
class FakeBillingController extends Controller
{
    public const DEFAULT_AMOUNT = 25000;

    public function billDetails(Request $request): JsonResponse
    {
        $account = strtoupper((string) $request->query('account_number'));

        if (str_starts_with($account, 'NOBILL')) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        return response()->json([
            'account_number' => $account,
            'amount' => str_starts_with($account, 'BIG') ? 999_999_999 : self::DEFAULT_AMOUNT,
        ]);
    }

    public function extendPlan(Request $request): JsonResponse
    {
        $account = strtoupper((string) $request->input('account_number'));
        $paymentRef = (string) $request->input('payment_ref');

        Cache::put($this->cacheKey($paymentRef), $account, now()->addDay());

        if (str_starts_with($account, 'REJECT')) {
            return response()->json(['success' => false, 'message' => 'Fake billing rejected the payment.'], 422);
        }

        if (str_starts_with($account, 'PENDING')) {
            return response()->json(['message' => 'Fake billing gateway timeout.'], 504);
        }

        return response()->json([
            'success' => true,
            'billing_ref' => 'FAKE-BILL-'.Str::upper(Str::random(8)),
            'payment_ref' => $paymentRef,
        ]);
    }

    public function paymentStatus(Request $request): JsonResponse
    {
        $paymentRef = (string) $request->query('payment_ref');
        $account = Cache::get($this->cacheKey($paymentRef));

        if ($account === null || str_starts_with($account, 'PENDING-LOST')) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        return response()->json(match (true) {
            str_starts_with($account, 'PENDING-OK') => [
                'status' => 'completed',
                'billing_ref' => 'FAKE-BILL-'.Str::upper(Str::random(8)),
                'payment_ref' => $paymentRef,
            ],
            str_starts_with($account, 'PENDING-FAIL') => ['status' => 'failed', 'message' => 'Fake billing marked the payment failed.'],
            str_starts_with($account, 'PENDING') => ['status' => 'processing'],
            str_starts_with($account, 'REJECT') => ['status' => 'failed'],
            default => ['status' => 'completed', 'payment_ref' => $paymentRef],
        });
    }

    private function cacheKey(string $paymentRef): string
    {
        return 'fake-billing:payment:'.$paymentRef;
    }
}
