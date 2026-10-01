<?php

namespace Tests\Feature;

use App\Enums\TopUpCardStatus;
use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\UserStatus;
use App\Enums\WalletStatus;
use App\Models\LedgerEntry;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\TopUpCard;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\TopUpCardRedemptionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Notification;
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

    public function test_serial_check_treats_active_cards_past_expiry_as_expired(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('serial-check-expiry')->plainTextToken;
        $card = TopUpCard::factory()->create([
            'status' => TopUpCardStatus::Active,
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        $this->withToken($token)
            ->postJson('/api/redeem/check-serial-no', ['serial_no' => $card->serial_no])
            ->assertStatus(400)
            ->assertExactJson(['message' => 'This top-up card has expired.']);
    }

    public function test_redeeming_a_card_credits_the_authenticated_wallet_once(): void
    {
        Notification::fake();

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
        $transaction = LedgerTransaction::query()->where('transaction_no', $transactionNo)->firstOrFail();
        $entries = LedgerEntry::query()->where('ledger_transaction_id', $transaction->id)->get();
        $walletEntry = $entries->firstWhere('wallet_id', $wallet->id);
        $clearingEntry = $entries->firstWhere('wallet_id', null);

        Notification::assertSentToTimes($user, TopUpCardRedemptionNotification::class, 1);
        Notification::assertSentTo(
            $user,
            TopUpCardRedemptionNotification::class,
            fn (TopUpCardRedemptionNotification $notification) => $notification->transactionNo === $transactionNo &&
                $notification->amount === 500 &&
                $notification->balance === 1500,
        );

        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(2, $wallet->fresh()->version);
        $this->assertSame(LedgerTransactionType::Topup, $transaction->type);
        $this->assertSame(LedgerTransactionStatus::Completed, $transaction->status);
        $this->assertSame($idempotencyKey, $transaction->idempotency_key);
        $this->assertCount(2, $entries);
        $this->assertNotNull($walletEntry);
        $this->assertSame(0, $walletEntry->debit);
        $this->assertSame(500, $walletEntry->credit);
        $this->assertSame(1000, $walletEntry->balance_before);
        $this->assertSame(1500, $walletEntry->balance_after);
        $this->assertSame(500, (int) $entries->sum('debit'));
        $this->assertSame(500, (int) $entries->sum('credit'));
        $this->assertNotNull($clearingEntry);
        $this->assertSame(500, $clearingEntry->debit);
        $this->assertSame(0, $clearingEntry->credit);
        $this->assertSame(
            LedgerAccountCode::CashTopup->value,
            LedgerAccount::query()->findOrFail($clearingEntry->ledger_account_id)->code,
        );
        $this->assertSame(TopUpCardStatus::Used, $card->fresh()->status);
        $this->assertSame($user->id, $card->fresh()->redeemed_by);
        $this->assertSame($transaction->id, $card->fresh()->ledger_transaction_id);

        $this->postJson('/api/redeem/top-up-account', [
            'phone' => $user->phone,
            'pin' => $pin,
            'idempotency_key' => $idempotencyKey,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Top-up already processed.')
            ->assertJsonPath('balance', 1500)
            ->assertJsonPath('transaction_no', $transactionNo);

        Notification::assertSentToTimes($user, TopUpCardRedemptionNotification::class, 1);

        $otherPin = '1234567890123457';
        $otherCard = TopUpCard::factory()->create(['pin' => TopUpCardPin::hash($otherPin)]);

        $this->postJson('/api/redeem/top-up-account', [
            'phone' => $user->phone,
            'pin' => $otherPin,
            'idempotency_key' => $idempotencyKey,
        ])->assertStatus(409);

        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Active, $otherCard->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_repeated_used_card_pin_counts_toward_user_rate_limit(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up-repeat-pin')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $pin = '1234567890123456';
        $card = TopUpCard::factory()->create([
            'amount' => 500,
            'pin' => TopUpCardPin::hash($pin),
        ]);
        $userFailureKey = 'top-up-card-pin-failure:user:' . hash('sha256', (string) $user->id);
        $ipFailureKey = 'top-up-card-pin-failure:ip:' . hash('sha256', (string) request()->ip());

        RateLimiter::clear($userFailureKey);
        RateLimiter::clear($ipFailureKey);

        $this->withToken($token)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'repeat-pin-initial-001',
            ])
            ->assertOk();

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/api/redeem/top-up-account', [
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'repeat-pin-retry-' . $attempt,
            ])
                ->assertBadRequest()
                ->assertExactJson(['message' => 'Invalid or unavailable top-up card.']);
        }

        $this->postJson('/api/redeem/top-up-account', [
            'phone' => $user->phone,
            'pin' => $pin,
            'idempotency_key' => 'repeat-pin-limited-001',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Authenticated user rate limit reached: 10 failed top-up attempts. Check Retry-After; the limit lasts up to 30 minutes.');

        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Used, $card->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 1);
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
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
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

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/redeem/top-up-account', [
                'phone' => $user->phone,
                'pin' => 'incorrect-pin-' . $attempt,
                'idempotency_key' => 'pin-limit-failure-' . $attempt,
            ])->assertBadRequest();
        }

        $limitedResponse = $this->postJson('/api/redeem/top-up-account', [
            'phone' => $user->phone,
            'pin' => 'another-incorrect-pin',
            'idempotency_key' => 'pin-limit-final-001',
        ])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'Authenticated user rate limit reached: 10 failed top-up attempts. Check Retry-After; the limit lasts up to 30 minutes.');

        $this->assertGreaterThan(0, (int) $limitedResponse->headers->get('Retry-After'));
        $this->assertLessThanOrEqual(30 * 60, (int) $limitedResponse->headers->get('Retry-After'));

        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Used, $card->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 1);
    }

    public function test_ip_rate_limit_blocks_after_twenty_failed_pin_attempts(): void
    {
        $users = User::factory()->count(5)->create();
        $ipFailureKey = 'top-up-card-pin-failure:ip:' . hash('sha256', (string) request()->ip());

        RateLimiter::clear($ipFailureKey);

        foreach ($users as $user) {
            RateLimiter::clear('top-up-card-pin-failure:user:' . hash('sha256', (string) $user->id));
            Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        }

        for ($userIndex = 0; $userIndex < 4; $userIndex++) {
            $user = $users[$userIndex];
            $token = $user->createToken('top-up-ip-limit')->plainTextToken;

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->withToken($token)
                    ->postJson('/api/redeem/top-up-account', [
                        'phone' => $user->phone,
                        'pin' => 'invalid-ip-pin-' . $userIndex . '-' . $attempt,
                        'idempotency_key' => 'ip-limit-' . $userIndex . '-' . $attempt,
                    ])
                    ->assertBadRequest();
            }
        }

        $blockedUser = $users[4];
        $blockedToken = $blockedUser->createToken('top-up-ip-limit')->plainTextToken;
        $limitedResponse = $this->withToken($blockedToken)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $blockedUser->phone,
                'pin' => 'invalid-ip-limit-final',
                'idempotency_key' => 'ip-limit-final-001',
            ])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'IP rate limit reached: 20 failed top-up attempts from this IP address. Check Retry-After; the limit lasts up to 1 hour.');

        $this->assertGreaterThan(0, (int) $limitedResponse->headers->get('Retry-After'));
        $this->assertLessThanOrEqual(60 * 60, (int) $limitedResponse->headers->get('Retry-After'));
    }

    public function test_phone_selects_the_recipient_account_wallet(): void
    {
        Notification::fake();

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
        $this->assertDatabaseHas('ledger_transactions', [
            'wallet_id' => $recipientWallet->id,
            'actor_id' => $user->id,
            'idempotency_key' => 'top-up-recipient-001',
            'user_agent' => $userAgent,
        ]);

        Notification::assertSentToTimes($recipient, TopUpCardRedemptionNotification::class, 1);
        Notification::assertSentToTimes($user, TopUpCardRedemptionNotification::class, 0);
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
        $this->assertDatabaseCount('ledger_transactions', 0);
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
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_card_with_existing_ledger_transaction_cannot_be_redeemed_again(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up-linked-transaction')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $existingTransaction = LedgerTransaction::factory()->create(['wallet_id' => $wallet->id]);
        $pin = '1234567890123456';
        $card = TopUpCard::factory()->create([
            'status' => TopUpCardStatus::Active,
            'redeemed_at' => null,
            'ledger_transaction_id' => $existingTransaction->id,
            'pin' => TopUpCardPin::hash($pin),
        ]);

        $this->withToken($token)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'top-up-existing-ledger-001',
            ])
            ->assertBadRequest()
            ->assertExactJson(['message' => 'Invalid or unavailable top-up card.']);

        $this->assertSame(1000, $wallet->fresh()->balance);
        $this->assertSame($existingTransaction->id, $card->fresh()->ledger_transaction_id);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_suspended_user_cannot_redeem_with_an_existing_token(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Suspended]);
        $recipient = User::factory()->create(['status' => UserStatus::Active]);
        $token = $user->createToken('top-up')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $recipient->id, 'balance' => 1000]);
        $pin = '1234567890123456';
        $card = TopUpCard::factory()->create(['pin' => TopUpCardPin::hash($pin)]);

        $this->withToken($token)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $recipient->phone,
                'pin' => $pin,
                'idempotency_key' => 'top-up-suspended-user-001',
            ])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Account is not available.']);

        $this->assertSame(1000, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Active, $card->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_suspended_actor_cannot_top_up_another_users_wallet(): void
    {
        $actor = User::factory()->create(['status' => UserStatus::Suspended]);
        $recipient = User::factory()->create();
        $token = $actor->createToken('top-up-suspended-actor')->plainTextToken;
        Wallet::factory()->create(['user_id' => $recipient->id, 'balance' => 1000]);
        $pin = '1234567890123456';
        $card = TopUpCard::factory()->create(['pin' => TopUpCardPin::hash($pin)]);

        $this->withToken($token)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $recipient->phone,
                'pin' => $pin,
                'idempotency_key' => 'top-up-suspended-actor-001',
            ])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Account is not available.']);

        $this->assertSame(TopUpCardStatus::Active, $card->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_card_with_existing_ledger_link_cannot_be_redeemed_again(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up-ledger-link')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $pin = '1234567890123456';
        $orphanLedger = LedgerTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'amount' => 500,
        ]);
        $card = TopUpCard::factory()->create([
            'amount' => 500,
            'pin' => TopUpCardPin::hash($pin),
            'status' => TopUpCardStatus::Active,
            'ledger_transaction_id' => $orphanLedger->id,
            'redeemed_at' => null,
        ]);

        $this->withToken($token)
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'top-up-ledger-link-001',
            ])
            ->assertBadRequest()
            ->assertExactJson(['message' => 'Invalid or unavailable top-up card.']);

        $this->assertSame(1000, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Active, $card->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 1);
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
        $this->assertDatabaseCount('ledger_transactions', 0);
    }
}