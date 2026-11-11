<?php

namespace Tests\Feature;

use App\Enums\BillPaymentNotificationEvent;
use App\Enums\BillPaymentStatus;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\TransactionIdPrefix;
use App\Enums\WalletStatus;
use App\Jobs\ReconcileStuckInternetBillPaymentsJob;
use App\Models\Admin;
use App\Models\BillPayment;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\InternetBillPaymentStatusNotification;
use App\Services\BroarbandAccount\AccountInfoService;
use App\Services\CgNetApiClient;
use App\Services\InternetBill\InternetBillPaymentService;
use App\Services\Ledger\TransactionIdGenerator;
use App\Services\Order\OrderNumberGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InternetBillPaymentTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $accountInfo;

    private MockInterface $cgNet;

    private bool $accountNotFound = false;

    private array $rechargeResponse = ['ErrCode' => 0, 'Message' => 'success', 'billing_ref' => 'BILL-1001'];

    private int $rechargeStatus = 200;

    /** @var list<array{command: string, payload: array<string, mixed>}> */
    private array $rechargeRequests = [];

    /** @var list<array{service_type: string, account: string}> */
    private array $accountLookups = [];

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->accountInfo = Mockery::mock(AccountInfoService::class);
        $this->accountInfo
            ->shouldReceive('find')
            ->byDefault()
            ->andReturnUsing(function (string $serviceType, string $account): ?array {
                $this->accountLookups[] = ['service_type' => $serviceType, 'account' => $account];

                if ($this->accountNotFound) {
                    return null;
                }

                return [
                    'account' => $account,
                    'productID' => 'ftth-product-1',
                    'productName' => 'FTTH 100M',
                    'productPrice' => 1000,
                    'productLine' => 'FTTH',
                    'productSpeed' => '100M',
                    'monthCycle' => 1,
                    'monthCycleUnit' => '月',
                    'installationFee' => 0,
                ];
            });
        $this->app->instance(AccountInfoService::class, $this->accountInfo);

        $this->cgNet = Mockery::mock(CgNetApiClient::class);
        $this->cgNet
            ->shouldReceive('post')
            ->byDefault()
            ->withArgs(function (string $command, array $payload): bool {
                $this->rechargeRequests[] = ['command' => $command, 'payload' => $payload];

                return $command === 'RechargeAPI';
            })
            ->andReturnUsing(
                fn() => new Response(Http::response($this->rechargeResponse, $this->rechargeStatus)->wait()),
            );
        $this->app->instance(CgNetApiClient::class, $this->cgNet);
    }

    public function test_pending_slip_returns_bill_month_from_billing_server(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->startOfDay());

        Http::fake([
            'billing-server.test/bill-details*' => Http::response(
                [
                    'amount' => 1000,
                    'bill_month' => '2026-11',
                    'bill_month_label' => 'Nov 2026',
                    'slip_url' => 'https://billing-server.test/slips/next-month',
                ],
                200,
            ),
            ...$this->broadbandAccountFake(),
        ]);

        [$user] = $this->makePayer(balance: 1500);
        $user->update(['name' => 'App Profile Name']);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/internet-bills/pending-slip')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bill_month', '2026-11')
            ->assertJsonPath('data.bill_month_label', 'November 2026')
            ->assertJsonPath('data.customer_name', 'Broadband Account Holder')
            ->assertJsonPath('data.payment_method', 'CTO')
            ->assertJsonPath('data.slip.slip_url', 'https://billing-server.test/slips/next-month');

        Http::assertSent(
            fn($request) => str_contains($request->url(), 'account_number=' . $user->broadband_account_number) &&
                !str_contains($request->url(), 'bill_month='),
        );
    }

    public function test_pending_slip_does_not_calculate_month_from_local_payment_count(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->startOfDay());

        Http::fake([
            'billing-server.test/bill-details*' => Http::response(
                [
                    'amount' => 1000,
                    'bill_month' => '2026-12',
                    'bill_month_label' => 'Dec 2026',
                    'slip_url' => 'https://billing-server.test/slips/december',
                ],
                200,
            ),
            ...$this->broadbandAccountFake(),
        ]);

        [$user] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/internet-bills/pending-slip')
            ->assertOk()
            ->assertJsonPath('data.bill_month', '2026-12')
            ->assertJsonPath('data.slip.slip_url', 'https://billing-server.test/slips/december');

        Http::assertSent(fn($request) => !str_contains($request->url(), 'bill_month='));
    }

    public function test_pending_slip_label_formats_january_with_the_new_year(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 2)->startOfDay());

        Http::fake([
            'billing-server.test/bill-details*' => Http::response(
                [
                    'amount' => 1000,
                    'bill_month' => '2027-01',
                    'bill_month_label' => 'Jan 2027',
                    'slip_url' => 'https://billing-server.test/slips/january',
                ],
                200,
            ),
            ...$this->broadbandAccountFake(),
        ]);

        [$user] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/internet-bills/pending-slip')
            ->assertOk()
            ->assertJsonPath('data.bill_month', '2027-01')
            ->assertJsonPath('data.bill_month_label', 'January 2027');
    }

    public function test_pending_slip_returns_current_month_when_current_month_is_unpaid(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->startOfDay());

        Http::fake([
            'billing-server.test/bill-details*' => Http::response(
                [
                    'amount' => 1000,
                    'bill_month' => '2026-10',
                    'bill_month_label' => 'Oct 2026',
                    'slip_url' => 'https://billing-server.test/slips/current-month',
                ],
                200,
            ),
            ...$this->broadbandAccountFake(),
        ]);

        [$user] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/internet-bills/pending-slip')
            ->assertOk()
            ->assertJsonPath('data.bill_month', '2026-10')
            ->assertJsonPath('data.bill_month_label', 'October 2026')
            ->assertJsonPath('data.slip.slip_url', 'https://billing-server.test/slips/current-month');

        Http::assertSent(fn($request) => !str_contains($request->url(), 'bill_month='));
    }

    public function test_pending_slip_uses_server_bill_month_when_payment_is_confirmed_later(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->startOfDay());

        Http::fake([
            'billing-server.test/bill-details*' => Http::response(
                [
                    'amount' => 250,
                    'bill_month' => '2026-10',
                    'bill_month_label' => 'Oct 2026',
                    'slip_url' => 'https://billing-server.test/slips/october',
                ],
                200,
            ),
            ...$this->broadbandAccountFake(),
        ]);

        [$user, $wallet] = $this->makePayer(balance: 1500);
        $transaction = LedgerTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill,
            'status' => LedgerTransactionStatus::Completed,
        ]);
        BillPayment::query()->create([
            'ledger_transaction_id' => $transaction->id,
            'broadband_account_number' => $user->broadband_account_number,
            'status' => BillPaymentStatus::Completed,
            'confirmed_at' => now(),
        ]);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/internet-bills/pending-slip')
            ->assertOk()
            ->assertJsonPath('data.bill_month', '2026-10')
            ->assertJsonPath('data.bill_month_label', 'October 2026')
            ->assertJsonPath('data.slip.slip_url', 'https://billing-server.test/slips/october');

        Http::assertSent(fn($request) => !str_contains($request->url(), 'bill_month='));
    }

    public function test_pending_slip_uses_server_month_even_when_local_payment_exists(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->startOfDay());

        Http::fake([
            'billing-server.test/bill-details*' => Http::response(
                [
                    'amount' => 250,
                    'bill_month' => '2026-10',
                    'bill_month_label' => 'Oct 2026',
                    'slip_url' => 'https://billing-server.test/slips/october',
                ],
                200,
            ),
            ...$this->broadbandAccountFake(),
        ]);

        [$user, $wallet] = $this->makePayer(balance: 1500);
        $transaction = LedgerTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill,
            'status' => LedgerTransactionStatus::Completed,
        ]);
        BillPayment::query()->create([
            'ledger_transaction_id' => $transaction->id,
            'broadband_account_number' => $user->broadband_account_number,
            'status' => BillPaymentStatus::Completed,
            'confirmed_at' => now(),
        ]);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/internet-bills/pending-slip')
            ->assertOk()
            ->assertJsonPath('data.bill_month', '2026-10');
    }

    public function test_paid_slips_returns_all_slips_from_billing_server(): void
    {
        $billingPaidSlips = [
            [
                'bill_month' => '2026-08',
                'amount' => 250,
                'payment_ref' => 'PAY-1000',
            ],
            [
                'bill_month' => '2026-09',
                'amount' => 250,
                'payment_ref' => 'PAY-1001',
            ],
        ];

        Http::fake([
            'billing-server.test/bill-details*' => Http::response(
                [
                    'amount' => 250,
                    'bill_month' => '2026-10',
                    'paid_slips' => $billingPaidSlips,
                ],
                200,
            ),
            ...$this->broadbandAccountFake(),
        ]);

        [$user] = $this->makePayer(balance: 1500);
        $expectedPaidSlips = [
            [
                'bill_month' => '2026-09',
                'bill_month_label' => 'September 2026',
                'customer_name' => 'Broadband Account Holder',
                'payment_method' => 'CTO',
                'slip' => [
                    'amount' => 250,
                    'account_number' => $user->broadband_account_number,
                ],
            ],
            [
                'bill_month' => '2026-08',
                'bill_month_label' => 'August 2026',
                'customer_name' => 'Broadband Account Holder',
                'payment_method' => 'CTO',
                'slip' => [
                    'amount' => 250,
                    'account_number' => $user->broadband_account_number,
                ],
            ],
        ];

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/internet-bills/paid-slips')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.paid_slips', $expectedPaidSlips);

        Http::assertSent(
            fn($request) => str_contains($request->url(), 'account_number=' . $user->broadband_account_number) &&
                !str_contains($request->url(), 'bill_month='),
        );
    }

    public function test_bill_payment_history_includes_payments_for_the_bound_account_from_any_wallet(): void
    {
        [$user, $wallet] = $this->makePayer(balance: 1500);
        [$otherUser, $otherWallet] = $this->makePayer(balance: 1500);

        $otherAccountTransaction = LedgerTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill,
            'status' => LedgerTransactionStatus::Completed,
            'amount' => 1000,
        ]);
        BillPayment::query()->create([
            'ledger_transaction_id' => $otherAccountTransaction->id,
            'broadband_account_number' => $otherUser->broadband_account_number,
            'order_no' => 'FTTH-HISTORY-OTHER',
            'status' => BillPaymentStatus::Completed,
        ]);

        $boundAccountTransaction = LedgerTransaction::factory()->create([
            'wallet_id' => $otherWallet->id,
            'type' => LedgerTransactionType::FtthBill,
            'status' => LedgerTransactionStatus::Completed,
            'amount' => 1000,
        ]);
        $boundBillPayment = BillPayment::query()->create([
            'ledger_transaction_id' => $boundAccountTransaction->id,
            'broadband_account_number' => $user->broadband_account_number,
            'order_no' => 'FTTH-HISTORY-BOUND',
            'service_type' => 'FTTH',
            'product_id' => 'ftth-product-1',
            'product_price' => 1000,
            'snapshot' => [
                'product_name' => 'FTTH 100M',
                'product_line' => 'FTTH',
                'product_speed' => '100M',
                'month_cycle' => 1,
                'month_cycle_unit' => 'month',
            ],
            'status' => BillPaymentStatus::Completed,
            'confirmed_at' => now(),
        ]);

        $olderBoundAccountTransaction = LedgerTransaction::factory()->create([
            'wallet_id' => $otherWallet->id,
            'type' => LedgerTransactionType::FtthBill,
            'status' => LedgerTransactionStatus::Completed,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        BillPayment::query()->create([
            'ledger_transaction_id' => $olderBoundAccountTransaction->id,
            'broadband_account_number' => $user->broadband_account_number,
            'order_no' => 'FTTH-HISTORY-OLDER',
            'status' => BillPaymentStatus::Completed,
            'created_at' => now()->subDay(),
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $firstPage = $this->withToken($token, 'Bearer')
            ->getJson('/api/internet-bills/history?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $boundBillPayment->id)
            ->assertJsonPath('data.0.transaction_no', $boundAccountTransaction->transaction_no)
            ->assertJsonPath('data.0.type', 'ftth_bill')
            ->assertJsonPath('data.0.account', $user->broadband_account_number)
            ->assertJsonPath('data.0.service_type', 'FTTH')
            ->assertJsonPath('data.0.amount', '1000.00')
            ->assertJsonPath('data.0.status', BillPaymentStatus::Completed->value)
            ->assertJsonPath('data.0.title_code', 'MSG-FTTH-008')
            ->assertJsonPath('data.0.description_code', 'MSG-FTTH-008')
            ->assertJsonMissingPath('data.0.order_no')
            ->assertJsonMissingPath('data.0.package');

        $nextPageUrl = $firstPage->json('links.next');
        $this->assertIsString($nextPageUrl);
        parse_str((string) parse_url($nextPageUrl, PHP_URL_QUERY), $query);

        $this->withToken($token, 'Bearer')
            ->getJson('/api/internet-bills/history?' . http_build_query($query))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.transaction_no', $olderBoundAccountTransaction->transaction_no)
            ->assertJsonPath('data.0.account', $user->broadband_account_number);

        $this->withToken($token, 'Bearer')
            ->getJson('/api/internet-bills/history/'.$boundBillPayment->id)
            ->assertOk()
            ->assertJsonPath('data.id', $boundBillPayment->id)
            ->assertJsonPath('data.transaction_no', $boundAccountTransaction->transaction_no)
            ->assertJsonPath('data.type', 'ftth_bill')
            ->assertJsonPath('data.order_no', 'FTTH-HISTORY-BOUND')
            ->assertJsonPath('data.account', $user->broadband_account_number)
            ->assertJsonPath('data.service_type', 'FTTH')
            ->assertJsonPath('data.amount', '1000.00')
            ->assertJsonPath('data.package.product_name', 'FTTH 100M')
            ->assertJsonPath('data.package.product_speed', '100M')
            ->assertJsonPath('data.status', BillPaymentStatus::Completed->value)
            ->assertJsonPath('data.title_code', 'MSG-FTTH-008')
            ->assertJsonPath('data.description_code', 'MSG-FTTH-008');

        $this->withToken($token, 'Bearer')
            ->getJson('/api/internet-bills/history/'.$otherAccountTransaction->billPayment->id)
            ->assertNotFound();
    }

    public function test_ftth_bill_payment_requires_the_correct_user_password(): void
    {
        Http::fake();

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => 'ftth-pay-wrong-password',
                'password' => '654321',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'MSG-USER-002')
            ->assertJsonValidationErrors(['password']);

        $wallet->refresh();
        $this->assertEquals(1500, $wallet->balance);
        $this->assertDatabaseCount('ledger_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_ftth_bill_payment_password_must_match_registration_format(): void
    {
        Http::fake();

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => 'ftth-pay-invalid-password-format',
                'password' => '12345',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $wallet->refresh();
        $this->assertEquals(1500, $wallet->balance);
        $this->assertDatabaseCount('ledger_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_user_can_pay_ftth_bill_when_wallet_has_enough_balance(): void
    {
        Notification::fake();

        Http::fake();

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => 'ftth-pay-success-1',
                'password' => '123456',
                'remark' => 'monthly bill',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', '1000.00')
            ->assertJsonPath('data.billing_status', 'completed')
            ->assertJsonPath(
                'data.transaction_no',
                fn (string $transactionNo) => app(TransactionIdGenerator::class)
                    ->matches($transactionNo, TransactionIdPrefix::PayBillFtth),
            )
            ->assertJsonPath(
                'data.order_no',
                fn (string $orderNo) => app(OrderNumberGenerator::class)->matches($orderNo),
            )
            ->assertJsonPath('data.account', $user->broadband_account_number)
            ->assertJsonPath('data.service_type', 'FTTH')
            ->assertJsonPath('data.customer_name', $user->name)
            ->assertJsonPath('data.package.product_id', 'ftth-product-1')
            ->assertJsonPath('data.package.product_name', 'FTTH 100M')
            ->assertJsonPath('data.package.product_line', 'FTTH')
            ->assertJsonPath('data.package.product_price', '1000.00')
            ->assertJsonPath('data.package.product_speed', '100M')
            ->assertJsonPath('data.package.month_cycle', 1)
            ->assertJsonPath('data.package.month_cycle_unit', '月')
            ->assertJsonPath('data.remark', 'monthly bill')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath(
                'data.created_at',
                fn(string $createdAt) => preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $createdAt) === 1,
            )
            ->assertJsonPath(
                'data.confirmed_at',
                fn(string $confirmedAt) => preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $confirmedAt) === 1,
            );

        $wallet->refresh();
        $this->assertEquals(500, $wallet->balance);
        $this->assertDatabaseHas('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill->value,
            'status' => LedgerTransactionStatus::Completed->value,
            'amount' => 1000,
        ]);
        $this->assertDatabaseHas('bill_payments', [
            'status' => BillPaymentStatus::Completed->value,
            'broadband_account_number' => $user->broadband_account_number,
        ]);

        $billPayment = BillPayment::query()
            ->where('broadband_account_number', $user->broadband_account_number)
            ->where('status', BillPaymentStatus::Completed)
            ->latest('id')
            ->firstOrFail();

        $this->assertIsArray($billPayment->external_response);
        $this->assertNotEmpty($billPayment->order_no);
        $this->assertSame('FTTH', $billPayment->service_type);
        $this->assertSame('ftth-product-1', $billPayment->product_id);
        $this->assertSame('1000.00', $billPayment->product_price);
        $this->assertSame('monthly bill', $billPayment->remark);
        $this->assertSame(
            [
                'product_id' => 'ftth-product-1',
                'product_name' => 'FTTH 100M',
                'product_price' => 1000,
                'month_cycle' => 1,
                'month_cycle_unit' => '月',
                'product_line' => 'FTTH',
                'product_speed' => '100M',
            ],
            $billPayment->snapshot,
        );
        $this->assertSame($user->broadband_account_number, $billPayment->external_response['broadband_account_number']);
        $this->assertSame('success', $billPayment->external_response['outcome'] ?? null);
        $this->assertSame('BILL-1001', $billPayment->external_response['payload']['billing_ref'] ?? null);
        $this->assertNotNull($billPayment->confirmed_at);
        $this->assertDatabaseHas('ledger_entries', [
            'wallet_id' => $wallet->id,
            'debit' => 1000,
            'credit' => 0,
        ]);

        $this->assertCount(1, $this->rechargeRequests);
        $this->assertSame('RechargeAPI', $this->rechargeRequests[0]['command']);
        $this->assertSame($billPayment->order_no, $this->rechargeRequests[0]['payload']['OrderNo']);
        $this->assertSame($user->broadband_account_number, $this->rechargeRequests[0]['payload']['Account']);
        $this->assertSame('FTTH', $this->rechargeRequests[0]['payload']['ServiceType']);
        $this->assertSame('ftth-product-1', $this->rechargeRequests[0]['payload']['ProductID']);
        $this->assertSame(1000.0, $this->rechargeRequests[0]['payload']['ProductPrice']);
        $this->assertSame('monthly bill', $this->rechargeRequests[0]['payload']['Remark']);
        $this->assertSame(
            [['service_type' => 'FTTH', 'account' => $user->broadband_account_number]],
            $this->accountLookups,
        );

        Notification::assertSentToTimes($user, InternetBillPaymentStatusNotification::class, 1);
        Notification::assertSentTo(
            $user,
            InternetBillPaymentStatusNotification::class,
            fn(InternetBillPaymentStatusNotification $notification) => $notification->event ===
                BillPaymentNotificationEvent::Completed && $notification->amount === 1000,
        );
    }

    public function test_user_can_pay_cg_bill_with_pbcg_transaction_id(): void
    {
        Http::fake();

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'CG',
                'idempotency_key' => 'cg-pay-success-1',
                'password' => '123456',
            ])
            ->assertOk()
            ->assertJsonPath('data.service_type', 'CG')
            ->assertJsonPath(
                'data.transaction_no',
                fn (string $transactionNo) => app(TransactionIdGenerator::class)
                    ->matches($transactionNo, TransactionIdPrefix::PayBillCg),
            );

        $this->assertEquals(500, $wallet->fresh()->balance);
        $this->assertSame('CG', BillPayment::query()->value('service_type'));
        $this->assertDatabaseHas('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::CgBill->value,
        ]);
        $this->assertTrue(app(OrderNumberGenerator::class)->matches((string) BillPayment::query()->value('order_no')));
    }

    public function test_bill_payment_submits_expected_payload_through_mocked_cg_net_client(): void
    {
        [$user] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => 'ftth-mocked-cg-api',
                'password' => '123456',
                'remark' => 'customer note',
            ])
            ->assertOk()
            ->assertJsonPath('data.billing_status', 'completed');

        $orderNo = BillPayment::query()->value('order_no');
        $this->assertNotEmpty($orderNo);

        $this->assertSame(
            [['service_type' => 'FTTH', 'account' => $user->broadband_account_number]],
            $this->accountLookups,
        );
        $this->assertCount(1, $this->rechargeRequests);
        $this->assertSame('RechargeAPI', $this->rechargeRequests[0]['command']);
        $this->assertSame(
            [
                'OrderNo' => $orderNo,
                'Account' => $user->broadband_account_number,
                'ServiceType' => 'FTTH',
                'ProductID' => 'ftth-product-1',
                'ProductPrice' => 1000.0,
                'Remark' => 'customer note',
            ],
            $this->rechargeRequests[0]['payload'],
        );

        $snapshot = BillPayment::query()->firstOrFail()->snapshot;
        $this->assertSame('FTTH 100M', $snapshot['product_name']);
        $this->assertSame('FTTH', $snapshot['product_line']);
        $this->assertSame('100M', $snapshot['product_speed']);
        $this->assertSame('ftth-product-1', $snapshot['product_id']);
        $this->assertSame(1000, $snapshot['product_price']);
    }

    public function test_wallet_is_refunded_when_external_billing_rejects_payment(): void
    {
        Notification::fake();

        Http::fake();
        $this->rechargeResponse = ['ErrCode' => 100, 'Message' => 'Billing API rejected the request.'];

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => 'ftth-pay-fail-1',
                'password' => '123456',
            ])
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'External billing payment failed. Wallet was refunded.')
            ->assertJsonPath('data.account', $user->broadband_account_number)
            ->assertJsonPath('data.customer_name', $user->name)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.billing_status', 'failed')
            ->assertJsonPath('data.created_at', fn(string $createdAt) => !empty($createdAt))
            ->assertJsonPath('data.confirmed_at', fn(string $confirmedAt) => !empty($confirmedAt))
            ->assertJsonPath('data.refund_transaction_no', fn(string $transactionNo) => !empty($transactionNo))
            ->assertJsonStructure([
                'data' => [
                    'transaction_no',
                    'order_no',
                    'account',
                    'service_type',
                    'customer_name',
                    'amount',
                    'package' => [
                        'product_id',
                        'product_name',
                        'product_price',
                        'product_line',
                        'product_speed',
                        'month_cycle',
                        'month_cycle_unit',
                    ],
                    'remark',
                    'status',
                    'created_at',
                    'confirmed_at',
                ],
            ]);

        $wallet->refresh();
        $this->assertEquals(1500, $wallet->balance);
        $this->assertDatabaseHas('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::Refund->value,
            'amount' => 1000,
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill->value,
            'status' => LedgerTransactionStatus::Failed->value,
        ]);

        $refund = LedgerTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', LedgerTransactionType::Refund)
            ->firstOrFail();

        Notification::assertSentToTimes($user, InternetBillPaymentStatusNotification::class, 1);
        Notification::assertSentTo(
            $user,
            InternetBillPaymentStatusNotification::class,
            fn(InternetBillPaymentStatusNotification $notification) => $notification->event ===
                BillPaymentNotificationEvent::Refunded &&
                $notification->refundTransactionNo === $refund->transaction_no,
        );
    }

    #[DataProvider('providerRejectionCodes')]
    public function test_account_and_product_rejection_codes_refund_the_payment(int $errorCode): void
    {
        Notification::fake();

        $this->rechargeResponse = [
            'ErrCode' => $errorCode,
            'Message' => 'The account or product does not exist.',
        ];

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => 'ftth-reject-code-' . $errorCode,
                'password' => '123456',
            ])
            ->assertStatus(502)
            ->assertJsonPath('success', false);

        $this->assertEquals(1500, $wallet->fresh()->balance);
        $this->assertDatabaseHas('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill->value,
            'status' => LedgerTransactionStatus::Failed->value,
        ]);
    }

    public static function providerRejectionCodes(): array
    {
        return [
            'account does not exist' => [2],
            'product does not exist' => [500],
        ];
    }

    public function test_duplicate_recharge_order_is_treated_as_success_and_sends_all_required_fields(): void
    {
        Notification::fake();
        $this->rechargeResponse = [
            'ErrCode' => 1,
            'Message' => 'The order has already been renewed.',
        ];

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => 'ftth-recharge-duplicate-1',
                'password' => '123456',
            ])
            ->assertOk()
            ->assertJsonPath('data.billing_status', 'completed');

        $wallet->refresh();
        $this->assertEquals(500, $wallet->balance);
        $this->assertSame('RechargeAPI', $this->rechargeRequests[0]['command']);
        $this->assertArrayHasKey('Remark', $this->rechargeRequests[0]['payload']);
        $this->assertSame('', $this->rechargeRequests[0]['payload']['Remark']);
        $this->assertDatabaseMissing('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::Refund->value,
        ]);
    }

    public function test_unknown_billing_outcome_leaves_payment_processing(): void
    {
        Queue::fake();
        Notification::fake();

        Http::fake();
        $this->rechargeResponse = ['Message' => 'Gateway timeout'];
        $this->rechargeStatus = 504;

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => 'ftth-pay-unknown-1',
                'password' => '123456',
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.billing_status', 'processing')
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.account', $user->broadband_account_number)
            ->assertJsonPath('data.service_type', 'FTTH')
            ->assertJsonPath('data.customer_name', $user->name)
            ->assertJsonPath('data.package.product_name', 'FTTH 100M')
            ->assertJsonPath('data.package.product_speed', '100M')
            ->assertJsonPath('data.created_at', fn(string $createdAt) => !empty($createdAt))
            ->assertJsonPath('data.confirmed_at', null)
            ->assertJsonStructure([
                'data' => [
                    'transaction_no',
                    'order_no',
                    'account',
                    'service_type',
                    'customer_name',
                    'amount',
                    'package' => [
                        'product_id',
                        'product_name',
                        'product_price',
                        'product_line',
                        'product_speed',
                        'month_cycle',
                        'month_cycle_unit',
                    ],
                    'remark',
                    'status',
                    'created_at',
                    'confirmed_at',
                ],
            ]);

        $wallet->refresh();
        $this->assertEquals(500, $wallet->balance);
        $this->assertDatabaseHas('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill->value,
            'status' => LedgerTransactionStatus::Processing->value,
        ]);
        $this->assertDatabaseMissing('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::Refund->value,
        ]);

        $transaction = LedgerTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', LedgerTransactionType::FtthBill)
            ->firstOrFail();

        Queue::assertPushed(
            ReconcileStuckInternetBillPaymentsJob::class,
            fn(ReconcileStuckInternetBillPaymentsJob $job) => $job->ledgerTransactionId === $transaction->id,
        );

        Notification::assertSentTo(
            $user,
            InternetBillPaymentStatusNotification::class,
            fn(InternetBillPaymentStatusNotification $notification) => $notification->event ===
                BillPaymentNotificationEvent::Processing &&
                $notification->transactionNo === $transaction->transaction_no,
        );
    }

    public function test_wallet_balance_must_be_sufficient_before_payments_are_attempted(): void
    {
        Http::fake();

        [$user, $wallet] = $this->makePayer(balance: 500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => 'ftth-pay-insufficient-1',
                'password' => '123456',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Insufficient wallet balance.');

        $wallet->refresh();
        $this->assertEquals(500, $wallet->balance);
        Http::assertNothingSent();
        $this->assertSame([], $this->rechargeRequests);
    }

    public function test_idempotent_replay_returns_completed_payment(): void
    {
        Notification::fake();

        Http::fake();

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $payload = [
            'broadband_account_number' => $user->broadband_account_number,
            'service_type' => 'FTTH',
            'idempotency_key' => 'ftth-pay-idempotent-1',
            'password' => '123456',
        ];

        $first = $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', $payload)
            ->assertOk();
        $orderNo = $first->json('data.order_no');
        $transactionNo = $first->json('data.transaction_no');

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', $payload)
            ->assertOk()
            ->assertJsonPath('data.order_no', $orderNo)
            ->assertJsonPath('data.transaction_no', $transactionNo)
            ->assertJsonPath('message', 'Internet bill payment already processed.')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.account', $user->broadband_account_number)
            ->assertJsonPath('data.customer_name', $user->name)
            ->assertJsonPath('data.confirmed_at', fn(string $confirmedAt) => !empty($confirmedAt))
            ->assertJsonStructure([
                'data' => [
                    'transaction_no',
                    'order_no',
                    'account',
                    'service_type',
                    'customer_name',
                    'amount',
                    'package' => [
                        'product_id',
                        'product_name',
                        'product_price',
                        'product_line',
                        'product_speed',
                        'month_cycle',
                        'month_cycle_unit',
                    ],
                    'remark',
                    'status',
                    'created_at',
                    'confirmed_at',
                ],
            ]);

        $wallet->refresh();
        $this->assertEquals(500, $wallet->balance);
        $this->assertSame(
            1,
            LedgerTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('type', LedgerTransactionType::FtthBill)
                ->count(),
        );

        Notification::assertSentToTimes($user, InternetBillPaymentStatusNotification::class, 1);
    }

    public function test_idempotent_replay_after_failure_returns_refunded_result(): void
    {
        Notification::fake();

        Http::fake();
        $this->rechargeResponse = ['ErrCode' => 100, 'Message' => 'Rejected'];

        [$user] = $this->makePayer(balance: 1500);

        $payload = [
            'broadband_account_number' => $user->broadband_account_number,
            'service_type' => 'FTTH',
            'idempotency_key' => 'ftth-pay-fail-replay-1',
            'password' => '123456',
        ];

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', $payload)
            ->assertStatus(502);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', $payload)
            ->assertStatus(502)
            ->assertJsonPath('data.billing_status', 'failed')
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.account', $user->broadband_account_number)
            ->assertJsonPath('data.customer_name', $user->name)
            ->assertJsonPath('data.confirmed_at', fn(string $confirmedAt) => !empty($confirmedAt))
            ->assertJsonPath('data.refund_transaction_no', fn(string $transactionNo) => !empty($transactionNo))
            ->assertJsonStructure([
                'data' => [
                    'transaction_no',
                    'order_no',
                    'account',
                    'service_type',
                    'customer_name',
                    'amount',
                    'package' => [
                        'product_id',
                        'product_name',
                        'product_price',
                        'product_line',
                        'product_speed',
                        'month_cycle',
                        'month_cycle_unit',
                    ],
                    'remark',
                    'status',
                    'created_at',
                    'confirmed_at',
                ],
            ]);

        Notification::assertSentToTimes($user, InternetBillPaymentStatusNotification::class, 1);
    }

    public function test_reconcile_job_completes_stuck_processing_payment(): void
    {
        Notification::fake();

        Http::fake();
        $this->rechargeResponse = [
            'ErrCode' => 0,
            'Message' => 'success',
            'billing_ref' => 'BILL-RECON-1',
            'OrderNo' => 'PAY-RECON-1',
        ];

        [$user, $wallet] = $this->makePayer(balance: 500);

        $transaction = LedgerTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill,
            'status' => LedgerTransactionStatus::Processing,
            'amount' => 1000,
            'idempotency_key' => 'ftth-stuck-1',
            'transaction_no' => 'FTTH-STUCK-1',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        BillPayment::query()->create([
            'ledger_transaction_id' => $transaction->id,
            'broadband_account_number' => $user->broadband_account_number,
            'order_no' => 'FTTH-RECON-1',
            'service_type' => 'FTTH',
            'product_id' => 'ftth-product-1',
            'product_price' => 1000,
            'snapshot' => [
                'product_id' => 'ftth-product-1',
                'product_name' => 'FTTH 100M',
                'product_price' => 1000,
                'month_cycle' => 1,
                'month_cycle_unit' => '月',
                'product_line' => 'FTTH',
                'product_speed' => '100M',
            ],
            'status' => BillPaymentStatus::Processing,
            'external_response' => [
                'broadband_account_number' => $user->broadband_account_number,
            ],
        ]);

        (new ReconcileStuckInternetBillPaymentsJob($transaction->id))->handle(app(InternetBillPaymentService::class));

        $transaction->refresh();
        $this->assertSame(LedgerTransactionStatus::Completed, $transaction->status);
        $this->assertDatabaseHas('bill_payments', [
            'ledger_transaction_id' => $transaction->id,
            'status' => BillPaymentStatus::Completed->value,
        ]);
        $this->assertSame('FTTH-RECON-1', $this->rechargeRequests[0]['payload']['OrderNo']);
        $this->assertSame($user->broadband_account_number, $this->accountLookups[0]['account']);

        $billPayment = BillPayment::query()->where('ledger_transaction_id', $transaction->id)->firstOrFail();
        $this->assertIsArray($billPayment->external_response);
        $this->assertSame('success', $billPayment->external_response['outcome'] ?? null);
        $this->assertNotNull($billPayment->confirmed_at);

        Notification::assertSentTo(
            $user,
            InternetBillPaymentStatusNotification::class,
            fn(InternetBillPaymentStatusNotification $notification) => $notification->event ===
                BillPaymentNotificationEvent::Completed,
        );

        (new ReconcileStuckInternetBillPaymentsJob($transaction->id))->handle(app(InternetBillPaymentService::class));

        Notification::assertSentToTimes($user, InternetBillPaymentStatusNotification::class, 1);
    }

    public function test_reconcile_job_refunds_when_billing_reports_not_found(): void
    {
        Notification::fake();

        Http::fake();
        $this->rechargeResponse = ['ErrCode' => 100, 'Message' => 'Recharge rejected.'];

        [$user, $wallet] = $this->makePayer(balance: 500);

        $transaction = LedgerTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill,
            'status' => LedgerTransactionStatus::Processing,
            'amount' => 1000,
            'idempotency_key' => 'ftth-stuck-refund-1',
            'transaction_no' => 'FTTH-STUCK-REFUND-1',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        BillPayment::query()->create([
            'ledger_transaction_id' => $transaction->id,
            'broadband_account_number' => $user->broadband_account_number,
            'order_no' => 'FTTH-RECON-REFUND-1',
            'service_type' => 'FTTH',
            'product_id' => 'ftth-product-1',
            'product_price' => 1000,
            'status' => BillPaymentStatus::Processing,
            'external_response' => [
                'broadband_account_number' => $user->broadband_account_number,
            ],
        ]);

        (new ReconcileStuckInternetBillPaymentsJob($transaction->id))->handle(app(InternetBillPaymentService::class));

        $wallet->refresh();
        $transaction->refresh();
        $this->assertEquals(1500, $wallet->balance);
        $this->assertSame(LedgerTransactionStatus::Failed, $transaction->status);
        $this->assertDatabaseHas('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::Refund->value,
            'reversal_of' => $transaction->id,
        ]);

        Notification::assertSentTo(
            $user,
            InternetBillPaymentStatusNotification::class,
            fn(InternetBillPaymentStatusNotification $notification) => $notification->event ===
                BillPaymentNotificationEvent::Refunded,
        );
    }

    public function test_reconciliation_exception_moves_payment_to_review_before_max_age_without_retrying(): void
    {
        Queue::fake();

        [$user, $wallet] = $this->makePayer(balance: 500);
        $transaction = LedgerTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill,
            'status' => LedgerTransactionStatus::Processing,
            'amount' => 1000,
            'idempotency_key' => 'ftth-reconcile-exception-retry',
            'transaction_no' => 'FTTH-RECON-EXCEPTION-RETRY',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);
        BillPayment::query()->create([
            'ledger_transaction_id' => $transaction->id,
            'broadband_account_number' => $user->broadband_account_number,
            'order_no' => 'FTTH-RECON-EXCEPTION-RETRY',
            'service_type' => 'FTTH',
            'product_id' => 'ftth-product-1',
            'product_price' => 1000,
            'status' => BillPaymentStatus::Processing,
        ]);

        $this->accountInfo->shouldReceive('find')->once()->andThrow(new \Error('Temporary reconciliation failure.'));

        (new ReconcileStuckInternetBillPaymentsJob($transaction->id))->handle(app(InternetBillPaymentService::class));

        $this->assertSame(LedgerTransactionStatus::Review, $transaction->fresh()->status);
        $this->assertSame(
            'reconciliation_failed',
            $transaction->fresh()->billPayment->external_response['manual_review_reason'],
        );
        Queue::assertNotPushed(ReconcileStuckInternetBillPaymentsJob::class);
    }

    public function test_reconciliation_exception_moves_overage_payment_to_review(): void
    {
        Notification::fake();
        Queue::fake();

        [$user, $wallet] = $this->makePayer(balance: 500);
        $transaction = LedgerTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill,
            'status' => LedgerTransactionStatus::Processing,
            'amount' => 1000,
            'idempotency_key' => 'ftth-reconcile-exception-review',
            'transaction_no' => 'FTTH-RECON-EXCEPTION-REVIEW',
            'created_at' => now()->subSeconds((int) config('services.cg_api.processing_max_age_seconds', 900) + 60),
            'updated_at' => now()->subSeconds((int) config('services.cg_api.processing_max_age_seconds', 900) + 60),
        ]);
        BillPayment::query()->create([
            'ledger_transaction_id' => $transaction->id,
            'broadband_account_number' => $user->broadband_account_number,
            'order_no' => 'FTTH-RECON-EXCEPTION-REVIEW',
            'service_type' => 'FTTH',
            'product_id' => 'ftth-product-1',
            'product_price' => 1000,
            'status' => BillPaymentStatus::Processing,
        ]);

        $this->accountInfo->shouldReceive('find')->once()->andThrow(new \Error('Repeated reconciliation failure.'));

        (new ReconcileStuckInternetBillPaymentsJob($transaction->id))->handle(app(InternetBillPaymentService::class));

        $this->assertSame(LedgerTransactionStatus::Review, $transaction->fresh()->status);
        $this->assertDatabaseHas('bill_payments', [
            'ledger_transaction_id' => $transaction->id,
            'status' => BillPaymentStatus::Review->value,
        ]);
        $this->assertSame(
            'reconciliation_failed',
            $transaction->fresh()->billPayment->external_response['manual_review_reason'],
        );
        Queue::assertNotPushed(ReconcileStuckInternetBillPaymentsJob::class);
    }

    public function test_admin_sees_a_flash_error_when_refunding_a_payment_that_is_no_longer_under_review(): void
    {
        RolePermissionSeeder::sync();
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['billing.view', 'billing.update']);
        [$user, $wallet] = $this->makePayer(balance: 500);
        $transaction = LedgerTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill,
            'status' => LedgerTransactionStatus::Completed,
            'amount' => 1000,
            'idempotency_key' => 'ftth-review-already-completed',
            'transaction_no' => 'FTTH-REVIEW-ALREADY-COMPLETED',
        ]);
        BillPayment::query()->create([
            'ledger_transaction_id' => $transaction->id,
            'broadband_account_number' => $user->broadband_account_number,
            'order_no' => 'FTTH-REVIEW-ALREADY-COMPLETED',
            'service_type' => 'FTTH',
            'product_id' => 'ftth-product-1',
            'product_price' => 1000,
            'status' => BillPaymentStatus::Completed,
        ]);

        $this->actingAs($admin, 'web')
            ->post('/billing/transactions/'.$transaction->id.'/review/refund')
            ->assertRedirect()
            ->assertSessionHas('error', 'transactions.review_unavailable');

        $this->assertDatabaseMissing('ledger_transactions', [
            'reversal_of' => $transaction->id,
            'type' => LedgerTransactionType::Refund->value,
        ]);
    }

    public function test_user_can_pay_another_broadband_account(): void
    {
        Notification::fake();

        Http::fake();

        [$user, $wallet] = $this->makePayer(balance: 1500);
        $user->forceFill(['broadband_account_number' => null])->save();

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => 'CG00000000',
                'service_type' => 'FTTH',
                'idempotency_key' => 'ftth-pay-other-acct',
                'password' => '123456',
            ])
            ->assertOk()
            ->assertJsonPath('code', 'MSG-FTTH-008')
            ->assertJsonPath('data.amount', '1000.00');

        $wallet->refresh();
        $user->refresh();
        $this->assertEquals(500, $wallet->balance);
        $this->assertNull($user->broadband_account_number);
        $this->assertDatabaseHas('bill_payments', [
            'broadband_account_number' => 'CG00000000',
            'status' => BillPaymentStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill->value,
            'note' => 'Internet bill CG00000000',
        ]);
    }

    public function test_same_idempotency_key_cannot_pay_a_different_account(): void
    {
        Notification::fake();

        Http::fake();

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => 'ftth-pay-one-account',
                'password' => '123456',
            ])
            ->assertOk();

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => 'CG00000000',
                'service_type' => 'FTTH',
                'idempotency_key' => 'ftth-pay-one-account',
                'password' => '123456',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'MSG-FTTH-013')
            ->assertJsonPath('message', 'This payment request cannot be replayed.');

        $wallet->refresh();
        $this->assertEquals(500, $wallet->balance);
        $this->assertSame(1, LedgerTransaction::query()->where('type', LedgerTransactionType::FtthBill)->count());
    }

    public function test_same_idempotency_key_cannot_be_reused_by_another_wallet(): void
    {
        Notification::fake();

        Http::fake();

        [$firstUser] = $this->makePayer(balance: 1500);
        [$secondUser, $secondWallet] = $this->makePayer(balance: 1500);
        $idempotencyKey = 'ftth-pay-global-key';

        $this->withToken($firstUser->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $firstUser->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => $idempotencyKey,
                'password' => '123456',
            ])
            ->assertOk();

        $this->withToken($secondUser->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $secondUser->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => $idempotencyKey,
                'password' => '123456',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'MSG-FTTH-013')
            ->assertJsonPath('message', 'This payment request cannot be replayed.');

        $secondWallet->refresh();
        $this->assertEquals(1500, $secondWallet->balance);
        $this->assertSame(1, LedgerTransaction::query()->where('type', LedgerTransactionType::FtthBill)->count());
    }

    public function test_unknown_bill_does_not_debit_the_wallet(): void
    {
        Http::fake();
        $this->accountNotFound = true;

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => 'CG00000000',
                'service_type' => 'FTTH',
                'idempotency_key' => 'ftth-pay-missing-bill',
                'password' => '123456',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'MSG-FTTH-004')
            ->assertJsonPath('message', 'Broadband account number not found.');

        $wallet->refresh();
        $this->assertEquals(1500, $wallet->balance);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_ftth_idempotency_key_leaves_room_for_a_refund_prefix(): void
    {
        [$user] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => str_repeat('a', 94),
                'password' => '123456',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_idempotency_key_is_required(): void
    {
        [$user] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'FTTH',
                'password' => '123456',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_service_type_is_required(): void
    {
        [$user] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'password' => '123456',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_type']);
    }

    /**
     * @return array{0: User, 1: Wallet}
     */
    private function makePayer(int $balance): array
    {
        $accountNumber = 'CG' . fake()->unique()->numerify('########');

        $user = User::factory()->create([
            'phone' => '959' . fake()->unique()->numerify('########'),
            'password' => '123456',
            'broadband_account_number' => $accountNumber,
        ]);

        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => $balance,
            'status' => WalletStatus::Active,
        ]);

        $user->deviceTokens()->create(['token' => 'fcm-' . fake()->unique()->uuid()]);

        return [$user, $wallet];
    }

    /** @return array<string, Response> */
    private function broadbandAccountFake(): array
    {
        return [
            '*broadband_accounts*' => function ($request) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);
                $accountNumber = $query['account_number'] ?? ($request['account_number'] ?? 'CG00000000');

                return Http::response(
                    [
                        [
                            'account_number' => $accountNumber,
                            'customer_name' => 'Broadband Account Holder',
                        ],
                    ],
                    200,
                );
            },
        ];
    }
}
