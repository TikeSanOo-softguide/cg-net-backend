<?php

namespace App\Exports;

use App\Models\LedgerTransaction;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TransactionExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
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
            'Reversal Of',
            'Actor Type',
            'Actor ID',
            'Customer',
            'Customer Phone',
            'Wallet ID',
            'Type',
            'Direction',
            'Amount',
            'Status',
            'Idempotency Key',
            'Balance Before',
            'Balance After',
            'Bill Payment ID',
            'Package Order ID',
            'Top Up Card Serial No.',
            'IP Address',
            'Device',
            'Created At',
        ];
    }

    public function map(mixed $transaction): array
    {
        /** @var LedgerTransaction $transaction */
        $liabilityEntry = $transaction->walletLiabilityEntry();
        $direction = $liabilityEntry
            ? ($liabilityEntry->isCredit() ? 'credit' : 'debit')
            : '';

        return [
            $transaction->transaction_no,
            $transaction->reversalOf?->transaction_no ?? '',
            $transaction->actor_type?->value ?? '',
            $transaction->actor_id ?? '',
            $transaction->wallet?->user?->name ?? '',
            $transaction->wallet?->user?->phone ?? '',
            $transaction->wallet_id,
            $transaction->type->value,
            $direction,
            $transaction->amount,
            $transaction->status->value,
            $transaction->idempotency_key ?? '',
            $liabilityEntry?->balance_before ?? '',
            $liabilityEntry?->balance_after ?? '',
            $transaction->billPayment?->id ?? '',
            $transaction->packageOrder?->id ?? '',
            $transaction->topUpCard?->serial_no ?? '',
            $transaction->ip_address ?? '',
            $transaction->user_agent ?? '',
            $transaction->created_at?->toDateTimeString() ?? '',
        ];
    }
}
