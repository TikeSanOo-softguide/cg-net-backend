<?php

namespace App\Exports;

use App\Models\BillPayment;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BillPaymentExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Transaction No.',
            'Customer',
            'Customer Phone',
            'Broadband Account',
            'Amount',
            'Status',
            'Bill Reference',
            'Payment Reference',
            'Created At',
            'Confirmed At',
        ];
    }

    public function map(mixed $payment): array
    {
        /** @var BillPayment $payment */
        $transaction = $payment->ledgerTransaction;
        $customer = $transaction?->wallet?->user;

        return [
            $transaction?->transaction_no ?? '',
            $customer?->name ?? '',
            $customer?->phone ?? '',
            $payment->broadband_account_number ?? '',
            $transaction?->amount ?? '',
            $payment->status?->value ?? '',
            $payment->external_bill_ref ?? '',
            $payment->external_payment_ref ?? '',
            $payment->getRawOriginal('created_at') ?? '',
            $payment->confirmed_at?->toDateTimeString() ?? '',
        ];
    }
}
