<?php

namespace App\Services\Reports;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerTransactionType;
use App\Enums\TopUpCardStatus;
use App\Models\TopUpCard;
use Illuminate\Support\Facades\DB;

class FinancialSummaryService
{
    /**
     * @return array{
     *     as_of: string,
     *     top_up_cards: array{
     *         activated: array{count: int, amount: string},
     *         active: array{count: int, amount: string},
     *         redeemed: array{count: int, amount: string},
     *         blocked: array{count: int, amount: string},
     *         expired: array{count: int, amount: string}
     *     },
     *     system_money: array{
     *         accounts: list<array{code: string, name: string, balance: string}>,
     *         wallet_balance: string,
     *         internet_service_revenue: array{ftth: string, cg: string},
     *         adjustments: array{debits: string, credits: string}
     *     },
     *     adjustments: array{debits: string, credits: string, net_expense: string}
     * }
     */
    public function generate(): array
    {
        return DB::transaction(function (): array {
            $asOf = now();

            $cardTotals = TopUpCard::query()
                ->withTrashed()
                ->selectRaw('COALESCE(SUM(CASE WHEN activated_at IS NOT NULL THEN 1 ELSE 0 END), 0) as activated_count')
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN activated_at IS NOT NULL THEN amount ELSE 0 END), 0) as activated_amount',
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status = ? AND activated_at IS NOT NULL THEN 1 ELSE 0 END), 0) as active_count',
                    [TopUpCardStatus::Active->value],
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status = ? AND activated_at IS NOT NULL THEN amount ELSE 0 END), 0) as active_amount',
                    [TopUpCardStatus::Active->value],
                )
                ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as redeemed_count', [
                    TopUpCardStatus::Used->value,
                ])
                ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as redeemed_amount', [
                    TopUpCardStatus::Used->value,
                ])
                ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as blocked_count', [
                    TopUpCardStatus::Blocked->value,
                ])
                ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as blocked_amount', [
                    TopUpCardStatus::Blocked->value,
                ])
                ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as expired_count', [
                    TopUpCardStatus::Expired->value,
                ])
                ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as expired_amount', [
                    TopUpCardStatus::Expired->value,
                ])
                ->first();

            $walletBalance = DB::table('wallets')->sum('balance');

            $systemMoneyAccountCodes = [
                LedgerAccountCode::CashTopup,
                LedgerAccountCode::PackageRevenue,
            ];
            $systemAccountCodes = [
                ...$systemMoneyAccountCodes,
                LedgerAccountCode::InternetServiceRevenue,
                LedgerAccountCode::AdjustmentExpense,
            ];
            $systemAccountTotals = DB::table('ledger_accounts')
                ->leftJoin('ledger_entries', 'ledger_entries.ledger_account_id', '=', 'ledger_accounts.id')
                ->leftJoin(
                    'ledger_transactions',
                    'ledger_transactions.id',
                    '=',
                    'ledger_entries.ledger_transaction_id',
                )
                ->leftJoin(
                    'ledger_transactions as original_transactions',
                    'original_transactions.id',
                    '=',
                    'ledger_transactions.reversal_of',
                )
                ->whereIn('ledger_accounts.code', array_map(
                    fn(LedgerAccountCode $code): string => $code->value,
                    $systemAccountCodes,
                ))
                ->select('ledger_accounts.code', 'ledger_accounts.name')
                ->selectRaw('COALESCE(SUM(ledger_entries.debit), 0) as debits')
                ->selectRaw('COALESCE(SUM(ledger_entries.credit), 0) as credits')
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN ledger_accounts.code = ? AND COALESCE(original_transactions.type, ledger_transactions.type) = ? THEN ledger_entries.credit - ledger_entries.debit ELSE 0 END), 0) as ftth_service_revenue',
                    [LedgerAccountCode::InternetServiceRevenue->value, LedgerTransactionType::FtthBill->value],
                )
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN ledger_accounts.code = ? AND COALESCE(original_transactions.type, ledger_transactions.type) = ? THEN ledger_entries.credit - ledger_entries.debit ELSE 0 END), 0) as cg_service_revenue',
                    [LedgerAccountCode::InternetServiceRevenue->value, LedgerTransactionType::CgBill->value],
                )
                ->groupBy('ledger_accounts.id', 'ledger_accounts.code', 'ledger_accounts.name')
                ->get();
            $systemAccountTotalsByCode = $systemAccountTotals->keyBy('code');
            $internetServiceRevenueTotals = $systemAccountTotalsByCode->get(
                LedgerAccountCode::InternetServiceRevenue->value,
            );
            $adjustmentTotals = $systemAccountTotalsByCode->get(LedgerAccountCode::AdjustmentExpense->value);
            $adjustments = [
                'debits' => $this->money($adjustmentTotals->debits ?? 0),
                'credits' => $this->money($adjustmentTotals->credits ?? 0),
                'net_expense' => $this->money(
                    ($adjustmentTotals->debits ?? 0) - ($adjustmentTotals->credits ?? 0),
                ),
            ];

            return [
                'as_of' => $asOf->toISOString(),
                'top_up_cards' => [
                    'activated' => [
                        'count' => (int) $cardTotals->activated_count,
                        'amount' => $this->money($cardTotals->activated_amount),
                    ],
                    'active' => [
                        'count' => (int) $cardTotals->active_count,
                        'amount' => $this->money($cardTotals->active_amount),
                    ],
                    'redeemed' => [
                        'count' => (int) $cardTotals->redeemed_count,
                        'amount' => $this->money($cardTotals->redeemed_amount),
                    ],
                    'blocked' => [
                        'count' => (int) $cardTotals->blocked_count,
                        'amount' => $this->money($cardTotals->blocked_amount),
                    ],
                    'expired' => [
                        'count' => (int) $cardTotals->expired_count,
                        'amount' => $this->money($cardTotals->expired_amount),
                    ],
                ],
                'system_money' => [
                    'wallet_balance' => $this->money($walletBalance),
                    'accounts' => collect($systemMoneyAccountCodes)
                        ->map(
                            function (LedgerAccountCode $code) use ($systemAccountTotalsByCode): array {
                                $account = $systemAccountTotalsByCode->get($code->value);
                                $debits = $account->debits ?? 0;
                                $credits = $account->credits ?? 0;
                                $balance = match ($code->accountType()) {
                                    LedgerAccountType::Asset, LedgerAccountType::Expense => $debits - $credits,
                                    LedgerAccountType::Liability,
                                    LedgerAccountType::Equity,
                                    LedgerAccountType::Revenue => $credits - $debits,
                                };

                                return [
                                    'code' => $code->value,
                                    'name' => (string) ($account->name ?? $code->label()),
                                    'balance' => $this->money($balance),
                                ];
                            },
                        )
                        ->values()
                        ->all(),
                    'internet_service_revenue' => [
                        'ftth' => $this->money($internetServiceRevenueTotals->ftth_service_revenue ?? 0),
                        'cg' => $this->money($internetServiceRevenueTotals->cg_service_revenue ?? 0),
                    ],
                    'adjustments' => [
                        'debits' => $adjustments['debits'],
                        'credits' => $adjustments['credits'],
                    ],
                ],
                'adjustments' => $adjustments,
            ];
        });
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
