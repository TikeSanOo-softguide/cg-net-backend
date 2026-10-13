<?php

namespace Tests\Feature;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\TopUpCardStatus;
use App\Enums\UserStatus;
use App\Enums\WalletStatus;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\TopUpCard;
use App\Models\User;
use App\Models\Wallet;
use App\Support\TopUpCardPin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class TopUpCardRedeemApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_serial_check_returns_distinct_card_statuses(): void
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
            ->assertExactJson([
                'result' => 'valid',
                'message' => 'This top-up card is valid.',
            ]);

        $this->postJson('/api/redeem/check-serial-no', ['serial_no' => $usedCard->serial_no])
            ->assertStatus(400)
            ->assertExactJson([
            'result' => 'already_used',
                'message' => 'This top-up card has already been used.',
            ]);

        foreach ([$expiredCard, $blockedCard, $pendingCard] as $card) {
            $this->postJson('/api/redeem/check-serial-no', ['serial_no' => $card->serial_no])
                ->assertStatus(400)
                ->assertExactJson([
                    'result' => 'invalid_card',
                    'message' => 'Invalid top-up card.',
                ]);
        }

        $this->postJson('/api/redeem/check-serial-no', ['serial_no' => 'UNKNOWN-SERIAL'])
            ->assertStatus(400)
            ->assertExactJson([
            'result' => 'invalid_card',
                'message' => 'Invalid top-up card.',
            ]);
    }

    public function test_serial_check_blocks_after_five_attempts(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('serial-check-limit')->plainTextToken;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withToken($token)
                ->postJson('/api/redeem/check-serial-no', ['serial_no' => 'UNKNOWN-SERIAL'])
                ->assertStatus(400);
        }

        $this->withToken($token)
            ->postJson('/api/redeem/check-serial-no', ['serial_no' => 'UNKNOWN-SERIAL'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_idempotency_key_uses_the_header_and_rejects_refund_prefix(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up-idempotency-validation')->plainTextToken;
        $payload = [
            'phone' => $user->phone,
            'pin' => '12345678',
        ];

        $this->withToken($token)
            ->postJson('/api/redeem/top-up-account', $payload + ['idempotency_key' => 'body-only-key-001'])
            ->assertJsonValidationErrors(['idempotency_key']);

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'refund:top-up-key-001')
            ->postJson('/api/redeem/top-up-account', $payload)
            ->assertJsonValidationErrors(['idempotency_key']);
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
            ->postTopUpAccount([
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

        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(2, $wallet->fresh()->version);
        $this->assertSame(LedgerTransactionType::Topup, $transaction->type);
        $this->assertSame(LedgerTransactionStatus::Completed, $transaction->status);
        $this->assertSame($idempotencyKey, $transaction->idempotency_key);
        $this->assertCount(2, $entries);
        $this->assertSame((int) $entries->sum('debit'), (int) $entries->sum('credit'));
        $this->assertSame(500, (int) $entries->sum('debit'));
        $this->assertTrue(
            $entries->contains(
                fn(LedgerEntry $entry): bool => $entry->ledgerAccount?->code === LedgerAccountCode::CashTopup->value &&
                    (int) $entry->debit === 500,
            ),
        );
        $this->assertSame(TopUpCardStatus::Used, $card->fresh()->status);
        $this->assertSame($user->id, $card->fresh()->redeemed_by);
        $this->assertSame($transaction->id, $card->fresh()->ledger_transaction_id);

        $this->postTopUpAccount([
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

        $this->postTopUpAccount([
            'phone' => $user->phone,
            'pin' => $otherPin,
            'idempotency_key' => $idempotencyKey,
        ])->assertStatus(409);

        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Active, $otherCard->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_same_idempotency_key_with_different_payload_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up-conflict')->plainTextToken;
        Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $pin = '1234567890123456';
        $otherPin = '1234567890123457';
        $idempotencyKey = 'top-up-conflict-001';

        TopUpCard::factory()->create([
            'amount' => 500,
            'pin' => TopUpCardPin::hash($pin),
        ]);
        $otherCard = TopUpCard::factory()->create([
            'amount' => 500,
            'pin' => TopUpCardPin::hash($otherPin),
        ]);

        $this->withToken($token)
            ->postTopUpAccount([
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => $idempotencyKey,
            ])
            ->assertOk();

        $this->postTopUpAccount([
            'phone' => $user->phone,
            'pin' => $otherPin,
            'idempotency_key' => $idempotencyKey,
        ])
            ->assertStatus(409)
            ->assertExactJson(['success' => false, 'message' => 'This request could not be completed.']);

        $this->assertSame(TopUpCardStatus::Active, $otherCard->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 1);
    }

    public function test_different_idempotency_keys_for_the_same_card_succeed_only_once(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up-reuse')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $pin = '1234567890123456';
        $card = TopUpCard::factory()->create([
            'amount' => 500,
            'pin' => TopUpCardPin::hash($pin),
        ]);

        $this->withToken($token)
            ->postTopUpAccount([
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'different-key-001',
            ])
            ->assertOk();

        $this->postTopUpAccount([
            'phone' => $user->phone,
            'pin' => $pin,
            'idempotency_key' => 'different-key-002',
        ])
            ->assertBadRequest()
            ->assertExactJson(['success' => false, 'message' => 'Invalid or unavailable top-up card.']);

        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Used, $card->fresh()->status);
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
        $this->clearPinRateLimits($user);

        $this->withToken($token)
            ->postTopUpAccount([
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'repeat-pin-initial-001',
            ])
            ->assertOk();

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postTopUpAccount([
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'repeat-pin-retry-' . $attempt,
            ])
                ->assertBadRequest()
                ->assertExactJson(['success' => false, 'message' => 'Invalid or unavailable top-up card.']);
        }

        $this->postTopUpAccount([
            'phone' => $user->phone,
            'pin' => $pin,
            'idempotency_key' => 'repeat-pin-limited-001',
        ])
            ->assertStatus(429)
            ->assertJsonPath(
                'message',
                'Too many attempts, retry after 30 minutes.',
            );

        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Used, $card->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 1);
    }

    public function test_invalid_pin_does_not_change_card_or_wallet_and_is_security_logged(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $card = TopUpCard::factory()->create(['pin' => TopUpCardPin::hash('1234567890123456')]);

        $this->withToken($token)
            ->postTopUpAccount([
                'phone' => $user->phone,
                'pin' => '9999999999999999',
                'idempotency_key' => 'top-up-bad-pin-001',
            ])
            ->assertBadRequest()
            ->assertExactJson(['success' => false, 'message' => 'Invalid or unavailable top-up card.']);

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
        $card = TopUpCard::factory()->create([
            'amount' => 500,
            'pin' => TopUpCardPin::hash($pin),
        ]);
        $this->clearPinRateLimits($user);

        $this->withToken($token)
            ->postTopUpAccount([
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'pin-limit-valid-001',
            ])
            ->assertOk();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postTopUpAccount([
                'phone' => $user->phone,
                'pin' => '9' . str_pad((string) $attempt, 15, '0', STR_PAD_LEFT),
                'idempotency_key' => 'pin-limit-failure-' . $attempt,
            ])->assertBadRequest();
        }

        $limitedResponse = $this->postTopUpAccount([
            'phone' => $user->phone,
            'pin' => '8888888888888888',
            'idempotency_key' => 'pin-limit-final-001',
        ])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath(
                'message',
                'Too many attempts, retry after 30 minutes.',
            );

        $this->assertGreaterThan(0, (int) $limitedResponse->headers->get('Retry-After'));
        $this->assertLessThanOrEqual(30 * 60, (int) $limitedResponse->headers->get('Retry-After'));
        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Used, $card->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 1);
    }

    public function test_ip_rate_limit_blocks_after_twenty_failed_pin_attempts(): void
    {
        \Illuminate\Support\Facades\Cache::flush();
        Auth::forgetGuards();
        $users = User::factory()->count(5)->create();
        RateLimiter::clear('top-up-card-pin-failure:ip:' . hash('sha256', '127.0.0.1'));

        foreach ($users as $user) {
            RateLimiter::clear('top-up-card-pin-failure:user:' . hash('sha256', (string) $user->id));
            Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        }

        for ($userIndex = 0; $userIndex < 4; $userIndex++) {
            $user = $users[$userIndex];
            $token = $user->createToken('top-up-ip-limit-' . $userIndex)->plainTextToken;

            for ($attempt = 0; $attempt < 5; $attempt++) {
                Auth::forgetGuards();

                $this->flushHeaders()
                    ->withToken($token)
                    ->withHeader('Idempotency-Key', 'ip-limit-' . $userIndex . '-' . $attempt)
                    ->postJson('/api/redeem/top-up-account', [
                        'phone' => $user->phone,
                        'pin' => '8' . str_pad((string) ($userIndex * 10 + $attempt), 15, '0', STR_PAD_LEFT),
                    ])
                    ->assertBadRequest();
            }
        }

        $blockedUser = $users[4];
        $blockedToken = $blockedUser->createToken('top-up-ip-limit-final')->plainTextToken;
        Auth::forgetGuards();
        $limitedResponse = $this->flushHeaders()
            ->withToken($blockedToken)
            ->withHeader('Idempotency-Key', 'ip-limit-final-001')
            ->postJson('/api/redeem/top-up-account', [
                'phone' => $blockedUser->phone,
                'pin' => '8888888888888888',
            ])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath(
                'message',
                'Too many attempts, retry after 1 hour.',
            );

        $this->assertGreaterThan(0, (int) $limitedResponse->headers->get('Retry-After'));
        $this->assertLessThanOrEqual(60 * 60, (int) $limitedResponse->headers->get('Retry-After'));
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
            ->postTopUpAccount([
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
            ->postTopUpAccount([
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
            ->postTopUpAccount([
                'phone' => $user->phone,
                'pin' => '9999999999999999',
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
            $pin = '7' . str_pad((string) $index, 15, '0', STR_PAD_LEFT);
            $card = TopUpCard::factory()->create($attributes + ['pin' => TopUpCardPin::hash($pin)]);

            $this->withToken($token)
                ->postTopUpAccount([
                    'phone' => $user->phone,
                    'pin' => $pin,
                    'idempotency_key' => 'top-up-card-status-' . $index,
                ])
                ->assertBadRequest()
                ->assertExactJson(['success' => false, 'message' => 'Invalid or unavailable top-up card.']);
        }

        $this->assertSame(1000, $wallet->fresh()->balance);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_card_with_ledger_transaction_id_cannot_be_redeemed_even_if_status_reset(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $pin = '1234567890123456';
        $prior = LedgerTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::Topup,
            'status' => LedgerTransactionStatus::Completed,
            'amount' => 500,
        ]);
        $card = TopUpCard::factory()->create([
            'status' => TopUpCardStatus::Active,
            'redeemed_at' => null,
            'pin' => TopUpCardPin::hash($pin),
            'ledger_transaction_id' => $prior->id,
        ]);

        $this->withToken($token)
            ->postTopUpAccount([
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'top-up-stale-link-001',
            ])
            ->assertBadRequest()
            ->assertExactJson(['success' => false, 'message' => 'Invalid or unavailable top-up card.']);

        $this->assertSame(1000, $wallet->fresh()->balance);
        $this->assertDatabaseCount('ledger_transactions', 1);
    }

    public function test_suspended_actor_cannot_redeem_even_for_an_active_recipient(): void
    {
        $actor = User::factory()->create(['status' => UserStatus::Suspended]);
        $recipient = User::factory()->create(['status' => UserStatus::Active]);
        $token = $actor->createToken('top-up')->plainTextToken;
        Wallet::factory()->create(['user_id' => $recipient->id, 'balance' => 1000]);
        $pin = '1234567890123456';
        $card = TopUpCard::factory()->create(['pin' => TopUpCardPin::hash($pin)]);

        $this->withToken($token)
            ->postTopUpAccount([
                'phone' => $recipient->phone,
                'pin' => $pin,
                'idempotency_key' => 'top-up-suspended-actor-001',
            ])
            ->assertForbidden()
            ->assertExactJson(['success' => false, 'message' => 'Account is not available.']);

        $this->assertSame(TopUpCardStatus::Active, $card->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_suspended_user_cannot_redeem_with_an_existing_token(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Suspended]);
        $token = $user->createToken('top-up')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $pin = '1234567890123456';
        $card = TopUpCard::factory()->create(['pin' => TopUpCardPin::hash($pin)]);

        $this->withToken($token)
            ->postTopUpAccount([
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'top-up-suspended-user-001',
            ])
            ->assertForbidden()
            ->assertExactJson(['success' => false, 'message' => 'Account is not available.']);

        $this->assertSame(1000, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Active, $card->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_wallet_balance_overflow_does_not_consume_card(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('top-up')->plainTextToken;
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 2147483647]);
        $pin = '1234567890123456';
        $card = TopUpCard::factory()->create(['pin' => TopUpCardPin::hash($pin)]);

        $this->withToken($token)
            ->postTopUpAccount([
                'phone' => $user->phone,
                'pin' => $pin,
                'idempotency_key' => 'top-up-overflow-001',
            ])
            ->assertStatus(409)
            ->assertExactJson(['success' => false, 'message' => 'This action could not be completed.']);

        $this->assertSame(2147483647, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Active, $card->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    private function clearPinRateLimits(User $user): void
    {
        RateLimiter::clear('top-up-card-pin-failure:user:' . hash('sha256', (string) $user->id));
    }

    private function postTopUpAccount(array $payload)
    {
        $idempotencyKey = $payload['idempotency_key'];
        unset($payload['idempotency_key']);

        return $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/redeem/top-up-account', $payload);
    }
}
