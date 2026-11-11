<?php

namespace Tests\Feature;

use App\Enums\BillPaymentStatus;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletStatus;
use App\Jobs\ReconcileStuckInternetBillPaymentsJob;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BroarbandAccount\AccountInfoService;
use App\Services\CgNetApiClient;
use App\Services\InternetBill\InternetBillPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BillPaymentScenariosTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $accountInfo;

    private MockInterface $cgNet;

    private bool $timeoutRecharge = false;

    private int $rechargeCode = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->accountInfo = Mockery::mock(AccountInfoService::class);
        $this->accountInfo
            ->shouldReceive('find')
            ->byDefault()
            ->andReturnUsing(fn(string $serviceType, string $account): array => [
                'account' => $account,
                'productID' => 'ftth-product-1',
                'productName' => 'FTTH 100M',
                'productPrice' => '1000.00',
                'productLine' => 'FTTH',
                'productSpeed' => '100M',
                'monthCycle' => 1,
                'monthCycleUnit' => '月',
                'installationFee' => '0.00',
            ]);
        $this->app->instance(AccountInfoService::class, $this->accountInfo);

        $this->cgNet = Mockery::mock(CgNetApiClient::class);
        $this->cgNet
            ->shouldReceive('post')
            ->byDefault()
            ->withArgs(fn(string $command): bool => $command === 'RechargeAPI')
            ->andReturnUsing(function (): Response {
                if ($this->timeoutRecharge) {
                    throw new ConnectionException('RechargeAPI timed out.');
                }

                return new Response(Http::response([
                    'ErrCode' => $this->rechargeCode,
                    'Message' => $this->rechargeCode === 0 ? 'success' : 'rejected',
                ])->wait());
            });
        $this->app->instance(CgNetApiClient::class, $this->cgNet);
    }

    public function test_successful_bill_payment_debits_once_and_completes(): void
    {
        [$user, $wallet] = $this->makePayer(1500);

        $this->pay($user, 'bill-success')
            ->assertOk()
            ->assertJsonPath('data.billing_status', 'completed')
            ->assertJsonPath('data.amount', '1000.00');

        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseHas('bill_payments', [
            'status' => BillPaymentStatus::Completed->value,
        ]);
    }

    public function test_rejected_bill_payment_refunds_the_wallet(): void
    {
        $this->rechargeCode = 100;
        [$user, $wallet] = $this->makePayer(1500);

        $this->pay($user, 'bill-rejected')
            ->assertStatus(502)
            ->assertJsonPath('data.billing_status', 'failed');

        $this->assertSame('1500.00', $wallet->fresh()->balance);
        $this->assertDatabaseHas('ledger_transactions', [
            'type' => LedgerTransactionType::Refund->value,
            'amount' => '1000.00',
        ]);
    }

    public function test_recharge_timeout_keeps_payment_processing_and_dispatches_reconciliation(): void
    {
        Queue::fake();
        $this->timeoutRecharge = true;
        [$user, $wallet] = $this->makePayer(1500);

        $this->pay($user, 'bill-timeout')
            ->assertStatus(202)
            ->assertJsonPath('data.billing_status', 'processing');

        $transaction = LedgerTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', LedgerTransactionType::FtthBill)
            ->firstOrFail();

        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertSame(LedgerTransactionStatus::Processing, $transaction->status);
        Queue::assertPushed(
            ReconcileStuckInternetBillPaymentsJob::class,
            fn(ReconcileStuckInternetBillPaymentsJob $job): bool => $job->ledgerTransactionId === $transaction->id,
        );
    }

    public function test_reconciliation_retries_timeout_and_completes_the_original_payment(): void
    {
        Queue::fake();
        $this->timeoutRecharge = true;
        [$user, $wallet] = $this->makePayer(1500);

        $this->pay($user, 'bill-reconcile')
            ->assertStatus(202)
            ->assertJsonPath('data.billing_status', 'processing');

        $transaction = LedgerTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', LedgerTransactionType::FtthBill)
            ->firstOrFail();
        $transaction->forceFill([
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ])->save();
        $orderNo = $transaction->billPayment->order_no;

        $this->timeoutRecharge = false;

        (new ReconcileStuckInternetBillPaymentsJob($transaction->id))
            ->handle(app(InternetBillPaymentService::class));

        $transaction->refresh();
        $this->assertSame(LedgerTransactionStatus::Completed, $transaction->status);
        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertDatabaseHas('bill_payments', [
            'order_no' => $orderNo,
            'status' => BillPaymentStatus::Completed->value,
        ]);
        $this->assertDatabaseCount('ledger_transactions', 1);
    }

    private function pay(User $user, string $idempotencyKey)
    {
        return $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/internet-bills/pay', [
                'broadband_account_number' => $user->broadband_account_number,
                'service_type' => 'FTTH',
                'idempotency_key' => $idempotencyKey,
                'password' => '123456',
            ]);
    }

    /** @return array{0: User, 1: Wallet} */
    private function makePayer(int $balance): array
    {
        $user = User::factory()->create([
            'phone' => '959'.fake()->unique()->numerify('########'),
            'password' => '123456',
            'broadband_account_number' => 'CG'.fake()->unique()->numerify('########'),
        ]);

        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => $balance,
            'status' => WalletStatus::Active,
        ]);

        return [$user, $wallet];
    }
}
