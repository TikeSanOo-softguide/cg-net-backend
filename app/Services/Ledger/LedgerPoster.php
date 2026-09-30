<?php

namespace App\Services\Ledger;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletActorType;
use App\Enums\WalletStatus;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class LedgerPoster
{
    /**
     * Ensure system ledger accounts exist (idempotent).
     */
    public function ensureSystemAccounts(): void
    {
        foreach (LedgerAccountCode::cases() as $code) {
            LedgerAccount::query()->firstOrCreate(
                ['code' => $code->value],
                [
                    'name' => $code->label(),
                    'type' => $code->accountType(),
                    'wallet_id' => null,
                    'is_postable' => true,
                    'is_active' => true,
                ],
            );
        }
    }

    public function ensureCustomerLiabilityAccount(Wallet $wallet): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            ['wallet_id' => $wallet->id],
            [
                'code' => 'CUST-'.$wallet->id,
                'name' => 'Customer wallet #'.$wallet->id,
                'type' => LedgerAccountType::Liability,
                'is_postable' => true,
                'is_active' => true,
            ],
        );
    }

    public function systemAccount(LedgerAccountCode $code): LedgerAccount
    {
        $this->ensureSystemAccounts();

        return LedgerAccount::query()->where('code', $code->value)->firstOrFail();
    }

    /**
     * Debit customer liability and credit a contra account (spend / pay).
     */
    public function debitWallet(
        Wallet $wallet,
        int $amount,
        LedgerAccountCode $contraAccount,
        LedgerTransactionType $type,
        LedgerTransactionStatus $status,
        string $idempotencyKey,
        ?WalletActorType $actorType = null,
        ?int $actorId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $transactionNo = null,
        ?int $reversalOf = null,
    ): LedgerTransaction {
        return $this->post(
            wallet: $wallet,
            amount: $amount,
            type: $type,
            status: $status,
            idempotencyKey: $idempotencyKey,
            lines: [
                ['account' => 'customer', 'debit' => $amount, 'credit' => 0],
                ['account' => $contraAccount, 'debit' => 0, 'credit' => $amount],
            ],
            actorType: $actorType,
            actorId: $actorId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            transactionNo: $transactionNo,
            reversalOf: $reversalOf,
        );
    }

    /**
     * Credit customer liability and debit a contra account (top-up / refund).
     */
    public function creditWallet(
        Wallet $wallet,
        int $amount,
        LedgerAccountCode $contraAccount,
        LedgerTransactionType $type,
        LedgerTransactionStatus $status,
        string $idempotencyKey,
        ?WalletActorType $actorType = null,
        ?int $actorId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $transactionNo = null,
        ?int $reversalOf = null,
    ): LedgerTransaction {
        return $this->post(
            wallet: $wallet,
            amount: $amount,
            type: $type,
            status: $status,
            idempotencyKey: $idempotencyKey,
            lines: [
                ['account' => $contraAccount, 'debit' => $amount, 'credit' => 0],
                ['account' => 'customer', 'debit' => 0, 'credit' => $amount],
            ],
            actorType: $actorType,
            actorId: $actorId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            transactionNo: $transactionNo,
            reversalOf: $reversalOf,
        );
    }

    /**
     * @param  list<array{account: LedgerAccountCode|'customer'|LedgerAccount, debit?: int, credit?: int}>  $lines
     */
    public function post(
        Wallet $wallet,
        int $amount,
        LedgerTransactionType $type,
        LedgerTransactionStatus $status,
        string $idempotencyKey,
        array $lines,
        ?WalletActorType $actorType = null,
        ?int $actorId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $transactionNo = null,
        ?int $reversalOf = null,
    ): LedgerTransaction {
        if ($amount < 1) {
            throw new InvalidArgumentException('Ledger amount must be at least 1.');
        }

        if (count($lines) < 2) {
            throw new InvalidArgumentException('A ledger posting requires at least two lines.');
        }

        $existing = LedgerTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use (
            $wallet,
            $amount,
            $type,
            $status,
            $idempotencyKey,
            $lines,
            $actorType,
            $actorId,
            $ipAddress,
            $userAgent,
            $transactionNo,
            $reversalOf,
        ) {
            /** @var Wallet $locked */
            $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== WalletStatus::Active) {
                throw ValidationException::withMessages([
                    'wallet' => ['Wallet is not active.'],
                ]);
            }

            $customerAccount = $this->ensureCustomerLiabilityAccount($locked);
            $resolvedLines = [];
            $totalDebit = 0;
            $totalCredit = 0;
            $walletDelta = 0; // liability: credit increases balance, debit decreases

            foreach ($lines as $index => $line) {
                $debit = (int) ($line['debit'] ?? 0);
                $credit = (int) ($line['credit'] ?? 0);

                if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                    throw new InvalidArgumentException('Each ledger line must be a debit or a credit, not both or neither.');
                }

                $account = $this->resolveAccount($line['account'], $customerAccount);

                if (! $account->is_postable || ! $account->is_active) {
                    throw new RuntimeException("Ledger account {$account->code} is not postable.");
                }

                $totalDebit += $debit;
                $totalCredit += $credit;

                if ($account->isCustomerLiability() && (int) $account->wallet_id === (int) $locked->id) {
                    $walletDelta += $credit - $debit;
                }

                $resolvedLines[] = [
                    'account' => $account,
                    'debit' => $debit,
                    'credit' => $credit,
                    'line_no' => $index + 1,
                ];
            }

            if ($totalDebit !== $totalCredit || $totalDebit !== $amount) {
                throw new RuntimeException('Ledger posting is unbalanced or does not match the transaction amount.');
            }

            $before = (int) $locked->balance;
            $after = $before + $walletDelta;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient wallet balance.'],
                ]);
            }

            $locked->balance = $after;
            $locked->incrementVersion();
            $locked->save();

            $transaction = LedgerTransaction::query()->create([
                'wallet_id' => $locked->id,
                'transaction_no' => $transactionNo ?? $this->transactionNo($type),
                'type' => $type,
                'status' => $status,
                'amount' => $amount,
                'idempotency_key' => $idempotencyKey,
                'reversal_of' => $reversalOf,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'posted_at' => now(),
            ]);

            foreach ($resolvedLines as $line) {
                /** @var LedgerAccount $account */
                $account = $line['account'];
                $isLiabilityLine = $account->isCustomerLiability() && (int) $account->wallet_id === (int) $locked->id;

                LedgerEntry::query()->create([
                    'ledger_transaction_id' => $transaction->id,
                    'ledger_account_id' => $account->id,
                    'wallet_id' => $isLiabilityLine ? $locked->id : null,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'line_no' => $line['line_no'],
                    'balance_before' => $isLiabilityLine ? $before : null,
                    'balance_after' => $isLiabilityLine ? $after : null,
                ]);
            }

            return $transaction->refresh()->load(['entries.ledgerAccount']);
        });
    }

    /**
     * Mark a posted transaction completed without creating new entries.
     */
    public function markCompleted(LedgerTransaction $transaction): void
    {
        if ($transaction->status === LedgerTransactionStatus::Completed) {
            return;
        }

        $transaction->update([
            'status' => LedgerTransactionStatus::Completed,
            'posted_at' => $transaction->posted_at ?? now(),
        ]);
    }

    /**
     * Mark a posted transaction failed without mutating entries (use a reversing post for money).
     */
    public function markFailed(LedgerTransaction $transaction): void
    {
        if ($transaction->status === LedgerTransactionStatus::Failed) {
            return;
        }

        $transaction->update(['status' => LedgerTransactionStatus::Failed]);
    }

    private function resolveAccount(LedgerAccountCode|LedgerAccount|string $account, LedgerAccount $customerAccount): LedgerAccount
    {
        if ($account instanceof LedgerAccount) {
            return $account;
        }

        if ($account === 'customer') {
            return $customerAccount;
        }

        if ($account instanceof LedgerAccountCode) {
            return $this->systemAccount($account);
        }

        $found = LedgerAccount::query()->where('code', $account)->first();
        if (! $found) {
            throw new InvalidArgumentException("Unknown ledger account [{$account}].");
        }

        return $found;
    }

    private function transactionNo(LedgerTransactionType $type): string
    {
        $prefix = match ($type) {
            LedgerTransactionType::FtthBill => 'FTTH',
            LedgerTransactionType::Refund => 'REFUND',
            LedgerTransactionType::Topup => 'TOPUP',
            LedgerTransactionType::WifiPackage => 'WIFI',
            LedgerTransactionType::Adjustment => 'ADJ',
        };

        return $prefix.'-'.Str::ulid();
    }
}
