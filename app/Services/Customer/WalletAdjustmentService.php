<?php

namespace App\Services\Customer;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletActorType;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Ledger\LedgerPoster;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletAdjustmentService
{
    public function __construct(private readonly LedgerPoster $ledger) {}

    public function adjust(
        User $customer,
        string $direction,
        int $amount,
        string $note,
        int $adminId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?int $relatedTransactionId = null,
    ): LedgerTransaction {
        $wallet = $customer->wallet;

        if ($wallet === null) {
            throw ValidationException::withMessages([
                'wallet' => [__('customers.wallet_adjust.no_wallet')],
            ]);
        }

        $this->ledger->ensureSystemAccounts();
        $this->ledger->ensureCustomerLiabilityAccount($wallet);

        $idempotencyKey = sprintf(
            'admin-adjust:%d:%d:%s',
            $adminId,
            $customer->id,
            (string) Str::ulid(),
        );

        $wallet = $wallet->fresh();

        if ($direction === 'debit') {
            return $this->ledger->debitWallet(
                wallet: $wallet,
                amount: $amount,
                contraAccount: LedgerAccountCode::AdjustmentExpense,
                type: LedgerTransactionType::Adjustment,
                status: LedgerTransactionStatus::Completed,
                idempotencyKey: $idempotencyKey,
                actorType: WalletActorType::Admin,
                actorId: $adminId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                relatedTransactionId: $relatedTransactionId,
                note: $note,
            );
        }

        return $this->ledger->creditWallet(
            wallet: $wallet,
            amount: $amount,
            contraAccount: LedgerAccountCode::AdjustmentExpense,
            type: LedgerTransactionType::Adjustment,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: $idempotencyKey,
            actorType: WalletActorType::Admin,
            actorId: $adminId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            relatedTransactionId: $relatedTransactionId,
            note: $note,
        );
    }
}
