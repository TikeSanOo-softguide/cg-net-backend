<?php

namespace App\Http\Controllers\Transaction;

use App\Enums\WalletActorType;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $customerId = $request->integer('customer_id') ?: null;
        $search = trim($request->string('search')->toString());
        $actorType = $request->string('actor_type')->toString();
        $status = $request->string('status')->toString();
        $direction = $request->string('direction')->toString();
        $from = $request->date('from')?->toDateString();
        $to = $request->date('to')?->toDateString();

        $transactions = WalletTransaction::query()
            ->with([
                'wallet.user:id,name,phone',
                'walletEntry',
                'walletTransfer',
                'billPayment',
                'packageOrder',
                'topUpCard',
                'reversalOf:id,transaction_no',
            ])
            ->when(
                $customerId,
                fn($query) => $query->whereHas('wallet', fn($wallet) => $wallet->where('user_id', $customerId)),
            )
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('transaction_no', 'like', '%' . $search . '%')
                        ->orWhereHas('wallet.user', function ($user) use ($search): void {
                            $user
                                ->where('name', 'like', '%' . $search . '%')
                                ->orWhere('phone', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when(
                in_array($actorType, array_column(WalletActorType::cases(), 'value'), true),
                fn($query) => $query->where('actor_type', $actorType),
            )
            ->when(
                in_array($status, array_column(WalletTransactionStatus::cases(), 'value'), true),
                fn($query) => $query->where('status', $status),
            )
            ->when($from, fn($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn($query) => $query->whereDate('created_at', '<=', $to))
            ->when(in_array($direction, ['credit', 'debit'], true), function ($query) use ($direction): void {
                $query->where(function ($query) use ($direction): void {
                    $query
                        ->where(function ($query) use ($direction): void {
                            $query
                                ->where('type', '!=', WalletTransactionType::Transfer->value)
                                ->whereHas('walletEntry', fn($entry) => $entry->where('type', $direction));
                        })
                        ->orWhere(function ($query) use ($direction): void {
                            $query
                                ->where('type', WalletTransactionType::Transfer->value)
                                ->whereHas('walletTransfer', function ($transfer) use ($direction): void {
                                    $transfer->whereColumn(
                                        'wallet_transactions.wallet_id',
                                        'wallet_transfers.' .
                                            ($direction === 'credit' ? 'to_wallet_id' : 'from_wallet_id'),
                                    );
                                });
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn(WalletTransaction $transaction) => $this->transactionPayload($transaction));

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'filters' => [
                'customer_id' => $customerId,
                'search' => $search,
                'actor_type' => $actorType,
                'status' => $status,
                'direction' => $direction,
                'from' => $from ?? '',
                'to' => $to ?? '',
            ],
            'filterOptions' => [
                'actor_types' => array_column(WalletActorType::cases(), 'value'),
                'statuses' => array_column(WalletTransactionStatus::cases(), 'value'),
            ],
            'scope' => $customerId ? 'customer' : 'global',
        ]);
    }

    private function transactionPayload(WalletTransaction $transaction): array
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
                    'to_wallet_id' => $transaction->walletTransfer->to_wallet_id,
                    'amount' => $transaction->walletTransfer->amount,
                    'note' => $transaction->walletTransfer->note,
                ]
                : null,
            'related' => [
                'bill_payment_id' => $transaction->billPayment?->id,
                'package_order_id' => $transaction->packageOrder?->id,
                'top_up_card_id' => $transaction->topUpCard?->id,
            ],
        ];
    }
}
