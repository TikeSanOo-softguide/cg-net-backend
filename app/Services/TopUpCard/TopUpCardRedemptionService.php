<?php

namespace App\Services\TopUpCard;

use App\Enums\TopUpCardStatus;
use App\Enums\UserStatus;
use App\Enums\WalletActorType;
use App\Enums\WalletEntryType;
use App\Enums\WalletStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\TopUpCard;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\WalletTransaction;
use App\Support\TopUpCardPin;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class TopUpCardRedemptionService
{
    private const MAX_WALLET_BALANCE = 2147483647;

    private const FAILED_PIN_LIMIT_PER_USER = 7;

    private const FAILED_PIN_LIMIT_PER_IP = 15;

    private const FAILED_PIN_DECAY_SECONDS_PER_USER = 30 * 60;

    private const FAILED_PIN_DECAY_SECONDS_PER_IP = 60 * 60;

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

                $existing = WalletTransaction::query()
                    ->with(['topUpCard', 'walletEntry'])
                    ->where('wallet_id', $wallet->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    return $this->existingResponse($existing, $card);
                }

                if (
                    $card->status !== TopUpCardStatus::Active ||
                    $card->redeemed_at !== null ||
                    $card->wallet_transaction_id !== null ||
                    $card->expires_at?->copy()->endOfDay()->isPast()
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
                    return $this->response(409, ['message' => 'This action could not be completed.']);
                }

                $balanceAfter = $balanceBefore + $amount;
                $now = now();
                $transaction = WalletTransaction::query()->create([
                    'wallet_id' => $wallet->id,
                    'transaction_no' => 'TOPUP-' . Str::ulid(),
                    'type' => WalletTransactionType::Topup,
                    'status' => WalletTransactionStatus::Completed,
                    'amount' => $amount,
                    'idempotency_key' => $idempotencyKey,
                    'actor_type' => WalletActorType::User,
                    'actor_id' => $actor->id,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);

                $wallet->balance = $balanceAfter;
                $wallet->incrementVersion();
                $wallet->updated_by = $actor->id;
                $wallet->save();

                WalletEntry::query()->create([
                    'wallet_transaction_id' => $transaction->id,
                    'wallet_id' => $wallet->id,
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'type' => WalletEntryType::Credit,
                ]);

                $card->status = TopUpCardStatus::Used;
                $card->redeemed_at = $now;
                $card->redeemed_by = $actor->id;
                $card->wallet_transaction_id = $transaction->id;
                $card->save();

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
     * @return array{http_status: int, body: array<string, mixed>, headers: array<string, string>}
     */
    protected function existingResponse(WalletTransaction $existing, TopUpCard $card): array
    {
        if (
            $existing->type !== WalletTransactionType::Topup ||
            $existing->status !== WalletTransactionStatus::Completed ||
            $existing->topUpCard?->getKey() !== $card->getKey() ||
            $existing->walletEntry === null
        ) {
            return $this->response(409, ['message' => 'Idempotency key has already been used.']);
        }

        return $this->response(200, [
            'message' => 'Top-up already processed.',
            'amount' => $existing->amount,
            'balance' => $existing->walletEntry->balance_after,
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