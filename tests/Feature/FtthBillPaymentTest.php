<?php

namespace Tests\Feature;

use App\Enums\BillPaymentNotificationEvent;
use App\Enums\BillPaymentStatus;
use App\Enums\WalletStatus;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Jobs\ReconcileStuckFtthBillPaymentsJob;
use App\Models\BillPayment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\LedgerTransaction;
use App\Notifications\FtthBillPaymentStatusNotification;
use App\Services\FtthBill\FtthBillPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FtthBillPaymentTest extends TestCase
{
    use RefreshDatabase;

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
            ->getJson('/api/ftth-bills/pending-slip')
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
            ->getJson('/api/ftth-bills/pending-slip')
            ->assertOk()
            ->assertJsonPath('data.bill_month', '2026-12')
            ->assertJsonPath('data.slip.slip_url', 'https://billing-server.test/slips/december');

        Http::assertSent(fn($request) => !str_contains($request->url(), 'bill_month='));
    }

    public function test_pending_slip_label_formats_january_with_the_new_year(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 2)->startOfDay());

        Http::fake([
            'billing-server.test/bill-details*' => Http::response([
                'amount' => 1000,
                'bill_month' => '2027-01',
                'bill_month_label' => 'Jan 2027',
                'slip_url' => 'https://billing-server.test/slips/january',
            ], 200),
            ...$this->broadbandAccountFake(),
        ]);

        [$user] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/ftth-bills/pending-slip')
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
            ->getJson('/api/ftth-bills/pending-slip')
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
            'billing-server.test/bill-details*' => Http::response([
                'amount' => 250,
                'bill_month' => '2026-10',
                'bill_month_label' => 'Oct 2026',
                'slip_url' => 'https://billing-server.test/slips/october',
            ], 200),
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
            ->getJson('/api/ftth-bills/pending-slip')
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
            'billing-server.test/bill-details*' => Http::response([
                'amount' => 250,
                'bill_month' => '2026-10',
                'bill_month_label' => 'Oct 2026',
                'slip_url' => 'https://billing-server.test/slips/october',
            ], 200),
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
            ->getJson('/api/ftth-bills/pending-slip')
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
            'billing-server.test/bill-details*' => Http::response([
                'amount' => 250,
                'bill_month' => '2026-10',
                'paid_slips' => $billingPaidSlips,
            ], 200),
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
                    'account_number' => $user->broadband_account_number,
                    'amount' => 250,
                ],
            ],
            [
                'bill_month' => '2026-08',
                'bill_month_label' => 'August 2026',
                'customer_name' => 'Broadband Account Holder',
                'payment_method' => 'CTO',
                'slip' => [
                    'account_number' => $user->broadband_account_number,
                    'amount' => 250,
                ],
            ],
        ];

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/ftth-bills/paid-slips')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.paid_slips', $expectedPaidSlips);

        Http::assertSent(
            fn($request) => str_contains($request->url(), 'account_number=' . $user->broadband_account_number) &&
                !str_contains($request->url(), 'bill_month='),
        );
    }

    public function test_user_can_pay_ftth_bill_when_wallet_has_enough_balance(): void
    {
        Notification::fake();

        Http::fake([
            'billing-server.test/bill-details*' => Http::response(['amount' => 1000], 200),
            'billing-server.test/extend-plan' => Http::response(
                [
                    'success' => true,
                    'billing_ref' => 'BILL-1001',
                    'payment_ref' => 'PAY-1001',
                ],
                200,
            ),
        ]);

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/ftth-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'idempotency_key' => 'ftth-pay-success-1',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 1000)
            ->assertJsonPath('data.billing_status', 'completed');

        $wallet->refresh();
        $this->assertSame(500, $wallet->balance);
        $this->assertDatabaseHas('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::FtthBill->value,
            'status' => LedgerTransactionStatus::Completed->value,
            'amount' => 1000,
        ]);
        $this->assertDatabaseHas('bill_payments', [
            'status' => BillPaymentStatus::Completed->value,
            'broadband_account_number' => $user->broadband_account_number,
            'external_bill_ref' => 'BILL-1001',
            'external_payment_ref' => 'PAY-1001',
        ]);

        $billPayment = BillPayment::query()
            ->where('broadband_account_number', $user->broadband_account_number)
            ->where('status', BillPaymentStatus::Completed)
            ->latest('id')
            ->firstOrFail();

        $this->assertIsArray($billPayment->external_response);
        $this->assertSame($user->broadband_account_number, $billPayment->external_response['broadband_account_number']);
        $this->assertSame('success', $billPayment->external_response['outcome'] ?? null);
        $this->assertSame('BILL-1001', $billPayment->external_response['billing_ref'] ?? null);
        $this->assertNotNull($billPayment->confirmed_at);
        $this->assertDatabaseHas('ledger_entries', [
            'wallet_id' => $wallet->id,
            'debit' => 1000,
            'credit' => 0,
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/extend-plan') &&
                isset($request['payment_ref']) &&
                isset($request['idempotency_key']);
        });

        Notification::assertSentToTimes($user, FtthBillPaymentStatusNotification::class, 1);
        Notification::assertSentTo(
            $user,
            FtthBillPaymentStatusNotification::class,
            fn(FtthBillPaymentStatusNotification $notification) => $notification->event ===
                BillPaymentNotificationEvent::Completed && $notification->amount === 1000,
        );
    }

    public function test_wallet_is_refunded_when_external_billing_rejects_payment(): void
    {
        Notification::fake();

        Http::fake([
            'billing-server.test/bill-details*' => Http::response(['amount' => 1000], 200),
            'billing-server.test/extend-plan' => Http::response(
                [
                    'success' => false,
                    'message' => 'Billing API rejected the request.',
                ],
                422,
            ),
        ]);

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/ftth-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'idempotency_key' => 'ftth-pay-fail-1',
            ])
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'External billing payment failed. Wallet was refunded.');

        $wallet->refresh();
        $this->assertSame(1500, $wallet->balance);
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

        Notification::assertSentToTimes($user, FtthBillPaymentStatusNotification::class, 1);
        Notification::assertSentTo(
            $user,
            FtthBillPaymentStatusNotification::class,
            fn(FtthBillPaymentStatusNotification $notification) => $notification->event ===
                BillPaymentNotificationEvent::Refunded &&
                $notification->refundTransactionNo === $refund->transaction_no,
        );
    }

    public function test_unknown_billing_outcome_leaves_payment_processing(): void
    {
        Queue::fake();
        Notification::fake();

        Http::fake([
            'billing-server.test/bill-details*' => Http::response(['amount' => 1000], 200),
            'billing-server.test/extend-plan' => Http::response(
                [
                    'message' => 'Gateway timeout',
                ],
                504,
            ),
        ]);

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/ftth-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'idempotency_key' => 'ftth-pay-unknown-1',
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.billing_status', 'processing');

        $wallet->refresh();
        $this->assertSame(500, $wallet->balance);
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
            ReconcileStuckFtthBillPaymentsJob::class,
            fn(ReconcileStuckFtthBillPaymentsJob $job) => $job->ledgerTransactionId === $transaction->id,
        );

        Notification::assertSentTo(
            $user,
            FtthBillPaymentStatusNotification::class,
            fn(FtthBillPaymentStatusNotification $notification) => $notification->event ===
                BillPaymentNotificationEvent::Processing &&
                $notification->transactionNo === $transaction->transaction_no,
        );
    }

    public function test_wallet_balance_must_be_sufficient_before_payments_are_attempted(): void
    {
        Http::fake([
            'billing-server.test/bill-details*' => Http::response(['amount' => 1000], 200),
        ]);

        [$user, $wallet] = $this->makePayer(balance: 500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/ftth-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'idempotency_key' => 'ftth-pay-insufficient-1',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Insufficient wallet balance.');

        $wallet->refresh();
        $this->assertSame(500, $wallet->balance);
        Http::assertSentCount(1);
        Http::assertNotSent(fn($request) => str_contains($request->url(), '/extend-plan'));
    }

    public function test_idempotent_replay_returns_completed_payment(): void
    {
        Notification::fake();

        Http::fake([
            'billing-server.test/bill-details*' => Http::response(['amount' => 1000], 200),
            'billing-server.test/extend-plan' => Http::response(
                [
                    'success' => true,
                    'billing_ref' => 'BILL-1001',
                ],
                200,
            ),
        ]);

        [$user, $wallet] = $this->makePayer(balance: 1500);

        $payload = [
            'broadband_account_number' => $user->broadband_account_number,
            'idempotency_key' => 'ftth-pay-idempotent-1',
        ];

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/ftth-bills/pay', $payload)
            ->assertOk();

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/ftth-bills/pay', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'FTTH bill payment already processed.');

        $wallet->refresh();
        $this->assertSame(500, $wallet->balance);
        $this->assertSame(
            1,
            LedgerTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('type', LedgerTransactionType::FtthBill)
                ->count(),
        );

        Notification::assertSentToTimes($user, FtthBillPaymentStatusNotification::class, 1);
    }

    public function test_idempotent_replay_after_failure_returns_refunded_result(): void
    {
        Notification::fake();

        Http::fake([
            'billing-server.test/bill-details*' => Http::response(['amount' => 1000], 200),
            'billing-server.test/extend-plan' => Http::response(
                [
                    'success' => false,
                    'message' => 'Rejected',
                ],
                422,
            ),
        ]);

        [$user] = $this->makePayer(balance: 1500);

        $payload = [
            'broadband_account_number' => $user->broadband_account_number,
            'idempotency_key' => 'ftth-pay-fail-replay-1',
        ];

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/ftth-bills/pay', $payload)
            ->assertStatus(502);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/ftth-bills/pay', $payload)
            ->assertStatus(502)
            ->assertJsonPath('data.billing_status', 'failed');

        Notification::assertSentToTimes($user, FtthBillPaymentStatusNotification::class, 1);
    }

    public function test_reconcile_job_completes_stuck_processing_payment(): void
    {
        Notification::fake();

        Http::fake([
            'billing-server.test/payment-status*' => Http::response(
                [
                    'status' => 'completed',
                    'billing_ref' => 'BILL-RECON-1',
                    'payment_ref' => 'PAY-RECON-1',
                ],
                200,
            ),
        ]);

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
            'status' => BillPaymentStatus::Processing,
            'external_response' => [
                'broadband_account_number' => $user->broadband_account_number,
            ],
        ]);

        (new ReconcileStuckFtthBillPaymentsJob($transaction->id))->handle(app(FtthBillPaymentService::class));

        $transaction->refresh();
        $this->assertSame(LedgerTransactionStatus::Completed, $transaction->status);
        $this->assertDatabaseHas('bill_payments', [
            'ledger_transaction_id' => $transaction->id,
            'status' => BillPaymentStatus::Completed->value,
            'external_bill_ref' => 'BILL-RECON-1',
            'external_payment_ref' => 'PAY-RECON-1',
        ]);

        $billPayment = BillPayment::query()->where('ledger_transaction_id', $transaction->id)->firstOrFail();
        $this->assertIsArray($billPayment->external_response);
        $this->assertSame('success', $billPayment->external_response['outcome'] ?? null);
        $this->assertNotNull($billPayment->confirmed_at);

        Notification::assertSentTo(
            $user,
            FtthBillPaymentStatusNotification::class,
            fn(FtthBillPaymentStatusNotification $notification) => $notification->event ===
                BillPaymentNotificationEvent::Completed,
        );

        (new ReconcileStuckFtthBillPaymentsJob($transaction->id))->handle(app(FtthBillPaymentService::class));

        Notification::assertSentToTimes($user, FtthBillPaymentStatusNotification::class, 1);
    }

    public function test_reconcile_job_refunds_when_billing_reports_not_found(): void
    {
        Notification::fake();

        Http::fake([
            'billing-server.test/payment-status*' => Http::response([], 404),
        ]);

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
            'status' => BillPaymentStatus::Processing,
            'external_response' => [
                'broadband_account_number' => $user->broadband_account_number,
            ],
        ]);

        (new ReconcileStuckFtthBillPaymentsJob($transaction->id))->handle(app(FtthBillPaymentService::class));

        $wallet->refresh();
        $transaction->refresh();
        $this->assertSame(1500, $wallet->balance);
        $this->assertSame(LedgerTransactionStatus::Failed, $transaction->status);
        $this->assertDatabaseHas('ledger_transactions', [
            'wallet_id' => $wallet->id,
            'type' => LedgerTransactionType::Refund->value,
            'reversal_of' => $transaction->id,
        ]);

        Notification::assertSentTo(
            $user,
            FtthBillPaymentStatusNotification::class,
            fn(FtthBillPaymentStatusNotification $notification) => $notification->event ===
                BillPaymentNotificationEvent::Refunded,
        );
    }

    public function test_idempotency_key_is_required(): void
    {
        [$user] = $this->makePayer(balance: 1500);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/ftth-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    /**
     * @return array{0: User, 1: Wallet}
     */
    private function makePayer(int $balance): array
    {
        $accountNumber = 'CG' . fake()->unique()->numerify('########');

        $user = User::factory()->create([
            'phone' => '959' . fake()->unique()->numerify('########'),
            'password' => 'password123',
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

    /** @return array<string, \Illuminate\Http\Client\Response> */
    private function broadbandAccountFake(): array
    {
        return [
            '*broadband_accounts*' => Http::response([
                ['customer_name' => 'Broadband Account Holder'],
            ], 200),
        ];
    }
}
