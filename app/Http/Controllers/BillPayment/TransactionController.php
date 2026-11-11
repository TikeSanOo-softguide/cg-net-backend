<?php

namespace App\Http\Controllers\BillPayment;

use App\Exceptions\InternetBillPaymentReviewConflict;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletActorType;
use App\Exports\TransactionExport;
use App\Http\Controllers\Controller;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\InternetBill\InternetBillPaymentService;
use App\Services\Transaction\TransactionService;
use App\Support\NavigationStack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class TransactionController extends Controller
{
    public function index(Request $request, TransactionService $transactions): Response
    {
        $filters = $transactions->filters($request);

        $props = [
            'transactions' => $transactions->paginate($filters),
            'filters' => $filters,
            'filterOptions' => [
                'actor_types' => array_column(WalletActorType::cases(), 'value'),
                'types' => array_column(LedgerTransactionType::cases(), 'value'),
                'statuses' => array_column(LedgerTransactionStatus::cases(), 'value'),
            ],
            'scope' => $filters['customer_id'] ? 'customer' : 'global',
        ];

        if ($request->query->has('return_to')) {
            $returnTo = $request->query('return_to');
            $props['return_to'] = is_string($returnTo) ? NavigationStack::sanitize($returnTo) : null;
        }

        return Inertia::render('BillPayment/Transactions/Index', $props);
    }

    public function export(Request $request, TransactionService $transactions)
    {
        return $this->download($transactions, $transactions->filters($request));
    }

    public function exportCustomer(Request $request, User $customer, TransactionService $transactions)
    {
        $filters = $transactions->filters($request);
        $filters['customer_id'] = $customer->id;

        return $this->download($transactions, $filters, 'customer-transactions.xlsx');
    }

    public function resolveReview(
        Request $request,
        LedgerTransaction $transaction,
        InternetBillPaymentService $payments,
    ): RedirectResponse {
        try {
            $payments->resolveReviewSuccessfully($transaction, (int) $request->user()->getAuthIdentifier());
        } catch (InternetBillPaymentReviewConflict) {
            return back()->with('error', 'transactions.review_unavailable');
        }

        return back()->with('success', 'transactions.review_completed');
    }

    public function refundReview(
        Request $request,
        LedgerTransaction $transaction,
        InternetBillPaymentService $payments,
    ): RedirectResponse {
        try {
            $payments->refundReviewPayment(
                $transaction,
                $transaction->wallet->user,
                (int) $request->user()->getAuthIdentifier(),
            );
        } catch (InternetBillPaymentReviewConflict) {
            return back()->with('error', 'transactions.review_unavailable');
        }

        return back()->with('success', 'transactions.review_refunded');
    }

    private function download(TransactionService $transactions, array $filters, ?string $filename = null)
    {
        return Excel::download(
            new TransactionExport($transactions->query($filters)),
            $filename ?? 'transactions-' . now()->format('Ymd-His') . '.xlsx',
        );
    }
}
