<?php

namespace Tests\Feature;

use App\Enums\TopUpCardStatus;
use App\Enums\UserStatus;
use App\Enums\WalletEntryType;
use App\Enums\WalletStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\TopUpCard;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use App\Support\TopUpCardPin;
use Tests\TestCase;

class TopUpCardRedeemApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_serial_check_returns_a_message_for_each_card_status(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('serial-check')->plainTextToken;
        $activeCard = TopUpCard::factory()->create(['status' => TopUpCardStatus::Active]);
        $usedCard = TopUpCard::factory()->create(['status' => TopUpCardStatus::Used]);
        $expiredCard = TopUpCard::factory()->create(['status' => TopUpCardStatus::Expired]);
        $blockedCard = TopUpCard::factory()->create(['status' => TopUpCardStatus::Blocked]);
        $pendingCard = TopUpCard::factory()->create(['status' => TopUpCardStatus::Pending]);

        $this->withToken($token)
            ->postJson('/api/redeem/check-serial-no', ['serial_no' => $activeCard->serial_no])
            ->assertOk()
            ->assertExactJson(['message' => 'This top-up card is valid.']);

        $this->postJson('/api/redeem/check-serial-no', ['serial_no' => $usedCard->serial_no])
            ->assertStatus(400)
            ->assertExactJson(['message' => 'This top-up card has already been used.']);

        $this->postJson('/api/redeem/check-serial-no', ['serial_no' => $expiredCard->serial_no])
            ->assertStatus(400)
            ->assertExactJson(['message' => 'This top-up card has expired.']);

        $this->postJson('/api/redeem/check-serial-no', ['serial_no' => $blockedCard->serial_no])
            ->assertStatus(400)
            ->assertExactJson(['message' => 'This top-up card has been blocked.']);

        $this->postJson('/api/redeem/check-serial-no', ['serial_no' => $pendingCard->serial_no])
            ->assertStatus(400)
            ->assertExactJson(['message' => 'This top-up card is invalid.']);

        $this->postJson('/api/redeem/check-serial-no', ['serial_no' => 'UNKNOWN-SERIAL'])
            ->assertStatus(400)
            ->assertExactJson(['message' => 'This top-up card is invalid.']);
    }

    public function test_redeeming_a_card_credits_the_authenticated_wallet_once(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $pin = '1234567890123456';
        $idempotencyKey = 'top-up-success-001';
        $card = TopUpCard::factory()->create([
            'amount' => 500,
            'expires_at' => now()->addDay()->toDateString(),
            'pin' => TopUpCardPin::hash($pin),
        ]);

        $response = $this->withToken($token)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => $idempotencyKey,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Top-up successful.')
            ->assertJsonPath('amount', 500)
            ->assertJsonPath('balance', 1500);

        $transactionNo = $response->json('transaction_no');
        $transaction = WalletTransaction::query()->where('transaction_no', $transactionNo)->firstOrFail();
        $entry = WalletEntry::query()->where('wallet_transaction_id', $transaction->id)->firstOrFail();

        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(2, $wallet->fresh()->version);
        $this->assertSame(WalletTransactionType::Topup, $transaction->type);
        $this->assertSame(WalletTransactionStatus::Completed, $transaction->status);
        $this->assertSame($idempotencyKey, $transaction->idempotency_key);
        $this->assertSame(WalletEntryType::Credit, $entry->type);
        $this->assertSame(1000, $entry->balance_before);
        $this->assertSame(1500, $entry->balance_after);
        $this->assertSame(TopUpCardStatus::Used, $card->fresh()->status);
        $this->assertSame($user->id, $card->fresh()->redeemed_by);

        $this->postJson('/api/redeem/top-up-account', [
            'phone' => $user->phone,
            'pin' => $pin,
            'idempotency_key' => $idempotencyKey,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Top-up already processed.')
            ->assertJsonPath('balance', 1500)
            ->assertJsonPath('transaction_no', $transactionNo);

        $otherPin = '1234567890123457';
        $otherCard = TopUpCard::factory()->create(['pin' => TopUpCardPin::hash($otherPin)]);

        $this->postJson('/api/redeem/top-up-account', [
            'phone' => $user->phone,
            'pin' => $otherPin,
            'idempotency_key' => $idempotencyKey,
        ])->assertStatus(409);

        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Active, $otherCard->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('wallet_entries', 1);
    }

    public function test_invalid_pin_does_not_change_card_or_wallet(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $card = TopUpCard::factory()->create(['pin' => TopUpCardPin::hash('correct-pin')]);

        $this->withToken($token)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $user->phone,
                'pin' => 'incorrect-pin',
                'idempotency_key' => 'top-up-bad-pin-001',
            ])
            ->assertBadRequest()
            ->assertExactJson(['message' => 'Invalid or unavailable top-up card.']);

        $this->assertSame(1000, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Active, $card->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_pin_limit_counts_only_failed_pin_attempts(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $pin = '1234567890123456';
        $card = TopUpCard::factory()->create(['pin' => TopUpCardPin::hash($pin)]);
        $userFailureKey = 'top-up-card-pin-failure:user:' . hash('sha256', (string) $user->id);
        $ipFailureKey = 'top-up-card-pin-failure:ip:' . hash('sha256', (string) request()->ip());

        RateLimiter::clear($userFailureKey);
        RateLimiter::clear($ipFailureKey);

        $this->withToken($token)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'pin-limit-valid-001',
            ])
            ->assertOk();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/redeem/top-up-account', [
                'phone' => $user->phone,
                'pin' => 'incorrect-pin-' . $attempt,
                'idempotency_key' => 'pin-limit-failure-' . $attempt,
            ])->assertBadRequest();
        }

        $this->postJson('/api/redeem/top-up-account', [
            'phone' => $user->phone,
            'pin' => 'another-incorrect-pin',
            'idempotency_key' => 'pin-limit-final-001',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many incorrect PIN attempts. Try again later.');

        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Used, $card->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_phone_selects_the_recipient_account_wallet(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();
        $token = $user->createToken('top-up')->plainTextToken;
        $requesterWallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $recipientWallet = Wallet::factory()->create(['user_id' => $recipient->id, 'balance' => 250]);
        $pin = '1234567890123456';
        $card = TopUpCard::factory()->create([
            'amount' => 500,
            'pin' => TopUpCardPin::hash($pin),
        ]);
        $userAgent = 'TopUpRedemptionTest/1.0';

        $this->withToken($token)
            ->withHeader('User-Agent', $userAgent)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $recipient->phone,
                'pin' => $pin,
                'idempotency_key' => 'top-up-recipient-001',
            ])
            ->assertOk()
            ->assertJsonPath('amount', 500)
            ->assertJsonPath('balance', 750);

        $this->assertSame(1000, $requesterWallet->fresh()->balance);
        $this->assertSame(750, $recipientWallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Used, $card->fresh()->status);
        $this->assertSame($user->id, $card->fresh()->redeemed_by);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $recipientWallet->id,
            'actor_id' => $user->id,
            'idempotency_key' => 'top-up-recipient-001',
            'user_agent' => $userAgent,
        ]);
    }

    public function test_frozen_wallet_cannot_redeem_a_card(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up')->plainTextToken;
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => 1000,
            'status' => WalletStatus::Frozen,
        ]);
        $pin = '1234567890123456';
        $card = TopUpCard::factory()->create(['pin' => TopUpCardPin::hash($pin)]);

        $this->withToken($token)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'top-up-frozen-wallet-001',
            ])
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Wallet is not active.',
            ]);

        $this->assertSame(1000, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Active, $card->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_missing_wallet_returns_not_found_without_checking_pin(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $user->phone,
                'pin' => 'incorrect-pin',
                'idempotency_key' => 'top-up-no-wallet-001',
            ])
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'Wallet not found.',
            ]);
    }

    public function test_used_blocked_pending_and_expired_cards_cannot_be_redeemed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $cardCases = [
            ['status' => TopUpCardStatus::Used],
            ['status' => TopUpCardStatus::Blocked],
            ['status' => TopUpCardStatus::Pending],
            ['status' => TopUpCardStatus::Active, 'expires_at' => now()->subDay()->toDateString()],
        ];

        foreach ($cardCases as $index => $attributes) {
            $pin = 'known-pin-' . $index;
            $card = TopUpCard::factory()->create($attributes + ['pin' => TopUpCardPin::hash($pin)]);

            $this->withToken($token)
                ->postJson('/api/redeem/top-up-account', [
                    'phone' => $user->phone,
                    'pin' => $pin,
                    'idempotency_key' => 'top-up-card-status-' . $index,
                ])
                ->assertBadRequest()
                ->assertExactJson(['message' => 'Invalid or unavailable top-up card.']);
        }

        $this->assertSame(1000, $wallet->fresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_suspended_user_cannot_redeem_with_an_existing_token(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Suspended]);
        $token = $user->createToken('top-up')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $pin = '1234567890123456';
        $card = TopUpCard::factory()->create(['pin' => TopUpCardPin::hash($pin)]);

        $this->withToken($token)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'top-up-suspended-user-001',
            ])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Account is not available.']);

        $this->assertSame(1000, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Active, $card->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_wallet_balance_overflow_does_not_consume_card(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 2147483647]);
        $pin = '1234567890123456';
        $card = TopUpCard::factory()->create(['pin' => TopUpCardPin::hash($pin)]);

        $this->withToken($token)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'top-up-overflow-001',
            ])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Top-up could not be completed.']);

        $this->assertSame(2147483647, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Active, $card->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}