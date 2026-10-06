<?php

namespace App\Services\TopUpCard;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\TopUpCardStatus;
use App\Enums\UserStatus;
use App\Enums\WalletActorType;
use App\Enums\WalletStatus;
use App\Models\LedgerTransaction;
use App\Models\TopUpCard;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use App\Support\TopUpCardPin;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class TopUpCardRedemptionService
{
    public function __construct(private readonly LedgerPoster $ledger) {}

    /**
     * @return array{http_status: int, body: array<string, mixed>, headers: array<string, string>}
     */
    public function redeem(
        User $user,
        string $phone,
        string $pin,
        string $idempotencyKey,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $ipAddress ??= 'unknown';
        $pinDigest = TopUpCardPin::hash($pin);

        try {
            return DB::transaction(function () use (
                $user,
                $phone,
                $pin,
                $pinDigest,
                $idempotencyKey,
                $ipAddress,
                $userAgent,
            ): array {
                $userId = $user->getKey();
                $recipientId = User::query()->where('phone', $phone)->value('id');

                if (!$recipientId) {
                    return $this->response(404, [
                        'success' => false,
                        'message' => 'Account not found.',
                    ]);
                }

                $users = User::query()
                    ->whereKey(array_values(array_unique([$userId, $recipientId])))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $user = $users->get($userId);
                $account = $users->get($recipientId);

                if (!$user || $user->status !== UserStatus::Active) {
                    return $this->response(403, [
                        'success' => false,
                        'message' => 'Account is not available.',
                    ]);
                }

                if (!$account || $account->status !== UserStatus::Active) {
                    return $this->response(403, [
                        'success' => false,
                        'message' => 'Account is not available.',
                    ]);
                }

                $wallet = $account->wallet()->lockForUpdate()->first();

                if (!$wallet) {
                    return $this->response(404, [
                        'success' => false,
                        'message' => 'Wallet not found.',
                    ]);
                }

                if ($wallet->status !== WalletStatus::Active) {
                    return $this->response(403, [
                        'success' => false,
                        'message' => 'Wallet is not active.',
                    ]);
                }

                $rateLimitResponse = $this->rateLimitResponse((string) $userId, $ipAddress);

                if ($rateLimitResponse !== null) {
                    return $rateLimitResponse;
                }

                $card = TopUpCard::query()->where('pin', $pinDigest)->lockForUpdate()->first();

                $pinMatches = TopUpCardPin::check($pin, $card?->pin ?? str_repeat('0', 64));

                if (!$card || !$pinMatches) {
                    $this->registerPinFailure((string) $userId, $ipAddress);

                    return $this->response(400, [
                        'success' => false,
                        'message' => 'Invalid or unavailable top-up card.',
                    ]);
                }

                $existing = $this->findExistingByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    return $this->existingResponse($existing, $wallet, $card);
                }

                if (!$this->cardIsRedeemable($card)) {
                    $this->registerPinFailure((string) $userId, $ipAddress);

                    return $this->response(400, [
                        'success' => false,
                        'message' => 'Invalid or unavailable top-up card.',
                    ]);
                }

                $amount = (int) $card->amount;
                $balanceBefore = (int) $wallet->balance;

                if (
                    $amount < 1 ||
                    $balanceBefore < 0 ||
                    $amount > (int) config('top_up_cards.redemption.max_wallet_balance') - $balanceBefore
                ) {
                    return $this->response(409, [
                        'success' => false,
                        'message' => 'This action could not be completed.',
                    ]);
                }

                $transaction = $this->ledger->creditWallet(
                    wallet: $wallet,
                    amount: $amount,
                    contraAccount: LedgerAccountCode::CashTopup,
                    type: LedgerTransactionType::Topup,
                    status: LedgerTransactionStatus::Completed,
                    idempotencyKey: $idempotencyKey,
                    actorType: WalletActorType::User,
                    actorId: $userId,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                );

                if (!$transaction->wasRecentlyCreated) {
                    return $this->existingResponse(
                        $transaction->load(['topUpCard', 'entries.ledgerAccount']),
                        $wallet,
                        $card,
                    );
                }

                $balanceAfter = (int) $wallet->refresh()->balance;

                $card->status = TopUpCardStatus::Used;
                $card->redeemed_at = now();
                $card->redeemed_by = $userId;
                $card->ledger_transaction_id = $transaction->id;
                $card->save();

                return $this->response(200, [
                    'success' => true,
                    'message' => 'Top-up successful.',
                    'amount' => $amount,
                    'balance' => $balanceAfter,
                    'transaction_no' => $transaction->transaction_no,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return $this->recoverFromIdempotencyRace(
                user: $user,
                phone: $phone,
                pinDigest: $pinDigest,
                idempotencyKey: $idempotencyKey,
            );
        }
    }

    protected function cardIsRedeemable(TopUpCard $card): bool
    {
        return $card->status === TopUpCardStatus::Active &&
            $card->redeemed_at === null &&
            $card->ledger_transaction_id === null &&
            !$card->expires_at?->copy()->endOfDay()->isPast();
    }

    protected function findExistingByIdempotencyKey(string $idempotencyKey): ?LedgerTransaction
    {
        return LedgerTransaction::query()
            ->with(['topUpCard', 'entries.ledgerAccount'])
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Concurrent same-key inserts lose the race on the unique idempotency index.
     * Replay the committed transaction instead of returning a bare conflict.
     *
     * @return array{http_status: int, body: array<string, mixed>, headers: array<string, string>}
     */
    protected function recoverFromIdempotencyRace(
        User $user,
        string $phone,
        string $pinDigest,
        string $idempotencyKey,
    ): array {
        if ($user->status !== UserStatus::Active) {
            return $this->response(403, [
                'success' => false,
                'message' => 'Account is not available.',
            ]);
        }

        $existing = $this->findExistingByIdempotencyKey($idempotencyKey);

        if ($existing === null) {
            return $this->response(409, [
                'success' => false,
                'message' => 'This request could not be completed.',
            ]);
        }

        $recipientId = User::query()->where('phone', $phone)->value('id');
        $wallet = $recipientId ? Wallet::query()->where('user_id', $recipientId)->first() : null;
        $card = TopUpCard::query()->where('pin', $pinDigest)->first();

        if ($wallet === null || $card === null) {
            return $this->response(409, [
                'success' => false,
                'message' => 'This request could not be completed.',
            ]);
        }

        return $this->existingResponse($existing, $wallet, $card);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>, headers: array<string, string>}|null
     */
    protected function rateLimitResponse(string $userId, string $ipAddress): ?array
    {
        $userFailureKey = $this->userFailureKey($userId);
        $ipFailureKey = $this->ipFailureKey($ipAddress);

        $userRateLimited = RateLimiter::tooManyAttempts(
            $userFailureKey,
            (int) config('top_up_cards.redemption.failed_pin_limits.user.max_attempts'),
        );
        $ipRateLimited = RateLimiter::tooManyAttempts(
            $ipFailureKey,
            (int) config('top_up_cards.redemption.failed_pin_limits.ip.max_attempts'),
        );

        if (!$userRateLimited && !$ipRateLimited) {
            return null;
        }

        $retryAfter = max(
            $userRateLimited ? RateLimiter::availableIn($userFailureKey) : 0,
            $ipRateLimited ? RateLimiter::availableIn($ipFailureKey) : 0,
        );

        $message = match (true) {
            $userRateLimited && $ipRateLimited => 'Too many attempts, retry after 1 hour.',
            $userRateLimited => 'Too many attempts, retry after 30 minutes.',
            default => 'Too many attempts, retry after 1 hour.',
        };

        return $this->response(429, ['message' => $message], ['Retry-After' => (string) $retryAfter]);
    }

    protected function registerPinFailure(string $userId, string $ipAddress): void
    {
        RateLimiter::hit(
            $this->userFailureKey($userId),
            (int) config('top_up_cards.redemption.failed_pin_limits.user.decay_seconds'),
        );
        RateLimiter::hit(
            $this->ipFailureKey($ipAddress),
            (int) config('top_up_cards.redemption.failed_pin_limits.ip.decay_seconds'),
        );
    }

    protected function userFailureKey(string $userId): string
    {
        return 'top-up-card-pin-failure:user:' . hash('sha256', $userId);
    }

    protected function ipFailureKey(string $ipAddress): string
    {
        return 'top-up-card-pin-failure:ip:' . hash('sha256', $ipAddress);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>, headers: array<string, string>}
     */
    protected function existingResponse(LedgerTransaction $existing, Wallet $wallet, TopUpCard $card): array
    {
        $entries = $existing->entries;
        $liabilityEntry = $existing->walletLiabilityEntry();
        $hasCashTopupDebit = $entries->contains(
            fn($entry): bool => $entry->ledgerAccount?->code === LedgerAccountCode::CashTopup->value &&
                (int) $entry->debit === (int) $existing->amount &&
                (int) $entry->credit === 0,
        );

        if (
            (int) $existing->wallet_id !== (int) $wallet->id ||
            $existing->type !== LedgerTransactionType::Topup ||
            $existing->status !== LedgerTransactionStatus::Completed ||
            $existing->topUpCard?->getKey() !== $card->getKey() ||
            $liabilityEntry === null ||
            (int) $liabilityEntry->debit !== 0 ||
            (int) $liabilityEntry->credit !== (int) $existing->amount ||
            $liabilityEntry->balance_after === null ||
            $entries->count() !== 2 ||
            !$hasCashTopupDebit ||
            (int) $entries->sum('debit') !== (int) $existing->amount ||
            (int) $entries->sum('credit') !== (int) $existing->amount
        ) {
            return $this->response(409, [
                'success' => false,
                'message' => 'This request could not be completed.',
            ]);
        }

        return $this->response(200, [
            'success' => true,
            'message' => 'Top-up already processed.',
            'amount' => $existing->amount,
            'balance' => (int) $wallet->fresh()->balance,
            'transaction_no' => $existing->transaction_no,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @return array{http_status: int, body: array<string, mixed>, headers: array<string, string>}
     */
    private function response(int $httpStatus, array $body, array $headers = []): array
    {
        return [
            'http_status' => $httpStatus,
            'body' => $body,
            'headers' => $headers,
        ];
    }
}
