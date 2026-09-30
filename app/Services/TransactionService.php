<?php

namespace App\Services;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletActorType;
use App\Models\LedgerTransaction;
use App\Support\PackageLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionService
{
    /** @return array{customer_id: int|null, search: string, open_transaction: string, actor_type: string, type: string, status: string, direction: string, from: string, to: string} */
    public function filters(Request $request): array
    {
        return [
            'customer_id' => $request->integer('customer_id') ?: null,
            'search' => trim($request->string('search')->toString()),
            'open_transaction' => trim($request->string('open_transaction')->toString()),
            'actor_type' => $request->string('actor_type')->toString(),
            'type' => $request->string('type')->toString(),
            'status' => $request->string('status')->toString(),
            'direction' => $request->string('direction')->toString(),
            'from' => $request->date('from')?->toDateString() ?? '',
            'to' => $request->date('to')?->toDateString() ?? '',
        ];
    }

    public function query(array $filters): Builder
    {
        return LedgerTransaction::query()
            ->with([
                'wallet.user:id,name,phone',
                'entries.ledgerAccount',
                'packageOrder.package.network:id,name_en,name_zh,name_my',
                'packageOrder.package.speed:id,mbps',
                'packageOrder.package.term:id,months',
                'topUpCard',
                'billPayment',
                'reversalOf:id,transaction_no',
            ])
            ->when(
                $filters['customer_id'],
                fn ($query) => $query->whereHas(
                    'wallet',
                    fn ($wallet) => $wallet->where('user_id', $filters['customer_id']),
                ),
            )
            ->when(
                $filters['open_transaction'] !== '',
                fn ($query) => $query->where('transaction_no', $filters['open_transaction']),
            )
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query
                        ->whereLike('transaction_no', '%'.$filters['search'].'%')
                        ->orWhereLike('idempotency_key', '%'.$filters['search'].'%')
                        ->orWhereLike('type', '%'.$filters['search'].'%')
                        ->orWhereLike('ip_address', '%'.$filters['search'].'%')
                        ->orWhereLike('user_agent', '%'.$filters['search'].'%')
                        ->orWhereHas('wallet.user', function ($user) use ($filters): void {
                            $user
                                ->whereLike('name', '%'.$filters['search'].'%')
                                ->orWhereLike('phone', '%'.$filters['search'].'%');
                        });
                });
            })
            ->when(
                in_array($filters['actor_type'], array_column(WalletActorType::cases(), 'value'), true),
                fn ($query) => $query->where('actor_type', $filters['actor_type']),
            )
            ->when(
                in_array($filters['type'], array_column(LedgerTransactionType::cases(), 'value'), true),
                fn ($query) => $query->where('type', $filters['type']),
            )
            ->when(
                in_array($filters['status'], array_column(LedgerTransactionStatus::cases(), 'value'), true),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when($filters['from'], fn ($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'], fn ($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->when(in_array($filters['direction'], ['credit', 'debit'], true), function ($query) use ($filters): void {
                $query->whereHas('entries', function ($entry) use ($filters): void {
                    $entry
                        ->whereNotNull('wallet_id')
                        ->whereColumn('wallet_id', 'ledger_transactions.wallet_id');

                    if ($filters['direction'] === 'credit') {
                        $entry->where('credit', '>', 0);
                    } else {
                        $entry->where('debit', '>', 0);
                    }
                });
            })
            ->latest();
    }

    public function paginate(array $filters, string $pageName = 'page'): LengthAwarePaginator
    {
        return $this->query($filters)
            ->paginate(15, ['*'], $pageName)
            ->withQueryString()
            ->through(fn (LedgerTransaction $transaction) => $this->payload($transaction));
    }

    public function payload(LedgerTransaction $transaction): array
    {
        $liabilityEntry = $transaction->walletLiabilityEntry();
        $direction = $liabilityEntry
            ? ($liabilityEntry->isCredit() ? 'credit' : 'debit')
            : null;

        return [
            'id' => $transaction->id,
            'transaction_no' => $transaction->transaction_no,
            'type' => $transaction->type->value,
            'status' => $transaction->status->value,
            'direction' => $direction,
            'amount' => number_format((float) $transaction->amount, 0, '.', ''),
            'created_at' => $transaction->created_at?->toISOString(),
            'wallet_id' => $transaction->wallet_id,
            'customer' => $transaction->wallet?->user
                ? [
                    'id' => $transaction->wallet->user->id,
                    'name' => $transaction->wallet->user->name,
                    'phone' => $transaction->wallet->user->phone,
                ]
                : null,
            'actor_type' => $transaction->actor_type?->value,
            'actor_id' => $transaction->actor_id,
            'ip_address' => $transaction->ip_address,
            'user_agent' => $transaction->user_agent,
            'idempotency_key' => $transaction->idempotency_key,
            'reversal_of' => $transaction->reversalOf?->transaction_no,
            'wallet_entry' => $liabilityEntry
                ? [
                    'type' => $direction,
                    'balance_before' => $liabilityEntry->balance_before,
                    'balance_after' => $liabilityEntry->balance_after,
                ]
                : null,
            'related' => [
                'bill_payment_id' => $transaction->billPayment?->id,
                'bill_payment_account' => $transaction->billPayment?->broadband_account_number
                    ? [
                        'broadband_account_number' => $transaction->billPayment->broadband_account_number,
                        'customer_name' => $transaction->wallet?->user?->name,
                    ]
                    : null,
                'package_order_id' => $transaction->packageOrder?->id,
                'package_order_detail' => $transaction->packageOrder
                    ? [
                        'package' => PackageLabel::make($transaction->packageOrder->package),
                        'status' => $transaction->packageOrder->status->value,
                    ]
                    : null,
                'top_up_card_serial_no' => $transaction->topUpCard?->serial_no,
            ],
        ];
    }
}
