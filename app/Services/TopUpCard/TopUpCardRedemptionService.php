<?php

namespace App\Services\TopUpCard;

use App\Enums\TopUpCardStatus;
use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
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
use RuntimeException;

class TopUpCardRedemptionService
{
    private const MAX_WALLET_BALANCE = 2147483647;

    private const FAILED_PIN_LIMIT_PER_USER = 10;

    private const FAILED_PIN_LIMIT_PER_IP = 20;

    private const FAILED_PIN_DECAY_SECONDS_PER_USER = 30 * 60;

    private const FAILED_PIN_DECAY_SECONDS_PER_IP = 60 * 60;

    private const FAILED_SERIAL_LIMIT_PER_USER = 10;

    private const FAILED_SERIAL_LIMIT_PER_IP = 20;

    private const FAILED_SERIAL_DECAY_SECONDS_PER_USER = 30 * 60;

    private const FAILED_SERIAL_DECAY_SECONDS_PER_IP = 60 * 60;

    public function __construct(private readonly LedgerPoster $ledger) {}

    /**
     * @return array{http_status: int, body: array<string, mixed>, headers: array<string, string>}
     */
    public function checkSerialNo(User $user, string $serialNo, ?string $ipAddress = null): array
    {
        $ipAddress ??= 'unknown';
        $userId = (string) $user->getKey();

        $rateLimitResponse = $this->serialRateLimitResponse($userId, $ipAddress);
        if ($rateLimitResponse !== null) {
            return $rateLimitResponse;
        }

        $card = TopUpCard::query()->where('serial_no', $serialNo)->first();
        $status = $card?->effectiveStatus();

        if ($status !== TopUpCardStatus::Active) {
            $this->registerSerialFailure($userId, $ipAddress);
        }

        return $this->response(
            $status === TopUpCardStatus::Active ? 200 : 400,
            ['message' => $this->serialCheckMessage($status)],
        );
    }

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
        try {
            return DB::transaction(function () use ($user, $phone, $pin, $idempotencyKey, $ipAddress, $userAgent): array {
                $actorId = $user->getKey();
                $recipientId = User::query()->where('phone', $phone)->value('id');

                if (!$recipientId) {
                    return $this->response(404, [
                        'success' => false,
                        'message' => 'Account not found.',
                    ]);
                }

                $users = User::query()
                    ->whereKey(array_values(array_unique([$actorId, $recipientId])))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $actor = $users->get($actorId);
                $account = $users->get($recipientId);

                if (!$actor || $actor->status !== UserStatus::Active) {
                    return $this->response(403, ['message' => 'Account is not available.']);
                }

                if (!$account || $account->status !== UserStatus::Active) {
                    return $this->response(403, ['message' => 'Account is not available.']);
                }

                if ((string) $account->phone !== $phone) {
                    return $this->response(404, [
                        'success' => false,
                        'message' => 'Account not found.',
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

                $ipAddress ??= 'unknown';
                $rateLimitResponse = $this->rateLimitResponse((string) $actor->getKey(), $ipAddress);

                if ($rateLimitResponse !== null) {
                    return $rateLimitResponse;
                }

                $card = TopUpCard::query()
                    ->where('pin', TopUpCardPin::hash($pin))
                    ->lockForUpdate()
                    ->first();
                $pinMatches = TopUpCardPin::check($pin, $card?->pin ?? str_repeat('0', 64));

                if (!$card || !$pinMatches) {
                    $this->registerPinFailure((string) $actor->getKey(), $ipAddress);

                    return $this->response(400, ['message' => 'Invalid or unavailable top-up card.']);
                }

                $existing = LedgerTransaction::query()
                    ->with(['topUpCard', 'entries.ledgerAccount'])
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $this->existingResponse($existing, $wallet, $card);
                }

                if (
                    $card->effectiveStatus() !== TopUpCardStatus::Active ||
                    $card->redeemed_at !== null ||
                    $card->ledger_transaction_id !== null
                ) {
                    $this->registerPinFailure((string) $actor->getKey(), $ipAddress);

                    return $this->response(400, ['message' => 'Invalid or unavailable top-up card.']);
                }

                $amount = (int) $card->amount;
                $balanceBefore = (int) $wallet->balance;

                if (
                    $amount < 1 ||
                    $balanceBefore < 0 ||
                    $amount > self::MAX_WALLET_BALANCE - $balanceBefore
                ) {
                    return $this->response(409, ['message' => 'Top-up could not be completed.']);
                }

                $now = now();
                $transaction = $this->ledger->creditWallet(
                    wallet: $wallet,
                    amount: $amount,
                    contraAccount: LedgerAccountCode::CashTopup,
                    type: LedgerTransactionType::Topup,
                    status: LedgerTransactionStatus::Completed,
                    idempotencyKey: $idempotencyKey,
                    actorType: WalletActorType::User,
                    actorId: $actor->id,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                );

                if (! $transaction->wasRecentlyCreated) {
                    return $this->existingResponse(
                        $transaction->load(['topUpCard', 'entries.ledgerAccount']),
                        $wallet,
                        $card,
                    );
                }

                $balanceAfter = (int) $wallet->refresh()->balance;

                $marked = TopUpCard::query()
                    ->whereKey($card->getKey())
                    ->where('status', TopUpCardStatus::Active)
                    ->whereNull('redeemed_at')
                    ->whereNull('ledger_transaction_id')
                    ->update([
                        'status' => TopUpCardStatus::Used,
                        'redeemed_at' => $now,
                        'redeemed_by' => $actor->id,
                        'ledger_transaction_id' => $transaction->id,
                        'updated_at' => $now,
                    ]);

                if ($marked !== 1) {
                    throw new RuntimeException('Top-up card could not be marked as redeemed.');
                }

                return $this->response(200, [
                    'result' => 'success',
                    'message' => 'Top-up successful.',
                    'amount' => $amount,
                    'balance' => $balanceAfter,
                    'transaction_no' => $transaction->transaction_no,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return $this->response(409, ['message' => 'Idempotency key has already been used.']);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'Top-up card could not be marked as redeemed.') {
                throw $exception;
            }

            return $this->response(409, ['message' => 'Top-up could not be completed.']);
        }
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>, headers: array<string, string>}|null
     */
    protected function rateLimitResponse(string $userId, string $ipAddress): ?array
    {
        $userFailureKey = 'top-up-card-pin-failure:user:' . hash('sha256', $userId);
        $ipFailureKey = 'top-up-card-pin-failure:ip:' . hash('sha256', $ipAddress);
        $userRateLimited = RateLimiter::tooManyAttempts($userFailureKey, self::FAILED_PIN_LIMIT_PER_USER);
        $ipRateLimited = RateLimiter::tooManyAttempts($ipFailureKey, self::FAILED_PIN_LIMIT_PER_IP);

        if (!$userRateLimited && !$ipRateLimited) {
            return null;
        }

        $retryAfter = max(
            $userRateLimited ? RateLimiter::availableIn($userFailureKey) : 0,
            $ipRateLimited ? RateLimiter::availableIn($ipFailureKey) : 0,
        );

        $message = match (true) {
            $userRateLimited && $ipRateLimited => 'Both rate limits reached: the authenticated user limit (10 failed top-up attempts per 30 minutes) and the IP limit (20 failed top-up attempts per 1 hour). Check Retry-After before trying again.',
            $userRateLimited => 'Authenticated user rate limit reached: 10 failed top-up attempts. Check Retry-After; the limit lasts up to 30 minutes.',
            default => 'IP rate limit reached: 20 failed top-up attempts from this IP address. Check Retry-After; the limit lasts up to 1 hour.',
        };

        return $this->response(429, ['message' => $message], ['Retry-After' => (string) $retryAfter]);
    }

    protected function registerPinFailure(string $userId, string $ipAddress): void
    {
        $userFailureKey = 'top-up-card-pin-failure:user:' . hash('sha256', $userId);
        $ipFailureKey = 'top-up-card-pin-failure:ip:' . hash('sha256', $ipAddress);

        RateLimiter::hit($userFailureKey, self::FAILED_PIN_DECAY_SECONDS_PER_USER);
        RateLimiter::hit($ipFailureKey, self::FAILED_PIN_DECAY_SECONDS_PER_IP);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>, headers: array<string, string>}|null
     */
    protected function serialRateLimitResponse(string $userId, string $ipAddress): ?array
    {
        $userFailureKey = 'top-up-card-serial-failure:user:' . hash('sha256', $userId);
        $ipFailureKey = 'top-up-card-serial-failure:ip:' . hash('sha256', $ipAddress);
        $userRateLimited = RateLimiter::tooManyAttempts($userFailureKey, self::FAILED_SERIAL_LIMIT_PER_USER);
        $ipRateLimited = RateLimiter::tooManyAttempts($ipFailureKey, self::FAILED_SERIAL_LIMIT_PER_IP);

        if (!$userRateLimited && !$ipRateLimited) {
            return null;
        }

        $retryAfter = max(
            $userRateLimited ? RateLimiter::availableIn($userFailureKey) : 0,
            $ipRateLimited ? RateLimiter::availableIn($ipFailureKey) : 0,
        );

        $message = match (true) {
            $userRateLimited && $ipRateLimited => 'Both rate limits reached: the authenticated user limit (10 failed serial checks per 30 minutes) and the IP limit (20 failed serial checks per 1 hour). Check Retry-After before trying again.',
            $userRateLimited => 'Authenticated user rate limit reached: 10 failed serial checks. Check Retry-After; the limit lasts up to 30 minutes.',
            default => 'IP rate limit reached: 20 failed serial checks from this IP address. Check Retry-After; the limit lasts up to 1 hour.',
        };

        return $this->response(429, ['message' => $message], ['Retry-After' => (string) $retryAfter]);
    }

    protected function registerSerialFailure(string $userId, string $ipAddress): void
    {
        $userFailureKey = 'top-up-card-serial-failure:user:' . hash('sha256', $userId);
        $ipFailureKey = 'top-up-card-serial-failure:ip:' . hash('sha256', $ipAddress);

        RateLimiter::hit($userFailureKey, self::FAILED_SERIAL_DECAY_SECONDS_PER_USER);
        RateLimiter::hit($ipFailureKey, self::FAILED_SERIAL_DECAY_SECONDS_PER_IP);
    }

    protected function serialCheckMessage(?TopUpCardStatus $status): string
    {
        return match ($status) {
            TopUpCardStatus::Active => 'This top-up card is valid.',
            TopUpCardStatus::Used => 'This top-up card has already been used.',
            TopUpCardStatus::Expired => 'This top-up card has expired.',
            TopUpCardStatus::Blocked => 'This top-up card has been blocked.',
            default => 'This top-up card is invalid.',
        };
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>, headers: array<string, string>}
     */
    protected function existingResponse(
        LedgerTransaction $existing,
        Wallet $wallet,
        TopUpCard $card,
    ): array
    {
        $entries = $existing->entries;
        $liabilityEntry = $existing->walletLiabilityEntry();
        $hasCashTopupDebit = $entries->contains(
            fn ($entry): bool => $entry->ledgerAccount?->code === LedgerAccountCode::CashTopup->value &&
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
            ! $hasCashTopupDebit ||
            (int) $entries->sum('debit') !== (int) $existing->amount ||
            (int) $entries->sum('credit') !== (int) $existing->amount
        ) {
            return $this->response(409, ['message' => 'Idempotency key has already been used.']);
        }

        return $this->response(200, [
            'message' => 'Top-up already processed.',
            'amount' => $existing->amount,
            'balance' => $liabilityEntry->balance_after,
            'transaction_no' => $existing->transaction_no,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
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