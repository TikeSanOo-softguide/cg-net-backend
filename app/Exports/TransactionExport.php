<?php

namespace App\Exports;

use App\Enums\WalletTransactionType;
use App\Models\WalletTransaction;
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
            'Customer',
            'Customer Phone',
            'Wallet ID',
            'Type',
            'Direction',
            'Amount',
            'Status',
            'Idempotency Key',
            'Actor Type',
            'Actor ID',
            'IP Address',
            'Device',
            'Balance Before',
            'Balance After',
            'Transfer From Wallet',
            'Transfer To Wallet',
            'Transfer Note',
            'Bill Payment ID',
            'Package Order ID',
            'Top Up Card Serial No.',
            'Created At',
        ];
    }

    public function map(mixed $transaction): array
    {
        /** @var WalletTransaction $transaction */
        $direction =
            $transaction->type === WalletTransactionType::Transfer
                ? match (true) {
                    $transaction->walletTransfer?->from_wallet_id === $transaction->wallet_id => 'debit',
                    $transaction->walletTransfer?->to_wallet_id === $transaction->wallet_id => 'credit',
                    default => '',
                }
                : $transaction->walletEntry?->type?->value ?? '';

        return [
            $transaction->transaction_no,
            $transaction->reversalOf?->transaction_no ?? '',
            $transaction->wallet?->user?->name ?? '',
            $transaction->wallet?->user?->phone ?? '',
            $transaction->wallet_id,
            $transaction->type->value,
            $direction,
            $transaction->amount,
            $transaction->status->value,
            $transaction->idempotency_key ?? '',
            $transaction->actor_type?->value ?? '',
            $transaction->actor_id ?? '',
            $transaction->ip_address ?? '',
            $transaction->user_agent ?? '',
            $transaction->walletEntry?->balance_before ?? '',
            $transaction->walletEntry?->balance_after ?? '',
            $transaction->walletTransfer?->from_wallet_id ?? '',
            $transaction->walletTransfer?->to_wallet_id ?? '',
            $transaction->walletTransfer?->note ?? '',
            $transaction->billPayment?->id ?? '',
            $transaction->packageOrder?->id ?? '',
            $transaction->topUpCard?->serial_no ?? '',
            $transaction->created_at?->toDateTimeString() ?? '',
        ];
    }
}
