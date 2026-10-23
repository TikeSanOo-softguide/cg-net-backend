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
use App\Services\CgNetApiClient;
use App\Services\Ledger\LedgerPoster;
use App\Services\Package\CgNetProductCatalog;
use App\Services\PackageActivation\FakePackageActivationService;
use App\Services\PackageActivation\PackageActivationServiceInterface;
use App\Services\PackageActivation\RealPackageActivationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PackagePurchaseTest extends TestCase
{
    use RefreshDatabase;

    private FakePackageActivationService $activation;

    private Network $network;

    private int $mbps = 1000;

    private object $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activation = new FakePackageActivationService;
        $this->app->instance(PackageActivationServiceInterface::class, $this->activation);
        $this->catalog = new class(app(CgNetApiClient::class)) extends CgNetProductCatalog
        {
            /** @var list<array<string, mixed>> */
            public array $catalogProducts = [];

            public function products(): array
            {
                return $this->catalogProducts;
            }

            public function find(string $productId): ?array
            {
                foreach ($this->catalogProducts as $product) {
                    if ($product['productID'] === $productId) {
                        return $product;
                    }
                }

                return null;
            }
        };
        $this->app->instance(CgNetProductCatalog::class, $this->catalog);
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
                'product_price' => 0.01,
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
                ->assertJsonPath('data.product_id', $package->provider_product_id)
                ->assertJsonPath('data.status', PackageOrderStatus::Completed->value);

            $account = $response->json('data.credentials.account');
            $password = $response->json('data.credentials.password');
            $this->assertMatchesRegularExpression('/^G\d{7}$/', $account);
            $this->assertMatchesRegularExpression('/^\d{6}$/', $password);

            $wallet->refresh();
            $this->assertSame(100000, $wallet->balance);

            $order = PackageOrder::query()->where('user_id', $user->id)->firstOrFail();
            $this->assertSame($order->order_no, $response->json('data.order_no'));
            $this->assertSame($package->provider_product_id, $order->product_id);
            $this->assertSame(number_format($package->price, 2, '.', ''), $order->product_price);
            $this->assertSame(PackageOrderStatus::Completed, $order->status);
            $this->assertNull($order->package_id);
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
            $this->assertNull($customerPackage->package_id);
            $this->assertSame($account, $customerPackage->username);
            $this->assertSame($password, $customerPackage->password);
            $this->assertNotSame(
                $password,
                DB::table('customer_packages')->where('id', $customerPackage->id)->value('password'),
            );
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

    public function test_day_based_provider_package_expires_after_the_provider_cycle_in_days(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-15 12:00:00'));

        try {
            $user = User::factory()->create();
            $this->wallet($user, 50000);
            $package = $this->makePackage(price: 25000, months: 1);
            $productIndex = array_key_last($this->catalog->catalogProducts);
            $this->catalog->catalogProducts[$productIndex]['monthCycle'] = 7;
            $this->catalog->catalogProducts[$productIndex]['monthCycleUnit'] = '天';

            $response = $this->buy($user, $package, 'pkg-seven-days')->assertOk();
            $order = PackageOrder::query()->firstOrFail();
            $customerPackage = CustomerPackage::query()->where('package_order_id', $order->id)->firstOrFail();

            $response->assertJsonPath('data.package.month_cycle', 7)
                ->assertJsonPath('data.package.month_cycle_unit', '天');
            $this->assertNull($order->snapshot['term_months']);
            $this->assertSame(7, $order->snapshot['term_cycle']);
            $this->assertSame('天', $order->snapshot['term_cycle_unit']);
            $this->assertSame('2026-01-22 12:00:00', $customerPackage->expires_at?->format('Y-m-d H:i:s'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_unknown_product_id_is_rejected_without_creating_order(): void
    {
        $user = User::factory()->create();
        $this->wallet($user, 150000);
        $this->activation->reset();

        $this->buyRaw($user, ['product_id' => 'unknown-product'], 'pkg-invalid-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);

        $this->assertDatabaseMissing('package_orders', ['user_id' => $user->id]);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertSame(0, $this->activation->calls);
    }

    public function test_product_not_in_provider_catalog_is_rejected_without_debit_or_activation(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 150000);
        $package = $this->makePackage(price: 50000, months: 3);
        $this->catalog->catalogProducts = [];

        $this->buy($user, $package, 'pkg-inactive-01')->assertStatus(422);

        $wallet->refresh();
        $this->assertSame(150000, $wallet->balance);
        $this->assertDatabaseCount('package_orders', 0);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertSame(0, $this->activation->calls);
    }

    public function test_package_without_a_local_term_uses_provider_duration(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 100000);
        $package = $this->makePackage(price: 10000, months: 1);
        $package->term?->delete();

        $this->buy($user, $package, 'pkg-provider-duration')->assertOk();

        $wallet->refresh();
        $this->assertSame(90000, $wallet->balance);
        $this->assertDatabaseCount('package_orders', 1);
        $this->assertSame(1, $this->activation->calls);
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
        $this->assertSame('refund:'.$key, $refund->idempotency_key);

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
            'package_id' => null,
            'status' => PackageOrderStatus::Failed->value,
            'ledger_transaction_id' => $debit->id,
        ]);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertSame(1, $this->activation->calls);
    }

    public function test_activation_exception_keeps_wallet_debit_pending_for_reconciliation(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 150000);
        $package = $this->makePackage(price: 50000, months: 3);

        $this->activation->reset();
        $this->activation->throwNextActivation = true;

        $this->buy($user, $package, 'pkg-throw-0001')
            ->assertStatus(202)
            ->assertJsonPath('data.status', PackageOrderStatus::PendingVerification->value);

        $wallet->refresh();
        $this->assertSame(100000, $wallet->balance);
        $this->assertDatabaseHas('package_orders', [
            'user_id' => $user->id,
            'package_id' => null,
            'status' => PackageOrderStatus::PendingVerification->value,
        ]);
        $this->assertDatabaseMissing('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::Refund->value,
        ]);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertSame(1, $this->activation->calls);
    }

    public function test_unconfirmed_activation_without_result_credentials_stays_pending(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 80000);
        $package = $this->makePackage(price: 20000, months: 2);

        $this->activation->returnEmptyCredentials = true;

        $this->buy($user, $package, 'pkg-blank-cred')
            ->assertStatus(202)
            ->assertJsonPath('data.status', PackageOrderStatus::PendingVerification->value);

        $wallet->refresh();
        $this->assertSame(60000, $wallet->balance);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertDatabaseHas('package_orders', [
            'user_id' => $user->id,
            'status' => PackageOrderStatus::PendingVerification->value,
        ]);
        $this->assertDatabaseCount('ledger_transactions', 1);
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
            'package_id' => null,
            'ledger_transaction_id' => $debit->id,
            'status' => PackageOrderStatus::Processing,
            'snapshot' => [
                'product_id' => $package->provider_product_id,
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
        config(['services.cg_api.url' => null]);
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

    public function test_real_activation_uses_server_values_and_returns_generated_credentials_only_on_success(): void
    {
        $this->app->instance(PackageActivationServiceInterface::class, app(RealPackageActivationService::class));
        $this->configureCgNet();
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response($this->cgnetToken()),
            'provider.test/WalletApp/ServerCommand/ActivateCGNETAccountAPI' => Http::response(['ErrCode' => 0, 'Message' => 'ok']),
        ]);

        $user = User::factory()->create();
        $this->wallet($user, 100000);
        $package = $this->makePackage(price: 25000, months: 3);

        $response = $this->buy($user, $package, 'real-provider-001', [
            'product_price' => 0.01,
            'price' => 1,
            'account' => 'G0000000',
            'password' => '000000',
        ])->assertOk();

        $order = PackageOrder::query()->firstOrFail();
        $response
            ->assertJsonPath('data.order_no', $order->order_no)
            ->assertJsonPath('data.package.product_id', $package->provider_product_id)
            ->assertJsonPath('data.package.price', number_format($package->price, 2, '.', ''));

        $this->assertMatchesRegularExpression('/^CGN-[A-F0-9-]{36}$/', $order->order_no);
        $this->assertMatchesRegularExpression('/^G\d{7}$/', $order->account);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $order->activation_password);
        $this->assertSame($order->account, $response->json('data.credentials.account'));
        $this->assertSame($order->activation_password, $response->json('data.credentials.password'));

        Http::assertSent(function (ClientRequest $request) use ($package, $order): bool {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://provider.test/WalletApp/ServerCommand/ActivateCGNETAccountAPI'
                && $request->hasHeader('Authorization')
                && $body['OrderNo'] === $order->order_no
                && $body['Account'] === $order->account
                && $body['Password'] === $order->activation_password
                && $body['ProductID'] === $package->provider_product_id
                && (float) $body['ProductPrice'] === (float) $package->price;
        });
    }

    public function test_real_activation_preserves_leading_zeroes_in_six_digit_password(): void
    {
        $this->configureCgNet();
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response($this->cgnetToken()),
            'provider.test/WalletApp/ServerCommand/ActivateCGNETAccountAPI' => Http::response(['ErrCode' => 0, 'Message' => 'ok']),
        ]);

        $user = User::factory()->create();
        $package = $this->makePackage(price: 10000, months: 1);
        $order = PackageOrder::query()->create([
            'user_id' => $user->id,
            'package_id' => null,
            'status' => PackageOrderStatus::Processing,
            'order_no' => 'CGN-LEADING-ZEROS',
            'account' => 'G1234567',
            'activation_password' => '000012',
            'product_id' => $package->provider_product_id,
            'product_price' => (string) $package->price,
            'snapshot' => [
                'product_id' => $package->provider_product_id,
                'term_months' => 1,
            ],
        ]);

        $result = app(RealPackageActivationService::class)->activate($user, $package, $order);

        $this->assertTrue($result->success);
        $this->assertSame('000012', $result->password);
        Http::assertSent(fn (ClientRequest $request): bool => str_ends_with(
            $request->url(),
            '/ActivateCGNETAccountAPI',
        ) && $request->data()['Password'] === '000012');
    }

    public function test_code_100_and_500_refund_once_without_creating_customer_packages(): void
    {
        $this->app->instance(PackageActivationServiceInterface::class, app(RealPackageActivationService::class));
        $this->configureCgNet();
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response($this->cgnetToken()),
            'provider.test/WalletApp/ServerCommand/ActivateCGNETAccountAPI' => Http::sequence()
                ->push(['ErrCode' => 100, 'Message' => 'missing product'])
                ->push(['ErrCode' => 500, 'Message' => 'invalid request']),
        ]);

        $user = User::factory()->create();
        $wallet = $this->wallet($user, 100000);
        $first = $this->makePackage(price: 15000, months: 1);
        $second = $this->makePackage(price: 20000, months: 2);

        $this->buy($user, $first, 'provider-error-100')->assertStatus(502);
        $this->buy($user, $first, 'provider-error-100')->assertStatus(502);
        $this->buy($user, $second, 'provider-error-500')->assertStatus(502);

        $wallet->refresh();
        $this->assertSame(100000, $wallet->balance);
        $this->assertSame(2, LedgerTransaction::query()->where('type', LedgerTransactionType::Refund)->count());
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertSame(2, PackageOrder::query()->where('status', PackageOrderStatus::Failed)->count());
    }

    public function test_code_1_and_2_and_http_uncertainty_keep_the_order_pending_without_refund(): void
    {
        $this->app->instance(PackageActivationServiceInterface::class, app(RealPackageActivationService::class));
        $this->configureCgNet();
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response($this->cgnetToken()),
            'provider.test/WalletApp/ServerCommand/ActivateCGNETAccountAPI' => Http::sequence()
                ->push(['ErrCode' => 1, 'Message' => 'order already open'])
                ->push(['ErrCode' => 2, 'Message' => 'account exists'])
                ->push(['error' => 'temporary upstream error'], 503)
                ->push('not-json', 200, ['Content-Type' => 'text/plain'])
                ->push(['ErrCode' => '0', 'Message' => 'invalid type']),
        ]);

        $user = User::factory()->create();
        $wallet = $this->wallet($user, 100000);

        foreach ([1, 2, 3, 4, 5] as $index) {
            $package = $this->makePackage(price: 10000, months: $index);
            $response = $this->buy($user, $package, 'provider-pending-'.$index)
                ->assertStatus(202)
                ->assertJsonPath('data.status', PackageOrderStatus::PendingVerification->value);

            $this->assertArrayNotHasKey('credentials', $response->json('data'));
        }

        $this->buyRaw($user, [
            'product_id' => $this->catalog->catalogProducts[0]['productID'],
        ], 'provider-pending-1')
            ->assertStatus(202);

        $wallet->refresh();
        $this->assertSame(50000, $wallet->balance);
        $this->assertDatabaseCount('customer_packages', 0);
        $this->assertDatabaseCount('ledger_transactions', 5);
        $this->assertSame(5, PackageOrder::query()->where('status', PackageOrderStatus::PendingVerification)->count());
        Http::assertSentCount(6);
    }

    public function test_connection_failure_reuses_order_and_never_refunds_automatically(): void
    {
        $this->app->instance(PackageActivationServiceInterface::class, app(RealPackageActivationService::class));
        $this->configureCgNet();
        $requestCount = 0;
        Http::fake(function (ClientRequest $request) use (&$requestCount) {
            $requestCount++;
            if (str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/GetTokenAPI')) {
                return Http::response($this->cgnetToken());
            }

            throw new ConnectionException('connection timeout');
        });

        $user = User::factory()->create();
        $wallet = $this->wallet($user, 80000);
        $package = $this->makePackage(price: 20000, months: 1);

        $first = $this->buy($user, $package, 'provider-timeout-1')->assertStatus(202);
        $orderNo = $first->json('data.order_no');
        $this->buy($user, $package, 'provider-timeout-1')
            ->assertStatus(202)
            ->assertJsonPath('data.order_no', $orderNo);

        $wallet->refresh();
        $this->assertSame(60000, $wallet->balance);
        $this->assertSame(1, PackageOrder::query()->count());
        $this->assertSame(1, LedgerTransaction::query()->where('type', LedgerTransactionType::WifiPackage)->count());
        $this->assertDatabaseMissing('ledger_transactions', ['type' => LedgerTransactionType::Refund->value]);
        $this->assertSame(2, $requestCount);
    }

    public function test_confirmed_provider_success_can_be_completed_on_idempotent_replay_after_database_error(): void
    {
        $this->app->instance(PackageActivationServiceInterface::class, app(RealPackageActivationService::class));
        $this->configureCgNet();
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response($this->cgnetToken()),
            'provider.test/WalletApp/ServerCommand/ActivateCGNETAccountAPI' => Http::response(['ErrCode' => 0, 'Message' => 'ok']),
        ]);

        $user = User::factory()->create();
        $wallet = $this->wallet($user, 80000);
        $package = $this->makePackage(price: 20000, months: 1);
        $failOnce = true;
        CustomerPackage::creating(function () use (&$failOnce): void {
            if ($failOnce) {
                $failOnce = false;
                throw new \RuntimeException('simulated local persistence failure');
            }
        });

        $first = $this->buy($user, $package, 'provider-save-retry')
            ->assertStatus(500)
            ->assertJsonPath('data.status', PackageOrderStatus::PendingVerification->value);
        $this->assertArrayNotHasKey('credentials', $first->json('data'));

        $this->buy($user, $package, 'provider-save-retry')
            ->assertOk()
            ->assertJsonPath('data.status', PackageOrderStatus::Completed->value);

        $wallet->refresh();
        $this->assertSame(60000, $wallet->balance);
        $this->assertDatabaseCount('customer_packages', 1);
        $this->assertDatabaseCount('ledger_transactions', 1);
        Http::assertSentCount(2);
    }

    public function test_credentials_are_not_in_profile_package_list_and_are_owner_only_on_the_dedicated_route(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $wallet = $this->wallet($user, 50000);
        $package = $this->makePackage(price: 10000, months: 1);
        $this->buy($user, $package, 'credentials-owner-1')->assertOk();
        $customerPackage = CustomerPackage::query()->firstOrFail();

        $profile = $this->withToken($user->createToken('profile')->plainTextToken, 'Bearer')
            ->getJson('/api/customer/profile')
            ->assertOk();
        $this->assertArrayNotHasKey('username', $profile->json('data.packages.0'));
        $this->assertArrayNotHasKey('password', $profile->json('data.packages.0'));

        Auth::forgetGuards();
        $this->withToken($otherUser->createToken('other')->plainTextToken, 'Bearer')
            ->getJson('/api/customer/packages/'.$customerPackage->id.'/credentials')
            ->assertNotFound();

        Auth::forgetGuards();
        $this->withToken($user->createToken('owner')->plainTextToken, 'Bearer')
            ->getJson('/api/customer/packages/'.$customerPackage->id.'/credentials')
            ->assertOk()
            ->assertJsonPath('data.credentials.account', $customerPackage->username)
            ->assertJsonPath('data.credentials.password', $customerPackage->password);
    }

    public function test_idempotency_key_header_is_required(): void
    {
        $user = User::factory()->create();
        $wallet = $this->wallet($user, 150000);
        $package = $this->makePackage(price: 10000, months: 1);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/packages/buy', ['product_id' => $package->provider_product_id])
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
        $this->postJson('/api/packages/buy', ['product_id' => 'any'])
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

    private function configureCgNet(): void
    {
        config([
            'services.cg_api.url' => 'https://provider.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'provider-client',
            'services.cg_api.client_secret' => 'provider-secret',
        ]);
    }

    /**
     * @return array{ErrCode: int, Message: string, token: string}
     */
    private function cgnetToken(): array
    {
        return [
            'ErrCode' => 0,
            'Message' => 'ok',
            'token' => 'provider-access-token',
        ];
    }

    private function makePackage(int $price, int $months, bool $active = true): Package
    {
        $this->mbps++;

        $package = Package::factory()->create([
            'network_id' => $this->network->id,
            'speed_id' => Speed::factory()->create(['mbps' => $this->mbps])->id,
            'term_id' => Term::factory()->create(['months' => $months])->id,
            'price' => $price,
            'is_active' => $active,
        ]);
        $productId = 'p-'.fake()->unique()->numerify('###');
        $package->setAttribute('provider_product_id', $productId);
        $this->catalog->catalogProducts[] = [
            'productID' => $productId,
            'productName' => 'Provider package '.$productId,
            'productPrice' => (float) $price,
            'monthCycle' => $months,
            'monthCycleUnit' => '月',
            'productLine' => 'test-line',
            'productSpeed' => (string) $this->mbps,
            'installationFee' => null,
        ];

        return $package;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function buy(User $user, Package $package, string $key, array $extra = []): TestResponse
    {
        return $this->buyRaw($user, ['product_id' => $package->provider_product_id, ...$extra], $key);
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
