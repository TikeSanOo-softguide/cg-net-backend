<?php

namespace Tests\Feature;

use App\Enums\CustomerPackageStatus;
use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\PackageOrderStatus;
use App\Enums\WalletActorType;
use App\Enums\WalletStatus;
use App\Models\CustomerPackage;
use App\Models\LedgerTransaction;
use App\Models\Network;
use App\Models\Package;
use App\Models\PackageOrder;
use App\Models\Speed;
use App\Models\Term;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use App\Services\PackageActivation\FakePackageActivationService;
use App\Services\PackageActivation\PackageActivationServiceInterface;
use App\Services\PackageActivation\RealPackageActivationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PackagePurchaseTest extends TestCase
{
    use RefreshDatabase;

    private FakePackageActivationService $activation;

    private Network $network;

    private int $mbps = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activation = new FakePackageActivationService;
        $this->app->instance(PackageActivationServiceInterface::class, $this->activation);
        $this->network = Network::factory()->create();
    }

    public function test_user_can_buy_package_when_wallet_has_enough_balance(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-15 12:00:00'));

        try {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $wallet = $this->wallet($user, 150000);
            $package = $this->makePackage(price: 50000, months: 6);

            $response = $this->buy($user, $package, 'pkg-success-01', [
                'price' => 1,
                'amount' => 1,
                'user_id' => $otherUser->id,
                'username' => 'client-user',
                'password' => 'client-password',
                'starts_at' => '2020-01-01 00:00:00',
                'expires_at' => '2020-02-01 00:00:00',
                'auto_renew' => true,
            ]);

            $response
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('message', 'Package purchased successfully.')
                ->assertJsonPath('data.amount', 50000)
                ->assertJsonPath('data.package_id', $package->id)
                ->assertJsonPath('data.status', PackageOrderStatus::Completed->value);

            $this->assertStringNotContainsString('test-password', (string) $response->getContent());
            $this->assertStringNotContainsString('client-password', (string) $response->getContent());
            $this->assertStringNotContainsString('test-user', (string) $response->getContent());

            $wallet->refresh();
            $this->assertSame(100000, $wallet->balance);

            $order = PackageOrder::query()->where('user_id', $user->id)->firstOrFail();
            $this->assertSame(PackageOrderStatus::Completed, $order->status);
            $this->assertSame($package->id, $order->package_id);
            $this->assertSame(50000, $order->snapshot['price']);
            $this->assertSame(6, $order->snapshot['term_months']);
            $this->assertNotNull($order->ledger_transaction_id);
            $this->assertSame('2026-01-15 12:00:00', $order->completed_at?->format('Y-m-d H:i:s'));

            $debit = LedgerTransaction::query()->findOrFail($order->ledger_transaction_id);
            $this->assertSame(LedgerTransactionType::WifiPackage, $debit->type);
            $this->assertSame(LedgerTransactionStatus::Completed, $debit->status);
            $this->assertSame(50000, $debit->amount);
            $this->assertSame('pkg-success-01', $debit->idempotency_key);
            $this->assertSame($user->id, $debit->actor_id);

            $this->assertDatabaseHas('ledger_entries', [
                'ledger_transaction_id' => $debit->id,
                'wallet_id' => $wallet->id,
                'debit' => 50000,
                'credit' => 0,
                'balance_before' => 150000,
                'balance_after' => 100000,
            ]);
            $this->assertDatabaseMissing('ledger_transactions', [
                'wallet_id' => $wallet->id,
                'type' => LedgerTransactionType::Refund->value,
            ]);

            $customerPackage = CustomerPackage::query()->where('package_order_id', $order->id)->firstOrFail();
            $this->assertSame($user->id, $customerPackage->user_id);
            $this->assertNotSame($otherUser->id, $customerPackage->user_id);
            $this->assertSame($package->id, $customerPackage->package_id);
            $this->assertSame('test-user', $customerPackage->username);
            $this->assertSame('test-password', $customerPackage->password);
            $this->assertArrayNotHasKey('password', $customerPackage->toArray());
            $this->assertSame(CustomerPackageStatus::Active, $customerPackage->status);
            $this->assertSame('2026-01-15 12:00:00', $customerPackage->starts_at?->format('Y-m-d H:i:s'));
            $this->assertSame('2026-07-15 12:00:00', $customerPackage->expires_at?->format('Y-m-d H:i:s'));
            $this->assertTrue(
                $customerPackage->expires_at->equalTo($customerPackage->starts_at->copy()->addMonths(6)),
            );
            $this->assertSame(1, $this->activation->calls);
            $this->assertDatabaseCount('customer_packages', 1);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_invalid_package_id_is_rejected_without_creating_order(): void
    {
        $user = User::factory()->create();
        $this->wallet($user, 150000);
        $this->activation->reset();

        $this->buyRaw($user, ['package_id' => 999999], 'pkg-invalid-01')->assertStatus(422);

        $this->assertDatabaseMissing('package_orders', ['user_id' => $user->id]);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertSame(0, $this->activation->calls);
    }

    public function test_inactive_package_is_rejected_without_debit_or_activation(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 150000);
        $package = $this->makePackage(price: 50000, months: 3, active: false);

        $this->buy($user, $package, 'pkg-inactive-01')->assertStatus(422);

        $wallet->refresh();
        $this->assertSame(150000, $wallet->balance);
        $this->assertDatabaseCount('package_orders', 0);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertSame(0, $this->activation->calls);
    }

    public function test_package_without_a_term_is_rejected_without_debit(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 150000);
        $package = $this->makePackage(price: 50000, months: 4);
        $package->term?->delete();

        $this->buy($user, $package, 'pkg-no-term-01')->assertStatus(422);

        $wallet->refresh();
        $this->assertSame(150000, $wallet->balance);
        $this->assertDatabaseCount('package_orders', 0);
        $this->assertSame(0, $this->activation->calls);
    }

    public function test_insufficient_balance_stops_purchase(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 30000);
        $package = $this->makePackage(price: 50000, months: 12);

        $this->buy($user, $package, 'pkg-poor-01')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient wallet balance.');

        $wallet->refresh();
        $this->assertSame(30000, $wallet->balance);
        $this->assertDatabaseCount('package_orders', 0);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertSame(0, $this->activation->calls);
    }

    public function test_activation_failure_refunds_wallet_and_marks_order_failed(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 150000);
        $package = $this->makePackage(price: 60000, months: 1);
        $key = str_repeat('a', 93);

        $this->activation->reset();
        $this->activation->failNextActivation = true;

        $this->buy($user, $package, $key)
            ->assertStatus(502)
            ->assertJsonPath('message', 'Package activation failed. Wallet was refunded.')
            ->assertJsonPath('data.status', PackageOrderStatus::Failed->value);

        $wallet->refresh();
        $this->assertSame(150000, $wallet->balance);

        $debit = LedgerTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', LedgerTransactionType::WifiPackage)
            ->firstOrFail();
        $this->assertSame(LedgerTransactionStatus::Failed, $debit->status);
        $this->assertSame(60000, $debit->amount);

        $refund = LedgerTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', LedgerTransactionType::Refund)
            ->firstOrFail();
        $this->assertSame($debit->id, $refund->reversal_of);
        $this->assertSame(LedgerTransactionStatus::Completed, $refund->status);
        $this->assertSame(60000, $refund->amount);
        $this->assertSame('refund:' . $key, $refund->idempotency_key);

        $this->assertDatabaseHas('ledger_entries', [
            'ledger_transaction_id' => $refund->id,
            'wallet_id' => $wallet->id,
            'debit' => 0,
            'credit' => 60000,
            'balance_before' => 90000,
            'balance_after' => 150000,
        ]);

        $this->assertDatabaseHas('package_orders', [
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => PackageOrderStatus::Failed->value,
            'ledger_transaction_id' => $debit->id,
        ]);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertSame(1, $this->activation->calls);
    }

    public function test_activation_exception_refunds_wallet_and_marks_order_failed(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 150000);
        $package = $this->makePackage(price: 50000, months: 3);

        $this->activation->reset();
        $this->activation->throwNextActivation = true;

        $this->buy($user, $package, 'pkg-throw-0001')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Package activation failed. Wallet was refunded.');

        $wallet->refresh();
        $this->assertSame(150000, $wallet->balance);
        $this->assertDatabaseHas('package_orders', [
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => PackageOrderStatus::Failed->value,
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::Refund->value,
            'amount' => 50000,
        ]);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertSame(1, $this->activation->calls);
    }

    public function test_activation_without_credentials_refunds_and_does_not_create_package(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 80000);
        $package = $this->makePackage(price: 20000, months: 2);

        $this->activation->returnEmptyCredentials = true;

        $this->buy($user, $package, 'pkg-blank-cred')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Package activation failed. Wallet was refunded.');

        $wallet->refresh();
        $this->assertSame(80000, $wallet->balance);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertDatabaseHas('package_orders', [
            'user_id' => $user->id,
            'status' => PackageOrderStatus::Failed->value,
        ]);
        $this->assertDatabaseCount('ledger_transactions', 2);
    }

    public function test_replaying_a_successful_purchase_does_not_debit_or_activate_again(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 150000);
        $package = $this->makePackage(price: 40000, months: 5);

        $first = $this->buy($user, $package, 'pkg-replay-ok1')->assertOk();
        $transactionNo = $first->json('data.transaction_no');

        $this->buy($user, $package, 'pkg-replay-ok1')
            ->assertOk()
            ->assertJsonPath('message', 'Package purchase already processed.')
            ->assertJsonPath('data.transaction_no', $transactionNo)
            ->assertJsonPath('data.amount', 40000);

        $wallet->refresh();
        $this->assertSame(110000, $wallet->balance);
        $this->assertSame(1, $this->activation->calls);
        $this->assertDatabaseCount('customer_packages', 1);
        $this->assertSame(1, LedgerTransaction::query()->where('type', LedgerTransactionType::WifiPackage)->count());
    }

    public function test_replaying_a_failed_purchase_does_not_refund_again(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 150000);
        $package = $this->makePackage(price: 25000, months: 8);

        $this->activation->failNextActivation = true;
        $this->buy($user, $package, 'pkg-replay-no1')->assertStatus(502);

        $this->activation->failNextActivation = true;
        $this->buy($user, $package, 'pkg-replay-no1')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Package activation failed. Wallet was refunded.');

        $wallet->refresh();
        $this->assertSame(150000, $wallet->balance);
        $this->assertSame(1, $this->activation->calls);
        $this->assertSame(1, LedgerTransaction::query()->where('type', LedgerTransactionType::Refund)->count());
        $this->assertDatabaseCount('customer_packages', 0);
    }

    public function test_same_idempotency_key_cannot_buy_a_different_package(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 200000);
        $first = $this->makePackage(price: 10000, months: 6);
        $second = $this->makePackage(price: 70000, months: 9);

        $this->buy($user, $first, 'pkg-same-key01')->assertOk();

        $this->buy($user, $second, 'pkg-same-key01')
            ->assertStatus(409)
            ->assertJsonPath('message', 'This idempotency key was already used for a different package.');

        $wallet->refresh();
        $this->assertSame(190000, $wallet->balance);
        $this->assertDatabaseCount('customer_packages', 1);
        $this->assertSame(1, $this->activation->calls);
    }

    public function test_processing_purchase_is_not_debited_or_activated_again(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 150000);
        $package = $this->makePackage(price: 50000, months: 6);

        $debit = app(LedgerPoster::class)->debitWallet(
            wallet: $wallet,
            amount: 50000,
            contraAccount: LedgerAccountCode::PackageRevenue,
            type: LedgerTransactionType::WifiPackage,
            status: LedgerTransactionStatus::Processing,
            idempotencyKey: 'pkg-processing1',
            actorType: WalletActorType::User,
            actorId: $user->id,
        );

        PackageOrder::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'ledger_transaction_id' => $debit->id,
            'status' => PackageOrderStatus::Processing,
            'snapshot' => [
                'package_id' => $package->id,
                'price' => 50000,
                'term_months' => 6,
            ],
        ]);

        $this->buy($user, $package, 'pkg-processing1')
            ->assertStatus(202)
            ->assertJsonPath('message', 'This package purchase is already being processed.');

        $wallet->refresh();
        $this->assertSame(100000, $wallet->balance);
        $this->assertSame(0, $this->activation->calls);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertSame(1, LedgerTransaction::query()->where('type', LedgerTransactionType::WifiPackage)->count());
    }

    public function test_default_activator_does_not_issue_fake_credentials(): void
    {
        $this->app->forgetInstance(PackageActivationServiceInterface::class);
        $this->assertInstanceOf(
            RealPackageActivationService::class,
            $this->app->make(PackageActivationServiceInterface::class),
        );

        $user = User::factory()->create();
        $wallet = $this->wallet($user, 90000);
        $package = $this->makePackage(price: 15000, months: 7);

        $this->buy($user, $package, 'pkg-unconfigured')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Package activation failed. Wallet was refunded.');

        $wallet->refresh();
        $this->assertSame(90000, $wallet->balance);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertDatabaseMissing('customer_packages', [
            'username' => 'test-user',
        ]);
        $this->assertSame(0, $this->activation->calls);
    }

    public function test_idempotency_key_header_is_required(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 150000);
        $package = $this->makePackage(price: 10000, months: 1);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/packages/buy', ['package_id' => $package->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);

        $this->buy($user, $package, str_repeat('b', 94))->assertStatus(422);
        $this->buy($user, $package, 'refund:not-allowed')->assertStatus(422);

        $wallet->refresh();
        $this->assertSame(150000, $wallet->balance);
        $this->assertDatabaseCount('package_orders', 0);
        $this->assertSame(0, $this->activation->calls);
    }

    public function test_guest_cannot_buy_a_package(): void
    {
        $this->postJson('/api/packages/buy', ['package_id' => 1])
            ->assertUnauthorized();

        $this->assertDatabaseCount('package_orders', 0);
        $this->assertSame(0, $this->activation->calls);
    }

    public function test_user_without_a_wallet_cannot_buy(): void
    {
        $user = User::factory()->create();
        $package = $this->makePackage(price: 10000, months: 1);

        $this->buy($user, $package, 'pkg-no-wallet1')
            ->assertNotFound()
            ->assertJsonPath('message', 'Wallet not found.');

        $this->assertDatabaseCount('package_orders', 0);
        $this->assertSame(0, $this->activation->calls);
    }

    public function test_inactive_wallet_cannot_buy(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 150000, WalletStatus::Frozen);
        $package = $this->makePackage(price: 10000, months: 2);

        $this->buy($user, $package, 'pkg-frozen-001')
            ->assertForbidden()
            ->assertJsonPath('message', 'Wallet is not active.');

        $wallet->refresh();
        $this->assertSame(150000, $wallet->balance);
        $this->assertDatabaseCount('package_orders', 0);
        $this->assertSame(0, $this->activation->calls);
    }

    private function wallet(User $user, int $balance, WalletStatus $status = WalletStatus::Active): Wallet
    {
        return Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => $balance,
            'status' => $status,
        ]);
    }

    private function makePackage(int $price, int $months, bool $active = true): Package
    {
        $this->mbps++;

        return Package::factory()->create([
            'network_id' => $this->network->id,
            'speed_id' => Speed::factory()->create(['mbps' => $this->mbps])->id,
            'term_id' => Term::factory()->create(['months' => $months])->id,
            'price' => $price,
            'is_active' => $active,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function buy(User $user, Package $package, string $key, array $extra = []): TestResponse
    {
        return $this->buyRaw($user, ['package_id' => $package->id, ...$extra], $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buyRaw(User $user, array $payload, string $key): TestResponse
    {
        return $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/packages/buy', $payload);
    }
}
