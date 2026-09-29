<?php

namespace Tests\Feature;

use App\Enums\BillPaymentNotificationEvent;
use App\Enums\BillPaymentStatus;
use App\Enums\WalletEntryType;
use App\Enums\WalletStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Jobs\ReconcileStuckFtthBillPaymentsJob;
use App\Models\BillPayment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
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
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::FtthBill->value,
            'status' => WalletTransactionStatus::Completed->value,
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
        $this->assertDatabaseHas('wallet_entries', [
            'wallet_id' => $wallet->id,
            'type' => WalletEntryType::Debit->value,
            'amount' => 1000,
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
            fn (FtthBillPaymentStatusNotification $notification) => $notification->event === BillPaymentNotificationEvent::Completed &&
                $notification->amount === 1000,
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
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::Refund->value,
            'amount' => 1000,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::FtthBill->value,
            'status' => WalletTransactionStatus::Failed->value,
        ]);

        $refund = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', WalletTransactionType::Refund)
            ->firstOrFail();

        Notification::assertSentToTimes($user, FtthBillPaymentStatusNotification::class, 1);
        Notification::assertSentTo(
            $user,
            FtthBillPaymentStatusNotification::class,
            fn (FtthBillPaymentStatusNotification $notification) => $notification->event === BillPaymentNotificationEvent::Refunded &&
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
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::FtthBill->value,
            'status' => WalletTransactionStatus::Processing->value,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::Refund->value,
        ]);

        $transaction = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', WalletTransactionType::FtthBill)
            ->firstOrFail();

        Queue::assertPushed(
            ReconcileStuckFtthBillPaymentsJob::class,
            fn (ReconcileStuckFtthBillPaymentsJob $job) => $job->walletTransactionId === $transaction->id,
        );

        Notification::assertSentTo(
            $user,
            FtthBillPaymentStatusNotification::class,
            fn (FtthBillPaymentStatusNotification $notification) => $notification->event === BillPaymentNotificationEvent::Processing &&
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
            WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('type', WalletTransactionType::FtthBill)
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

        $transaction = WalletTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::FtthBill,
            'status' => WalletTransactionStatus::Processing,
            'amount' => 1000,
            'idempotency_key' => 'ftth-stuck-1',
            'transaction_no' => 'FTTH-STUCK-1',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        BillPayment::query()->create([
            'wallet_transaction_id' => $transaction->id,
            'broadband_account_number' => $user->broadband_account_number,
            'status' => BillPaymentStatus::Processing,
            'external_response' => [
                'broadband_account_number' => $user->broadband_account_number,
            ],
        ]);

        (new ReconcileStuckFtthBillPaymentsJob($transaction->id))->handle(app(FtthBillPaymentService::class));

        $transaction->refresh();
        $this->assertSame(WalletTransactionStatus::Completed, $transaction->status);
        $this->assertDatabaseHas('bill_payments', [
            'wallet_transaction_id' => $transaction->id,
            'status' => BillPaymentStatus::Completed->value,
            'external_bill_ref' => 'BILL-RECON-1',
            'external_payment_ref' => 'PAY-RECON-1',
        ]);

        $billPayment = BillPayment::query()->where('wallet_transaction_id', $transaction->id)->firstOrFail();
        $this->assertIsArray($billPayment->external_response);
        $this->assertSame('success', $billPayment->external_response['outcome'] ?? null);
        $this->assertNotNull($billPayment->confirmed_at);

        Notification::assertSentTo(
            $user,
            FtthBillPaymentStatusNotification::class,
            fn (FtthBillPaymentStatusNotification $notification) => $notification->event === BillPaymentNotificationEvent::Completed,
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

        $transaction = WalletTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::FtthBill,
            'status' => WalletTransactionStatus::Processing,
            'amount' => 1000,
            'idempotency_key' => 'ftth-stuck-refund-1',
            'transaction_no' => 'FTTH-STUCK-REFUND-1',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        BillPayment::query()->create([
            'wallet_transaction_id' => $transaction->id,
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
        $this->assertSame(WalletTransactionStatus::Failed, $transaction->status);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::Refund->value,
            'reversal_of' => $transaction->id,
        ]);

        Notification::assertSentTo(
            $user,
            FtthBillPaymentStatusNotification::class,
            fn (FtthBillPaymentStatusNotification $notification) => $notification->event === BillPaymentNotificationEvent::Refunded,
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
}
