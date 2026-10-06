<?php

namespace App\Http\Controllers\BillPayment;

use App\Enums\BillPaymentStatus;
use App\Enums\LedgerTransactionType;
use App\Exports\BillPaymentExport;
use App\Http\Controllers\Controller;
use App\Models\BillPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class BillPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $payments = $this->query($filters)
            ->paginate(15)
            ->withQueryString()
            ->through(function (BillPayment $payment): array {
                $transaction = $payment->ledgerTransaction;
                $customer = $transaction?->wallet?->user;

                return [
                    'id' => $payment->id,
                    'ledger_transaction_id' => $payment->ledger_transaction_id,
                    'transaction_no' => $transaction?->transaction_no,
                    'amount' => $transaction?->amount,
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->name,
                    'customer_phone' => $customer?->phone,
                    'broadband_account_number' => $payment->broadband_account_number,
                    'status' => $payment->status->value,
                    'external_bill_ref' => $payment->external_bill_ref,
                    'external_payment_ref' => $payment->external_payment_ref,
                    'external_response' => $payment->external_response,
                    'created_at' => $payment->getRawOriginal('created_at'),
                    'confirmed_at' => $payment->confirmed_at?->toIso8601String(),
                ];
            });

        return Inertia::render('BillPayment/BillPayment/Index', [
            'payments' => $payments,
            'filters' => $filters,
            'statuses' => array_column(BillPaymentStatus::cases(), 'value'),
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);

        return Excel::download(new BillPaymentExport($this->query($filters)), 'bill-payments.xlsx');
    }

    /** @return array{search: string, status: string, payment_id: int, from: string, to: string} */
    private function filters(Request $request): array
    {
        return [
            'search' => trim($request->string('search')->toString()),
            'status' => $request->string('status')->toString(),
            'payment_id' => max(0, $request->integer('payment_id')),
            'from' => $request->query('from') === '' ? '' : $request->date('from')?->toDateString() ?? '',
            'to' => $request->query('to') === '' ? '' : $request->date('to')?->toDateString() ?? '',
        ];
    }

    /** @param array{search: string, status: string, payment_id: int, from: string, to: string} $filters */
    private function query(array $filters): Builder
    {
        return BillPayment::query()
            ->with(['ledgerTransaction.wallet.user:id,name,phone'])
            ->whereHas('ledgerTransaction', function (Builder $query): void {
                $query->where('type', LedgerTransactionType::FtthBill->value);
            })
            ->when(
                in_array($filters['status'], array_column(BillPaymentStatus::cases(), 'value'), true),
                fn(Builder $query) => $query->where('status', $filters['status']),
            )
            ->when($filters['payment_id'] > 0, fn(Builder $query) => $query->whereKey($filters['payment_id']))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $search = $filters['search'];
                    $query
                        ->whereLike('broadband_account_number', '%' . $search . '%')
                        ->orWhereLike('external_bill_ref', '%' . $search . '%')
                        ->orWhereLike('external_payment_ref', '%' . $search . '%')
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
