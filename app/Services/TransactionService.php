<?php

namespace App\Services;

use App\Enums\WalletActorType;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\WalletTransaction;
use App\Support\PackageLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionService
{
    /** @return array{customer_id: int|null, search: string, actor_type: string, type: string, status: string, direction: string, from: string, to: string} */
    public function filters(Request $request): array
    {
        return [
            'customer_id' => $request->integer('customer_id') ?: null,
            'search' => trim($request->string('search')->toString()),
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
        return WalletTransaction::query()
            ->with([
                'wallet.user:id,name,phone',
                'walletEntry',
                'walletTransfer.fromWallet.user:id,name,phone',
                'walletTransfer.toWallet.user:id,name,phone',
                'billPayment.broadbandAccount:id,account_number,customer_name',
                'packageOrder.package.network:id,name_en,name_zh,name_my',
                'packageOrder.package.speed:id,mbps',
                'packageOrder.package.term:id,months',
                'topUpCard',
                'reversalOf:id,transaction_no',
            ])
            ->when(
                $filters['customer_id'],
                fn($query) => $query->whereHas(
                    'wallet',
                    fn($wallet) => $wallet->where('user_id', $filters['customer_id']),
                ),
            )
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query
                        ->whereLike('transaction_no', '%' . $filters['search'] . '%')
                        ->orWhereLike('idempotency_key', '%' . $filters['search'] . '%')
                        ->orWhereLike('type', '%' . $filters['search'] . '%')
                        ->orWhereLike('ip_address', '%' . $filters['search'] . '%')
                        ->orWhereLike('user_agent', '%' . $filters['search'] . '%')
                        ->orWhereHas('wallet.user', function ($user) use ($filters): void {
                            $user
                                ->whereLike('name', '%' . $filters['search'] . '%')
                                ->orWhereLike('phone', '%' . $filters['search'] . '%');
                        });
                });
            })
            ->when(
                in_array($filters['actor_type'], array_column(WalletActorType::cases(), 'value'), true),
                fn($query) => $query->where('actor_type', $filters['actor_type']),
            )
            ->when(
                in_array($filters['type'], array_column(WalletTransactionType::cases(), 'value'), true),
                fn($query) => $query->where('type', $filters['type']),
            )
            ->when(
                in_array($filters['status'], array_column(WalletTransactionStatus::cases(), 'value'), true),
                fn($query) => $query->where('status', $filters['status']),
            )
            ->when($filters['from'], fn($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'], fn($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->when(in_array($filters['direction'], ['credit', 'debit'], true), function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query
                        ->where(function ($query) use ($filters): void {
                            $query
                                ->where('type', '!=', WalletTransactionType::Transfer->value)
                                ->whereHas('walletEntry', fn($entry) => $entry->where('type', $filters['direction']));
                        })
                        ->orWhere(function ($query) use ($filters): void {
                            $query
                                ->where('type', WalletTransactionType::Transfer->value)
                                ->whereHas('walletTransfer', function ($transfer) use ($filters): void {
                                    $transfer->whereColumn(
                                        'wallet_transactions.wallet_id',
                                        'wallet_transfers.' .
                                            ($filters['direction'] === 'credit' ? 'to_wallet_id' : 'from_wallet_id'),
                                    );
                                });
                        });
                });
            })
            ->latest();
    }

    public function paginate(array $filters, string $pageName = 'page'): LengthAwarePaginator
    {
        return $this->query($filters)
            ->paginate(15, ['*'], $pageName)
            ->withQueryString()
            ->through(fn(WalletTransaction $transaction) => $this->payload($transaction));
    }

    public function payload(WalletTransaction $transaction): array
    {
        $direction =
            $transaction->type === WalletTransactionType::Transfer
                ? match (true) {
                    $transaction->walletTransfer?->from_wallet_id === $transaction->wallet_id => 'debit',
                    $transaction->walletTransfer?->to_wallet_id === $transaction->wallet_id => 'credit',
                    default => null,
                }
                : $transaction->walletEntry?->type?->value;

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
            'wallet_entry' => $transaction->walletEntry
                ? [
                    'type' => $transaction->walletEntry->type->value,
                    'balance_before' => $transaction->walletEntry->balance_before,
                    'balance_after' => $transaction->walletEntry->balance_after,
                ]
                : null,
            'wallet_transfer' => $transaction->walletTransfer
                ? [
                    'from_wallet_id' => $transaction->walletTransfer->from_wallet_id,
                    'from_wallet_user' => $this->walletUserPayload($transaction->walletTransfer->fromWallet),
                    'to_wallet_id' => $transaction->walletTransfer->to_wallet_id,
                    'to_wallet_user' => $this->walletUserPayload($transaction->walletTransfer->toWallet),
                    'amount' => $transaction->walletTransfer->amount,
                    'note' => $transaction->walletTransfer->note,
                ]
                : null,
            'related' => [
                'bill_payment_id' => $transaction->billPayment?->id,
                'bill_payment_account' => $transaction->billPayment?->broadbandAccount
                    ? [
                        'account_number' => $transaction->billPayment->broadbandAccount->account_number,
                        'customer_name' => $transaction->billPayment->broadbandAccount->customer_name,
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

    private function walletUserPayload(?object $wallet): ?array
    {
        return $wallet?->user
            ? [
                'name' => $wallet->user->name,
                'phone' => $wallet->user->phone,
            ]
            : null;
    }
}
