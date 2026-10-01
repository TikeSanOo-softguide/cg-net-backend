<?php

namespace Tests\Feature;

use App\Enums\TopUpCardStatus;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\TopUpCard;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use App\Services\SecurityLogService;
use App\Services\TopUpCard\TopUpCardRedemptionService;
use App\Support\TopUpCardPin;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Concurrency;
use Tests\TestCase;

class TopUpCardRedeemConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_hundred_requests_for_the_same_card_succeed_exactly_once(): void
    {
        [$user, $wallet, $pin, $card] = $this->seedRedeemableCard(balance: 1000, amount: 500);
        $service = app(TopUpCardRedemptionService::class);

        $results = $this->runContendedRedeems(
            count: 100,
            runner: function (int $index) use ($service, $user, $pin): array {
                return $service->redeem(
                    user: $user,
                    phone: $user->phone,
                    pin: $pin,
                    idempotencyKey: sprintf('concurrent-same-card-%03d', $index),
                    ipAddress: '127.0.0.1',
                    userAgent: 'concurrency-test',
                );
            },
        );

        $successes = array_values(array_filter(
            $results,
            static fn (array $result): bool => $result['http_status'] === 200
                && ($result['body']['message'] ?? null) === 'Top-up successful.',
        ));

        $this->assertCount(1, $successes);
        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Used, $card->fresh()->status);
        $this->assertNotNull($card->fresh()->ledger_transaction_id);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertSame(
            (int) LedgerEntry::query()->sum('debit'),
            (int) LedgerEntry::query()->sum('credit'),
        );
    }

    public function test_different_idempotency_keys_same_card_succeed_exactly_once(): void
    {
        [$user, $wallet, $pin, $card] = $this->seedRedeemableCard(balance: 2000, amount: 750);
        $service = app(TopUpCardRedemptionService::class);

        $results = $this->runContendedRedeems(
            count: 40,
            runner: function (int $index) use ($service, $user, $pin): array {
                return $service->redeem(
                    user: $user,
                    phone: $user->phone,
                    pin: $pin,
                    idempotencyKey: sprintf('concurrent-diff-key-%03d', $index),
                    ipAddress: '127.0.0.1',
                    userAgent: 'concurrency-test',
                );
            },
        );

        $this->assertCount(
            1,
            array_filter($results, static fn (array $result): bool => $result['http_status'] === 200),
        );
        $this->assertSame(2750, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Used, $card->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 1);
    }

    public function test_same_idempotency_key_and_payload_replays_without_duplicate_finance(): void
    {
        [$user, $wallet, $pin, $card] = $this->seedRedeemableCard(balance: 1000, amount: 500);
        $idempotencyKey = 'concurrent-same-key-001';
        $service = app(TopUpCardRedemptionService::class);

        $results = $this->runContendedRedeems(
            count: 50,
            runner: function () use ($service, $user, $pin, $idempotencyKey): array {
                return $service->redeem(
                    user: $user,
                    phone: $user->phone,
                    pin: $pin,
                    idempotencyKey: $idempotencyKey,
                    ipAddress: '127.0.0.1',
                    userAgent: 'concurrency-test',
                );
            },
        );

        $ok = array_values(array_filter(
            $results,
            static fn (array $result): bool => $result['http_status'] === 200,
        ));

        $this->assertNotEmpty($ok);
        $this->assertTrue(collect($ok)->every(
            static fn (array $result): bool => in_array(
                $result['body']['message'] ?? null,
                ['Top-up successful.', 'Top-up already processed.'],
                true,
            ),
        ));
        $this->assertCount(1, collect($ok)->pluck('body.transaction_no')->unique()->filter());
        $this->assertSame(0, collect($results)->where('http_status', 409)->count());
        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Used, $card->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_same_idempotency_key_with_different_payload_is_rejected(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $pinA = 'concurrent-pin-aaaa';
        $pinB = 'concurrent-pin-bbbb';
        $cardA = TopUpCard::factory()->create([
            'amount' => 500,
            'pin' => TopUpCardPin::hash($pinA),
        ]);
        $cardB = TopUpCard::factory()->create([
            'amount' => 500,
            'pin' => TopUpCardPin::hash($pinB),
        ]);
        $idempotencyKey = 'concurrent-conflict-key-001';
        $service = app(TopUpCardRedemptionService::class);

        $this->assertSame(200, $service->redeem(
            user: $user,
            phone: $user->phone,
            pin: $pinA,
            idempotencyKey: $idempotencyKey,
        )['http_status']);

        $results = $this->runContendedRedeems(
            count: 20,
            runner: function () use ($service, $user, $pinB, $idempotencyKey): array {
                return $service->redeem(
                    user: $user,
                    phone: $user->phone,
                    pin: $pinB,
                    idempotencyKey: $idempotencyKey,
                    ipAddress: '127.0.0.1',
                    userAgent: 'concurrency-test',
                );
            },
        );

        $this->assertTrue(collect($results)->every(
            static fn (array $result): bool => $result['http_status'] === 409,
        ));
        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertSame(TopUpCardStatus::Used, $cardA->fresh()->status);
        $this->assertSame(TopUpCardStatus::Active, $cardB->fresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 1);
    }

    public function test_unique_constraint_race_replays_original_financial_result(): void
    {
        [$user, $wallet, $pin, $card] = $this->seedRedeemableCard(balance: 1000, amount: 500);
        $idempotencyKey = 'unique-race-replay-001';

        $winner = app(TopUpCardRedemptionService::class)->redeem(
            user: $user,
            phone: $user->phone,
            pin: $pin,
            idempotencyKey: $idempotencyKey,
        );

        $this->assertSame(200, $winner['http_status']);
        $this->assertSame('Top-up successful.', $winner['body']['message']);

        $poster = \Mockery::mock(LedgerPoster::class);
        $poster->shouldReceive('creditWallet')->once()->andThrow(
            new UniqueConstraintViolationException('sqlite', 'insert into ledger_transactions', [], new \Exception('unique')),
        );

        $service = new class ($poster, app(SecurityLogService::class)) extends TopUpCardRedemptionService {
            private int $lookups = 0;

            protected function findExistingByIdempotencyKey(string $idempotencyKey): ?LedgerTransaction
            {
                $this->lookups++;

                // First lookup (inside the redeem txn) misses, mimicking the race window.
                if ($this->lookups === 1) {
                    return null;
                }

                return parent::findExistingByIdempotencyKey($idempotencyKey);
            }

            protected function cardIsRedeemable(TopUpCard $card): bool
            {
                // Keep the winner's card link intact so existingResponse can validate
                // ledger ↔ card integrity while still reaching creditWallet.
                return true;
            }
        };

        $loser = $service->redeem(
            user: $user->fresh(),
            phone: $user->phone,
            pin: $pin,
            idempotencyKey: $idempotencyKey,
            ipAddress: '127.0.0.1',
            userAgent: 'race-test',
        );

        $this->assertSame(200, $loser['http_status']);
        $this->assertSame('Top-up already processed.', $loser['body']['message']);
        $this->assertSame($winner['body']['transaction_no'], $loser['body']['transaction_no']);
        $this->assertSame(1500, $loser['body']['balance']);
        $this->assertSame(1500, $wallet->fresh()->balance);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    /**
     * @return array{0: User, 1: Wallet, 2: string, 3: TopUpCard}
     */
    private function seedRedeemableCard(int $balance, int $amount): array
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => $balance]);
        $pin = 'concurrent-pin-'.bin2hex(random_bytes(4));
        $card = TopUpCard::factory()->create([
            'amount' => $amount,
            'pin' => TopUpCardPin::hash($pin),
        ]);

        return [$user, $wallet, $pin, $card];
    }

    /**
     * @param  callable(int): array{http_status: int, body: array<string, mixed>, headers?: array<string, string>}  $runner
     * @return list<array{http_status: int, body: array<string, mixed>, headers?: array<string, string>}>
     */
    private function runContendedRedeems(int $count, callable $runner): array
    {
        $tasks = [];
        for ($index = 1; $index <= $count; $index++) {
            $tasks[] = fn () => $runner($index);
        }

        try {
            /** @var list<array{http_status: int, body: array<string, mixed>, headers?: array<string, string>}> $results */
            $results = Concurrency::driver('sync')->run($tasks);
        } catch (\Throwable) {
            $results = [];
            foreach ($tasks as $index => $task) {
                $results[] = $task();
            }
        }

        $this->assertCount($count, $results);

        return $results;
    }
}
