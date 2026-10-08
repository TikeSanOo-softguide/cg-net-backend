<?php

namespace App\Http\Controllers\Dev;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
    public const DEFAULT_AMOUNT = 250;

    public function billDetails(Request $request): JsonResponse
    {
        $account = strtoupper((string) $request->query('account_number'));

        if (str_starts_with($account, 'NOBILL')) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $billMonth = $this->nextBillMonth($account);

        return response()->json([
            'account_number' => $account,
            'amount' => str_starts_with($account, 'BIG') ? 999_999_999 : self::DEFAULT_AMOUNT,
            'bill_month' => $billMonth,
            'bill_month_label' => Carbon::createFromFormat('!Y-m', $billMonth)->format('F Y'),
            'due_date' => now()->addDays(3)->toDateString(),
            'paid_slips' => Cache::get($this->paidSlipsCacheKey($account), []),
        ]);
    }

    public function dueBills(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'due_date_from' => ['required', 'date_format:Y-m-d'],
            'due_date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:due_date_from'],
        ]);
        $dueDate = now()->addDays(3)->toDateString();

        if ($dueDate < $filters['due_date_from'] || $dueDate > $filters['due_date_to']) {
            return response()->json(['data' => []]);
        }

        $accounts = User::query()
            ->where('status', UserStatus::Active)
            ->whereNotNull('broadband_account_number')
            ->where('broadband_account_number', '!=', '')
            ->distinct()
            ->pluck('broadband_account_number');

        $bills = $accounts
            ->reject(fn (string $account): bool => str_starts_with(strtoupper($account), 'NOBILL'))
            ->map(fn (string $account): array => [
                'account_number' => $account,
                'bill_id' => $account.':'.$this->nextBillMonth($account),
                'bill_month' => $this->nextBillMonth($account),
                'due_date' => $dueDate,
                'amount' => str_starts_with(strtoupper($account), 'BIG') ? 999_999_999 : self::DEFAULT_AMOUNT,
            ])
            ->values();

        return response()->json(['data' => $bills]);
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

        $billingRef = $this->advanceBillMonth($account, $paymentRef);

        return response()->json([
            'success' => true,
            'billing_ref' => $billingRef,
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

        $billingRef = str_starts_with($account, 'PENDING-OK')
            ? $this->advanceBillMonth($account, $paymentRef)
            : 'FAKE-BILL-' . Str::upper(Str::random(8));

        return response()->json(
            match (true) {
                str_starts_with($account, 'PENDING-OK') => [
                    'status' => 'completed',
                    'billing_ref' => $billingRef,
                    'payment_ref' => $paymentRef,
                ],
                str_starts_with($account, 'PENDING-FAIL') => [
                    'status' => 'failed',
                    'message' => 'Fake billing marked the payment failed.',
                ],
                str_starts_with($account, 'PENDING') => ['status' => 'processing'],
                str_starts_with($account, 'REJECT') => ['status' => 'failed'],
                default => ['status' => 'completed', 'payment_ref' => $paymentRef],
            },
        );
    }

    private function cacheKey(string $paymentRef): string
    {
        return 'fake-billing:payment:' . $paymentRef;
    }

    private function nextBillMonth(string $account): string
    {
        return Cache::rememberForever(
            $this->billMonthCacheKey($account),
            fn() => now()->startOfMonth()->format('Y-m'),
        );
    }

    private function advanceBillMonth(string $account, string $paymentRef): string
    {
        $billingRef = Cache::rememberForever(
            'fake-billing:billing-ref:' . $paymentRef,
            fn() => 'FAKE-BILL-' . Str::upper(Str::random(8)),
        );

        if (!Cache::add('fake-billing:advanced:' . $paymentRef, true, now()->addYear())) {
            return $billingRef;
        }

        $paidMonth = $this->nextBillMonth($account);
        $paidSlips = Cache::get($this->paidSlipsCacheKey($account), []);
        $paidSlips[] = [
            'account_number' => $account,
            'bill_month' => $paidMonth,
            'bill_month_label' => Carbon::createFromFormat('!Y-m', $paidMonth)->format('F Y'),
            'amount' => str_starts_with($account, 'BIG') ? 999_999_999 : self::DEFAULT_AMOUNT,
            'billing_ref' => $billingRef,
            'payment_ref' => $paymentRef,
            'paid_at' => now()->toIso8601String(),
        ];
        Cache::forever($this->paidSlipsCacheKey($account), $paidSlips);

        $nextBillMonth = Carbon::createFromFormat('!Y-m', $paidMonth)
            ->addMonthNoOverflow()
            ->format('Y-m');

        Cache::forever($this->billMonthCacheKey($account), $nextBillMonth);

        return $billingRef;
    }

    private function billMonthCacheKey(string $account): string
    {
        return 'fake-billing:next-bill-month:' . $account;
    }

    private function paidSlipsCacheKey(string $account): string
    {
        return 'fake-billing:paid-slips:' . $account;
    }
}
