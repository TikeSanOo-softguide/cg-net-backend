<?php

namespace App\Services\Reports;

use App\Enums\LedgerTransactionStatus;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LedgerHealthReportService
{
    /**
     * @return array{
     *     health: array{
     *         wallets: int,
     *         entries: int,
     *         balance_mismatches: int,
     *         completed_without_entry: int,
     *         unbalanced_transactions: int,
     *         duplicate_entry_transactions: int
     *     },
     *     balanceMismatches: list<array{wallet_id: int, customer_id: int, customer: string|null, wallet_balance: int, ledger_balance: int|null}>,
     *     missingEntries: list<array{transaction_no: string, wallet_id: int, customer: string|null, type: string, amount: int, created_at: string|null}>,
     *     unbalancedTransactions: list<array{transaction_no: string, wallet_id: int, debit_total: int, credit_total: int, created_at: string|null}>
     * }
     */
    public function generate(): array
    {
        $derivedBalances = LedgerEntry::query()
            ->whereNotNull('wallet_id')
            ->select('wallet_id')
            ->selectRaw('COALESCE(SUM(credit) - SUM(debit), 0) as derived_balance')
            ->groupBy('wallet_id');

        $balanceMismatchQuery = Wallet::query()
            ->leftJoinSub($derivedBalances, 'ledger_balances', function ($join): void {
                $join->on('ledger_balances.wallet_id', '=', 'wallets.id');
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereRaw('wallets.balance != COALESCE(ledger_balances.derived_balance, 0)')
                    ->orWhere(function (Builder $query): void {
                        $query->whereNull('ledger_balances.wallet_id')->where('wallets.balance', '!=', 0);
                    });
            });

        $balanceMismatches = (clone $balanceMismatchQuery)
            ->select([
                'wallets.id',
                'wallets.user_id',
                'wallets.balance',
                'ledger_balances.derived_balance as ledger_balance',
            ])
            ->with('user:id,name,phone')
            ->orderBy('wallets.id')
            ->limit(50)
            ->get()
            ->map(
                fn (Wallet $wallet): array => [
                    'wallet_id' => $wallet->id,
                    'customer_id' => (int) $wallet->user_id,
                    'customer' => $wallet->user?->name,
                    'wallet_balance' => (int) $wallet->balance,
                    'ledger_balance' => $wallet->ledger_balance !== null ? (int) $wallet->ledger_balance : null,
                ],
            )
            ->values()
            ->all();

        $postedStatuses = [
            LedgerTransactionStatus::Processing->value,
            LedgerTransactionStatus::Completed->value,
            LedgerTransactionStatus::Failed->value,
        ];

        $missingEntries = LedgerTransaction::query()
            ->whereIn('status', $postedStatuses)
            ->whereDoesntHave('entries')
            ->with('wallet.user:id,name,phone')
            ->latest('id')
            ->limit(50)
            ->get(['id', 'wallet_id', 'transaction_no', 'type', 'amount', 'created_at'])
            ->map(
                fn (LedgerTransaction $transaction): array => [
                    'transaction_no' => $transaction->transaction_no,
                    'wallet_id' => $transaction->wallet_id,
                    'customer' => $transaction->wallet?->user?->name,
                    'type' => $transaction->type->value,
                    'amount' => (int) $transaction->amount,
                    'created_at' => $transaction->created_at?->toISOString(),
                ],
            )
            ->values()
            ->all();

        $unbalancedQuery = LedgerTransaction::query()
            ->whereHas('entries')
            ->whereRaw(
                '(SELECT COALESCE(SUM(debit), 0) FROM ledger_entries WHERE ledger_entries.ledger_transaction_id = ledger_transactions.id)
                 != (SELECT COALESCE(SUM(credit), 0) FROM ledger_entries WHERE ledger_entries.ledger_transaction_id = ledger_transactions.id)',
            );

        $unbalancedTransactions = (clone $unbalancedQuery)
            ->withSum('entries as debit_total', 'debit')
            ->withSum('entries as credit_total', 'credit')
            ->latest('id')
            ->limit(50)
            ->get(['id', 'wallet_id', 'transaction_no', 'created_at'])
            ->map(
                fn (LedgerTransaction $transaction): array => [
                    'transaction_no' => $transaction->transaction_no,
                    'wallet_id' => $transaction->wallet_id,
                    'debit_total' => (int) $transaction->debit_total,
                    'credit_total' => (int) $transaction->credit_total,
                    'created_at' => $transaction->created_at?->toISOString(),
                ],
            )
            ->values()
            ->all();

        return [
            'health' => [
                'wallets' => Wallet::query()->count(),
                'entries' => LedgerEntry::query()->count(),
                'balance_mismatches' => (clone $balanceMismatchQuery)->count('wallets.id'),
                'completed_without_entry' => LedgerTransaction::query()
                    ->whereIn('status', $postedStatuses)
                    ->whereDoesntHave('entries')
                    ->count(),
                'unbalanced_transactions' => (clone $unbalancedQuery)->count(),
                'duplicate_entry_transactions' => (int) DB::table('ledger_entries')
                    ->select('ledger_transaction_id')
                    ->groupBy('ledger_transaction_id', 'line_no')
                    ->havingRaw('COUNT(*) > 1')
                    ->get()
                    ->count(),
            ],
            'balanceMismatches' => $balanceMismatches,
            'missingEntries' => $missingEntries,
            'unbalancedTransactions' => $unbalancedTransactions,
        ];
    }
}
