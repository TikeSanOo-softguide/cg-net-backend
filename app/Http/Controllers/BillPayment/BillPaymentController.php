<?php

namespace App\Http\Controllers\BillPayment;

use App\Enums\ActivityLogEvent;
use App\Enums\ActivityLogName;
use App\Enums\BillPaymentStatus;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Exceptions\InternetBillPaymentReviewConflict;
use App\Exports\BillPaymentExport;
use App\Http\Controllers\Controller;
use App\Models\BillPayment;
use App\Services\AdminNotification\AdminNotificationReadStatus;
use App\Services\InternetBill\InternetBillPaymentService;
use App\Support\NavigationStack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class BillPaymentController extends Controller
{
    public function index(Request $request, AdminNotificationReadStatus $readStatus): Response
    {
        $filters = $this->filters($request);
        $payments = $this->query($filters)->paginate(15)->withQueryString();
        $unreadPaymentIds = $readStatus->unreadReferenceIds(
            (int) $request->user()->getAuthIdentifier(),
            'bill_payment',
            $payments->getCollection()->pluck('id')->all(),
        );
        $payments->through(function (BillPayment $payment) use ($unreadPaymentIds): array {
            $transaction = $payment->ledgerTransaction;
            $customer = $transaction?->wallet?->user;

            return [
                'id' => $payment->id,
                'notification_unread' => in_array($payment->id, $unreadPaymentIds, true),
                'ledger_transaction_id' => $payment->ledger_transaction_id,
                'transaction_no' => $transaction?->transaction_no,
                'amount' => $transaction?->amount,
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->name,
                'customer_phone' => $customer?->phone,
                'broadband_account_number' => $payment->broadband_account_number,
                'order_no' => $payment->order_no,
                'service_type' => $payment->service_type,
                'product_id' => $payment->product_id,
                'product_price' => $payment->product_price,
                'snapshot' => $payment->snapshot,
                'remark' => $payment->remark,
                'status' => $payment->status->value,
                'external_response' => $payment->external_response,
                'created_at' => $payment->created_at?->toIso8601String(),
                'confirmed_at' => $payment->confirmed_at?->toIso8601String(),
            ];
        });

        $props = [
            'payments' => $payments,
            'filters' => $filters,
            'statuses' => array_column(BillPaymentStatus::cases(), 'value'),
        ];

        if ($request->query->has('return_to')) {
            $returnTo = $request->query('return_to');
            $props['return_to'] = is_string($returnTo) ? NavigationStack::sanitize($returnTo) : null;
        }

        return Inertia::render('BillPayment/BillPayment/Index', $props);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);

        return Excel::download(new BillPaymentExport($this->query($filters)), 'bill-payments.xlsx');
    }

    public function resolveReview(
        Request $request,
        BillPayment $billPayment,
        InternetBillPaymentService $payments,
    ): RedirectResponse {
        $transaction = $billPayment->ledgerTransaction;
        if ($transaction === null) {
            return back()->with('error', 'transactions.review_unavailable');
        }

        try {
            $payments->resolveReviewSuccessfully($transaction, (int) $request->user()->getAuthIdentifier());
        } catch (InternetBillPaymentReviewConflict) {
            return back()->with('error', 'transactions.review_unavailable');
        }

        activity(ActivityLogName::BillPayments->value)
            ->causedBy($request->user())
            ->performedOn($billPayment)
            ->event(ActivityLogEvent::Completed->value)
            ->withProperties([
                'from_status' => LedgerTransactionStatus::Review->value,
                'to_status' => LedgerTransactionStatus::Completed->value,
            ])
            ->log('bill_payment_completed');

        return back()->with('success', 'transactions.review_completed');
    }

    public function refundReview(
        Request $request,
        BillPayment $billPayment,
        InternetBillPaymentService $payments,
    ): RedirectResponse {
        $transaction = $billPayment->ledgerTransaction;
        $user = $transaction?->wallet?->user;
        if ($transaction === null || $user === null) {
            return back()->with('error', 'transactions.review_unavailable');
        }

        try {
            $payments->refundReviewPayment($transaction, $user, (int) $request->user()->getAuthIdentifier());
        } catch (InternetBillPaymentReviewConflict) {
            return back()->with('error', 'transactions.review_unavailable');
        }

        activity(ActivityLogName::BillPayments->value)
            ->causedBy($request->user())
            ->performedOn($billPayment)
            ->event(ActivityLogEvent::Refunded->value)
            ->withProperties([
                'from_status' => LedgerTransactionStatus::Review->value,
                'to_status' => LedgerTransactionStatus::Failed->value,
            ])
            ->log('bill_payment_refunded');

        return back()->with('success', 'transactions.review_refunded');
    }

    /** @return array{search: string, status: string, service_type: string, payment_id: int, from: string, to: string} */
    private function filters(Request $request): array
    {
        $serviceType = $request->string('service_type')->toString();

        return [
            'search' => trim($request->string('search')->toString()),
            'status' => $request->string('status')->toString(),
            'service_type' => in_array($serviceType, ['FTTH', 'CG'], true) ? $serviceType : '',
            'payment_id' => max(0, $request->integer('payment_id')),
            'from' => $request->query('from') === '' ? '' : $request->date('from')?->toDateString() ?? '',
            'to' => $request->query('to') === '' ? '' : $request->date('to')?->toDateString() ?? '',
        ];
    }

    /** @param array{search: string, status: string, service_type: string, payment_id: int, from: string, to: string} $filters */
    private function query(array $filters): Builder
    {
        return BillPayment::query()
            ->with(['ledgerTransaction.wallet.user:id,name,phone'])
            ->whereHas('ledgerTransaction', function (Builder $query): void {
                $query->whereIn('type', LedgerTransactionType::internetBillValues());
            })
            ->when(
                in_array($filters['status'], array_column(BillPaymentStatus::cases(), 'value'), true),
                fn(Builder $query) => $query->where('status', $filters['status']),
            )
            ->when(
                $filters['service_type'] !== '',
                fn(Builder $query) => $query->where('service_type', $filters['service_type']),
            )
            ->when($filters['payment_id'] > 0, fn(Builder $query) => $query->whereKey($filters['payment_id']))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $search = $filters['search'];
                    $query
                        ->whereLike('broadband_account_number', '%' . $search . '%')
                        ->orWhereLike('order_no', '%' . $search . '%')
                        ->orWhereLike('product_id', '%' . $search . '%')
                        ->orWhereHas('ledgerTransaction', function (Builder $transaction) use ($search): void {
                            $transaction
                                ->whereLike('transaction_no', '%' . $search . '%')
                                ->orWhereHas('wallet.user', function (Builder $user) use ($search): void {
                                    $user
                                        ->whereLike('name', '%' . $search . '%')
                                        ->orWhereLike('phone', '%' . $search . '%');
                                });
                        });
                });
            })
            ->when(
                $filters['from'] !== '',
                fn(Builder $query) => $query->whereDate('created_at', '>=', $filters['from']),
            )
            ->when($filters['to'] !== '', fn(Builder $query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->latest('created_at');
    }
}
