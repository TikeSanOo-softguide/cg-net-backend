<?php

namespace App\Services\Reports;

use App\Enums\BillPaymentStatus;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\PackageOrderStatus;
use App\Enums\TopUpCardStatus;
use App\Models\BillPayment;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\PackageOrder;
use App\Models\TopUpCard;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LedgerHealthReportService
{
    private const SAMPLE_LIMIT = 50;

    private ?Carbon $windowStart = null;

    private ?Carbon $windowEnd = null;

    /**
     * @return array{
     *     health: array{
     *         wallets: int,
     *         entries: int,
     *         balance_mismatches: int,
     *         completed_without_entry: int,
     *         unbalanced_transactions: int,
     *         duplicate_entry_transactions: int,
     *         source_mismatches: int
     *     },
     *     balanceMismatches: list<array{wallet_id: int, customer_id: int, customer: string|null, wallet_balance: int, ledger_balance: int|null}>,
     *     missingEntries: list<array{transaction_no: string, wallet_id: int, customer: string|null, type: string, amount: int, created_at: string|null}>,
     *     unbalancedTransactions: list<array{transaction_no: string, wallet_id: int, debit_total: int, credit_total: int, created_at: string|null}>,
     *     sourceMismatches: list<array{
     *         id: string,
     *         source: string,
     *         source_label: string,
     *         transaction_no: string|null,
     *         customer_id: int|null,
     *         customer: string|null,
     *         issue: string,
     *         detail: string|null,
     *         created_at: string|null
     *     }>,
     *     window: array{start: string, end: string}|null
     * }
     */
    public function generate(?Carbon $windowStart = null, ?Carbon $windowEnd = null): array
    {
        $this->windowStart = $windowStart;
        $this->windowEnd = $windowEnd;

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
            ->limit(self::SAMPLE_LIMIT)
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

        $missingEntriesQuery = LedgerTransaction::query()
            ->whereIn('status', $postedStatuses)
            ->whereDoesntHave('entries');
        $this->applyWindow($missingEntriesQuery, 'ledger_transactions.created_at');

        $missingEntries = $missingEntriesQuery
            ->with('wallet.user:id,name,phone')
            ->latest('id')
            ->limit(self::SAMPLE_LIMIT)
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
        $this->applyWindow($unbalancedQuery, 'ledger_transactions.created_at');

        $unbalancedTransactions = (clone $unbalancedQuery)
            ->withSum('entries as debit_total', 'debit')
            ->withSum('entries as credit_total', 'credit')
            ->latest('id')
            ->limit(self::SAMPLE_LIMIT)
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

        $sourceMismatches = $this->sourceMismatches();

        $missingEntriesCountQuery = LedgerTransaction::query()
            ->whereIn('status', $postedStatuses)
            ->whereDoesntHave('entries');
        $this->applyWindow($missingEntriesCountQuery, 'ledger_transactions.created_at');

        return [
            'health' => [
                'wallets' => Wallet::query()->count(),
                'entries' => LedgerEntry::query()->count(),
                'balance_mismatches' => (clone $balanceMismatchQuery)->count('wallets.id'),
                'completed_without_entry' => $missingEntriesCountQuery->count(),
                'unbalanced_transactions' => (clone $unbalancedQuery)->count(),
                'duplicate_entry_transactions' => (int) DB::table('ledger_entries')
                    ->select('ledger_transaction_id')
                    ->groupBy('ledger_transaction_id', 'line_no')
                    ->havingRaw('COUNT(*) > 1')
                    ->get()
                    ->count(),
                'source_mismatches' => count($sourceMismatches),
            ],
            'balanceMismatches' => $balanceMismatches,
            'missingEntries' => $missingEntries,
            'unbalancedTransactions' => $unbalancedTransactions,
            'sourceMismatches' => $sourceMismatches,
            'window' => $this->windowStart !== null && $this->windowEnd !== null
                ? [
                    'start' => $this->windowStart->toISOString(),
                    'end' => $this->windowEnd->toISOString(),
                ]
                : null,
        ];
    }

    /**
     * Window is (start, end] — after yesterday 4pm through today 4pm.
     */
    private function applyWindow(Builder $query, string $column): void
    {
        if ($this->windowStart === null || $this->windowEnd === null) {
            return;
        }

        $query
            ->where($column, '>', $this->windowStart)
            ->where($column, '<=', $this->windowEnd);
    }

    private function hasWindow(): bool
    {
        return $this->windowStart !== null && $this->windowEnd !== null;
    }

    /**
     * @return list<array{
     *     id: string,
     *     source: string,
     *     source_label: string,
     *     transaction_no: string|null,
     *     customer_id: int|null,
     *     customer: string|null,
     *     issue: string,
     *     detail: string|null,
     *     created_at: string|null
     * }>
     */
    private function sourceMismatches(): array
    {
        $rows = [
            ...$this->topUpCardMismatches(),
            ...$this->billPaymentMismatches(),
            ...$this->packageOrderMismatches(),
            ...$this->orphanLedgerMismatches(),
        ];

        usort(
            $rows,
            fn (array $left, array $right): int => strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? '')),
        );

        return $rows;
    }

    /**
     * @return list<array{
     *     id: string,
     *     source: string,
     *     source_label: string,
     *     transaction_no: string|null,
     *     customer_id: int|null,
     *     customer: string|null,
     *     issue: string,
     *     detail: string|null,
     *     created_at: string|null
     * }>
     */
    private function topUpCardMismatches(): array
    {
        $rows = [];

        $usedCardsQuery = TopUpCard::query()
            ->withTrashed()
            ->where('status', TopUpCardStatus::Used)
            ->with([
                'ledgerTransaction:id,wallet_id,transaction_no,type,status,amount',
                'ledgerTransaction.wallet:id,user_id',
                'redeemedBy:id,name',
            ]);

        if ($this->hasWindow()) {
            $usedCardsQuery->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $query): void {
                        $query
                            ->whereNotNull('redeemed_at')
                            ->where('redeemed_at', '>', $this->windowStart)
                            ->where('redeemed_at', '<=', $this->windowEnd);
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->whereNull('redeemed_at')
                            ->where('created_at', '>', $this->windowStart)
                            ->where('created_at', '<=', $this->windowEnd);
                    });
            });
        }

        $usedCards = $usedCardsQuery->latest('id')->get();

        foreach ($usedCards as $card) {
            $customerId = $card->redeemed_by !== null ? (int) $card->redeemed_by : null;
            $customer = $card->redeemedBy?->name;
            $createdAt = $this->toIso($card->redeemed_at ?? $card->created_at);
            $label = $card->serial_no;

            if ($card->ledger_transaction_id === null || $card->ledgerTransaction === null) {
                $rows[] = $this->mismatchRow(
                    id: 'topup-card-'.$card->id.'-missing-ledger',
                    source: 'topup_card',
                    sourceLabel: $label,
                    transactionNo: null,
                    customerId: $customerId,
                    customer: $customer,
                    issue: 'missing_ledger',
                    detail: null,
                    createdAt: $createdAt,
                );

                continue;
            }

            $transaction = $card->ledgerTransaction;

            if ($transaction->type !== LedgerTransactionType::Topup) {
                $rows[] = $this->mismatchRow(
                    id: 'topup-card-'.$card->id.'-type',
                    source: 'topup_card',
                    sourceLabel: $label,
                    transactionNo: $transaction->transaction_no,
                    customerId: $customerId,
                    customer: $customer,
                    issue: 'type_mismatch',
                    detail: $transaction->type->value,
                    createdAt: $createdAt,
                );
            }

            if ((int) $transaction->amount !== (int) $card->amount) {
                $rows[] = $this->mismatchRow(
                    id: 'topup-card-'.$card->id.'-amount',
                    source: 'topup_card',
                    sourceLabel: $label,
                    transactionNo: $transaction->transaction_no,
                    customerId: $customerId,
                    customer: $customer,
                    issue: 'amount_mismatch',
                    detail: (int) $card->amount.' vs '.(int) $transaction->amount,
                    createdAt: $createdAt,
                );
            }

            if ($transaction->status !== LedgerTransactionStatus::Completed) {
                $rows[] = $this->mismatchRow(
                    id: 'topup-card-'.$card->id.'-status',
                    source: 'topup_card',
                    sourceLabel: $label,
                    transactionNo: $transaction->transaction_no,
                    customerId: $customerId,
                    customer: $customer,
                    issue: 'status_mismatch',
                    detail: TopUpCardStatus::Used->value.' / '.$transaction->status->value,
                    createdAt: $createdAt,
                );
            }

            $walletUserId = $transaction->wallet?->user_id;

            if ($customerId !== null && $walletUserId !== null && (int) $walletUserId !== $customerId) {
                $rows[] = $this->mismatchRow(
                    id: 'topup-card-'.$card->id.'-wallet',
                    source: 'topup_card',
                    sourceLabel: $label,
                    transactionNo: $transaction->transaction_no,
                    customerId: $customerId,
                    customer: $customer,
                    issue: 'wallet_mismatch',
                    detail: null,
                    createdAt: $createdAt,
                );
            }
        }

        return $rows;
    }

    /**
     * @return list<array{
     *     id: string,
     *     source: string,
     *     source_label: string,
     *     transaction_no: string|null,
     *     customer_id: int|null,
     *     customer: string|null,
     *     issue: string,
     *     detail: string|null,
     *     created_at: string|null
     * }>
     */
    private function billPaymentMismatches(): array
    {
        $rows = [];

        $paymentsQuery = BillPayment::query()
            ->with([
                'ledgerTransaction:id,wallet_id,transaction_no,type,status,amount',
                'ledgerTransaction.wallet.user:id,name',
            ]);

        if ($this->hasWindow()) {
            $paymentsQuery->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $query): void {
                        $query
                            ->whereNotNull('confirmed_at')
                            ->where('confirmed_at', '>', $this->windowStart)
                            ->where('confirmed_at', '<=', $this->windowEnd);
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->whereNull('confirmed_at')
                            ->where('created_at', '>', $this->windowStart)
                            ->where('created_at', '<=', $this->windowEnd);
                    });
            });
        }

        $payments = $paymentsQuery->latest('id')->get();

        foreach ($payments as $payment) {
            $transaction = $payment->ledgerTransaction;
            $customerId = $transaction?->wallet?->user_id !== null ? (int) $transaction->wallet->user_id : null;
            $customer = $transaction?->wallet?->user?->name;
            $createdAt = $this->toIso($payment->confirmed_at ?? $payment->created_at);
            $label = $payment->broadband_account_number
                ?: 'bill-'.$payment->id;

            if ($transaction === null) {
                $rows[] = $this->mismatchRow(
                    id: 'bill-'.$payment->id.'-missing-ledger',
                    source: 'bill_payment',
                    sourceLabel: $label,
                    transactionNo: null,
                    customerId: $customerId,
                    customer: $customer,
                    issue: 'missing_ledger',
                    detail: null,
                    createdAt: $createdAt,
                );

                continue;
            }

            if ($transaction->type !== LedgerTransactionType::FtthBill) {
                $rows[] = $this->mismatchRow(
                    id: 'bill-'.$payment->id.'-type',
                    source: 'bill_payment',
                    sourceLabel: $label,
                    transactionNo: $transaction->transaction_no,
                    customerId: $customerId,
                    customer: $customer,
                    issue: 'type_mismatch',
                    detail: $transaction->type->value,
                    createdAt: $createdAt,
                );
            }

            if ($this->billStatusMismatch($payment->status, $transaction->status)) {
                $rows[] = $this->mismatchRow(
                    id: 'bill-'.$payment->id.'-status',
                    source: 'bill_payment',
                    sourceLabel: $label,
                    transactionNo: $transaction->transaction_no,
                    customerId: $customerId,
                    customer: $customer,
                    issue: 'status_mismatch',
                    detail: $payment->status->value.' / '.$transaction->status->value,
                    createdAt: $createdAt,
                );
            }
        }

        return $rows;
    }

    /**
     * @return list<array{
     *     id: string,
     *     source: string,
     *     source_label: string,
     *     transaction_no: string|null,
     *     customer_id: int|null,
     *     customer: string|null,
     *     issue: string,
     *     detail: string|null,
     *     created_at: string|null
     * }>
     */
    private function packageOrderMismatches(): array
    {
        $rows = [];

        $ordersQuery = PackageOrder::query()
            ->with([
                'user:id,name',
                'ledgerTransaction:id,wallet_id,transaction_no,type,status,amount',
                'ledgerTransaction.wallet:id,user_id',
            ])
            ->whereIn('status', [
                PackageOrderStatus::Processing->value,
                PackageOrderStatus::Completed->value,
                PackageOrderStatus::Failed->value,
            ]);

        if ($this->hasWindow()) {
            $ordersQuery->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $query): void {
                        $query
                            ->whereNotNull('completed_at')
                            ->where('completed_at', '>', $this->windowStart)
                            ->where('completed_at', '<=', $this->windowEnd);
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->whereNull('completed_at')
                            ->where('created_at', '>', $this->windowStart)
                            ->where('created_at', '<=', $this->windowEnd);
                    });
            });
        }

        $orders = $ordersQuery->latest('id')->get();

        foreach ($orders as $order) {
            $customerId = (int) $order->user_id;
            $customer = $order->user?->name;
            $createdAt = $this->toIso($order->completed_at ?? $order->created_at);
            $label = 'order-'.$order->id;
            $expectedAmount = isset($order->snapshot['price']) ? (int) $order->snapshot['price'] : null;

            if (
                in_array($order->status, [PackageOrderStatus::Processing, PackageOrderStatus::Completed], true)
                && ($order->ledger_transaction_id === null || $order->ledgerTransaction === null)
            ) {
                $rows[] = $this->mismatchRow(
                    id: 'package-'.$order->id.'-missing-ledger',
                    source: 'package_order',
                    sourceLabel: $label,
                    transactionNo: null,
                    customerId: $customerId,
                    customer: $customer,
                    issue: 'missing_ledger',
                    detail: null,
                    createdAt: $createdAt,
                );

                continue;
            }

            $transaction = $order->ledgerTransaction;

            if ($transaction === null) {
                continue;
            }

            if ($transaction->type !== LedgerTransactionType::WifiPackage) {
                $rows[] = $this->mismatchRow(
                    id: 'package-'.$order->id.'-type',
                    source: 'package_order',
                    sourceLabel: $label,
                    transactionNo: $transaction->transaction_no,
                    customerId: $customerId,
                    customer: $customer,
                    issue: 'type_mismatch',
                    detail: $transaction->type->value,
                    createdAt: $createdAt,
                );
            }

            if ($expectedAmount !== null && (int) $transaction->amount !== $expectedAmount) {
                $rows[] = $this->mismatchRow(
                    id: 'package-'.$order->id.'-amount',
                    source: 'package_order',
                    sourceLabel: $label,
                    transactionNo: $transaction->transaction_no,
                    customerId: $customerId,
                    customer: $customer,
                    issue: 'amount_mismatch',
                    detail: $expectedAmount.' vs '.(int) $transaction->amount,
                    createdAt: $createdAt,
                );
            }

            if ($this->packageStatusMismatch($order->status, $transaction->status)) {
                $rows[] = $this->mismatchRow(
                    id: 'package-'.$order->id.'-status',
                    source: 'package_order',
                    sourceLabel: $label,
                    transactionNo: $transaction->transaction_no,
                    customerId: $customerId,
                    customer: $customer,
                    issue: 'status_mismatch',
                    detail: $order->status->value.' / '.$transaction->status->value,
                    createdAt: $createdAt,
                );
            }

            $walletUserId = $transaction->wallet?->user_id;

            if ($walletUserId !== null && (int) $walletUserId !== $customerId) {
                $rows[] = $this->mismatchRow(
                    id: 'package-'.$order->id.'-wallet',
                    source: 'package_order',
                    sourceLabel: $label,
                    transactionNo: $transaction->transaction_no,
                    customerId: $customerId,
                    customer: $customer,
                    issue: 'wallet_mismatch',
                    detail: null,
                    createdAt: $createdAt,
                );
            }
        }

        return $rows;
    }

    /**
     * @return list<array{
     *     id: string,
     *     source: string,
     *     source_label: string,
     *     transaction_no: string|null,
     *     customer_id: int|null,
     *     customer: string|null,
     *     issue: string,
     *     detail: string|null,
     *     created_at: string|null
     * }>
     */
    private function orphanLedgerMismatches(): array
    {
        $rows = [];

        $orphanStatuses = [
            LedgerTransactionStatus::Processing->value,
            LedgerTransactionStatus::Completed->value,
        ];

        $topupsQuery = LedgerTransaction::query()
            ->where('type', LedgerTransactionType::Topup)
            ->whereIn('status', $orphanStatuses)
            ->whereDoesntHave('topUpCard')
            ->with('wallet.user:id,name');
        $this->applyWindow($topupsQuery, 'ledger_transactions.created_at');
        $topups = $topupsQuery->latest('id')->get(['id', 'wallet_id', 'transaction_no', 'created_at']);

        foreach ($topups as $transaction) {
            $rows[] = $this->mismatchRow(
                id: 'ledger-topup-'.$transaction->id.'-missing-source',
                source: 'ledger_transaction',
                sourceLabel: $transaction->transaction_no,
                transactionNo: $transaction->transaction_no,
                customerId: $transaction->wallet?->user_id !== null ? (int) $transaction->wallet->user_id : null,
                customer: $transaction->wallet?->user?->name,
                issue: 'missing_source',
                detail: LedgerTransactionType::Topup->value,
                createdAt: $transaction->created_at?->toISOString(),
            );
        }

        $billsQuery = LedgerTransaction::query()
            ->where('type', LedgerTransactionType::FtthBill)
            ->whereIn('status', $orphanStatuses)
            ->whereDoesntHave('billPayment')
            ->with('wallet.user:id,name');
        $this->applyWindow($billsQuery, 'ledger_transactions.created_at');
        $bills = $billsQuery->latest('id')->get(['id', 'wallet_id', 'transaction_no', 'created_at']);

        foreach ($bills as $transaction) {
            $rows[] = $this->mismatchRow(
                id: 'ledger-bill-'.$transaction->id.'-missing-source',
                source: 'ledger_transaction',
                sourceLabel: $transaction->transaction_no,
                transactionNo: $transaction->transaction_no,
                customerId: $transaction->wallet?->user_id !== null ? (int) $transaction->wallet->user_id : null,
                customer: $transaction->wallet?->user?->name,
                issue: 'missing_source',
                detail: LedgerTransactionType::FtthBill->value,
                createdAt: $transaction->created_at?->toISOString(),
            );
        }

        $packagesQuery = LedgerTransaction::query()
            ->where('type', LedgerTransactionType::WifiPackage)
            ->whereIn('status', $orphanStatuses)
            ->whereDoesntHave('packageOrder')
            ->with('wallet.user:id,name');
        $this->applyWindow($packagesQuery, 'ledger_transactions.created_at');
        $packages = $packagesQuery->latest('id')->get(['id', 'wallet_id', 'transaction_no', 'created_at']);

        foreach ($packages as $transaction) {
            $rows[] = $this->mismatchRow(
                id: 'ledger-package-'.$transaction->id.'-missing-source',
                source: 'ledger_transaction',
                sourceLabel: $transaction->transaction_no,
                transactionNo: $transaction->transaction_no,
                customerId: $transaction->wallet?->user_id !== null ? (int) $transaction->wallet->user_id : null,
                customer: $transaction->wallet?->user?->name,
                issue: 'missing_source',
                detail: LedgerTransactionType::WifiPackage->value,
                createdAt: $transaction->created_at?->toISOString(),
            );
        }

        return $rows;
    }

    private function billStatusMismatch(BillPaymentStatus $billStatus, LedgerTransactionStatus $ledgerStatus): bool
    {
        return match ($billStatus) {
            BillPaymentStatus::Completed => $ledgerStatus !== LedgerTransactionStatus::Completed,
            BillPaymentStatus::Processing => ! in_array(
                $ledgerStatus,
                [LedgerTransactionStatus::Processing, LedgerTransactionStatus::Pending],
                true,
            ),
            BillPaymentStatus::Failed => $ledgerStatus === LedgerTransactionStatus::Completed,
        };
    }

    private function packageStatusMismatch(PackageOrderStatus $orderStatus, LedgerTransactionStatus $ledgerStatus): bool
    {
        return match ($orderStatus) {
            PackageOrderStatus::Completed => $ledgerStatus !== LedgerTransactionStatus::Completed,
            PackageOrderStatus::Processing => ! in_array(
                $ledgerStatus,
                [LedgerTransactionStatus::Processing, LedgerTransactionStatus::Pending],
                true,
            ),
            PackageOrderStatus::Failed => $ledgerStatus === LedgerTransactionStatus::Completed,
        };
    }

    /**
     * @return array{
     *     id: string,
     *     source: string,
     *     source_label: string,
     *     transaction_no: string|null,
     *     customer_id: int|null,
     *     customer: string|null,
     *     issue: string,
     *     detail: string|null,
     *     created_at: string|null
     * }
     */
    private function mismatchRow(
        string $id,
        string $source,
        string $sourceLabel,
        ?string $transactionNo,
        ?int $customerId,
        ?string $customer,
        string $issue,
        ?string $detail,
        ?string $createdAt,
    ): array {
        return [
            'id' => $id,
            'source' => $source,
            'source_label' => $sourceLabel,
            'transaction_no' => $transactionNo,
            'customer_id' => $customerId,
            'customer' => $customer,
            'issue' => $issue,
            'detail' => $detail,
            'created_at' => $createdAt,
        ];
    }

    private function toIso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toISOString();
        } catch (\Throwable) {
            return null;
        }
    }
}
