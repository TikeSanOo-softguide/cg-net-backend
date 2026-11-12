<?php

namespace Tests\Feature;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\TopUpCardStatus;
use App\Enums\WalletActorType;
use App\Events\AdminNotificationCreated;
use App\Jobs\GenerateFinancialSummarySnapshot;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\FinancialSummarySnapshot;
use App\Models\TopUpCard;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use App\Services\Reports\FinancialSummaryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_summary_notifies_admin_and_links_to_its_report(): void
    {
        $admin = $this->createReportAdmin();
        Event::fake([AdminNotificationCreated::class]);
        $snapshot = FinancialSummarySnapshot::query()->create([
            'requested_by' => $admin->id,
            'status' => 'queued',
        ]);
        $summary = \Mockery::mock(FinancialSummaryService::class);
        $summary->shouldReceive('generate')->once()->andReturn([]);

        (new GenerateFinancialSummarySnapshot($snapshot->id))->handle($summary);

        $snapshot->refresh();
        $this->assertSame('completed', $snapshot->status);

        $notification = AdminNotification::query()->sole();
        $this->assertSame('Financial Summary Completed', $notification->title);
        $this->assertSame('The financial summary report completed successfully.', $notification->body);
        $this->assertSame('normal', $notification->severity);
        $this->assertSame('financial_summary_snapshot', $notification->reference_type);
        $this->assertSame($snapshot->id, $notification->reference_id);
        $this->assertSame(
            '/reports/financial-summary?snapshot=' . $snapshot->id,
            $notification->toDropdownArray()['href'],
        );
        Event::assertDispatched(AdminNotificationCreated::class);

        $this->actingAs($admin, 'web')
            ->get('/reports/financial-summary?snapshot=' . $snapshot->id)
            ->assertInertia(fn(Assert $page) => $page->where('snapshots.0.notification_unread', true));

        $this->actingAs($admin, 'web')
            ->putJson('/dashboard/notifications/reference/financial_summary_snapshot/' . $snapshot->id . '/read')
            ->assertOk()
            ->assertJson(['marked_read' => 1]);
    }

    public function test_failed_summary_notifies_admin_without_duplicate_on_failed_callback(): void
    {
        $this->createReportAdmin();
        Event::fake([AdminNotificationCreated::class]);
        $snapshot = FinancialSummarySnapshot::query()->create([
            'status' => 'queued',
        ]);
        $summary = \Mockery::mock(FinancialSummaryService::class);
        $summary->shouldReceive('generate')->once()->andThrow(new \RuntimeException('Summary failed.'));
        $job = new GenerateFinancialSummarySnapshot($snapshot->id);

        try {
            $job->handle($summary);
            $this->fail('Expected summary failure to be rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Summary failed.', $exception->getMessage());
        }

        $snapshot->refresh();
        $this->assertSame('failed', $snapshot->status);

        $notification = AdminNotification::query()->sole();
        $this->assertSame('Financial Summary Failed', $notification->title);
        $this->assertSame('alert', $notification->severity);
        $this->assertSame(
            'The financial summary report failed. Please review the report logs for details.',
            $notification->body,
        );
        $this->assertNull($notification->reference_type);
        $this->assertNull($notification->reference_id);
        $job->failed(new \RuntimeException('Summary failed.'));

        $this->assertSame(1, AdminNotification::query()->count());
        Event::assertDispatched(AdminNotificationCreated::class);
    }

    public function test_admin_can_view_and_queue_a_financial_summary(): void
    {
        Queue::fake();
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->get('/reports/financial-summary')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Reports/FinancialSummary/Index')
                    ->where('snapshot', null)
                    ->where('run', null),
            );

        $response = $this->actingAs($admin, 'web')->post('/reports/financial-summary/check');
        $snapshot = FinancialSummarySnapshot::query()->sole();

        $response->assertRedirect(route('reports.financial-summary.index', ['snapshot' => $snapshot->id]));
        $this->assertSame('queued', $snapshot->status);

        Queue::assertPushed(
            GenerateFinancialSummarySnapshot::class,
            fn(GenerateFinancialSummarySnapshot $job): bool => $job->snapshotId === $snapshot->id,
        );

        $this->actingAs($admin, 'web')
            ->post('/reports/financial-summary/check')
            ->assertRedirect(route('reports.financial-summary.index', ['snapshot' => $snapshot->id]));

        $this->assertSame(1, FinancialSummarySnapshot::query()->count());
        Queue::assertPushed(GenerateFinancialSummarySnapshot::class, 1);
    }

    public function test_job_persists_all_time_financial_totals(): void
    {
        $customer = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $customer->id, 'balance' => 0]);
        $otherCustomer = User::factory()->create();
        Wallet::factory()->create(['user_id' => $otherCustomer->id, 'balance' => 45]);
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        $poster->creditWallet(
            wallet: $wallet,
            amount: 100,
            contraAccount: LedgerAccountCode::CashTopup,
            type: LedgerTransactionType::Topup,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'financial-summary-topup',
            actorType: WalletActorType::User,
            actorId: $customer->id,
        );

        $poster->debitWallet(
            wallet: $wallet->fresh(),
            amount: 40,
            contraAccount: LedgerAccountCode::InternetServiceRevenue,
            type: LedgerTransactionType::FtthBill,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'financial-summary-bill',
        );

        $poster->debitWallet(
            wallet: $wallet->fresh(),
            amount: 12,
            contraAccount: LedgerAccountCode::InternetServiceRevenue,
            type: LedgerTransactionType::CgBill,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'financial-summary-cg-bill',
        );

        $refundedBill = $poster->debitWallet(
            wallet: $wallet->fresh(),
            amount: 15,
            contraAccount: LedgerAccountCode::InternetServiceRevenue,
            type: LedgerTransactionType::CgBill,
            status: LedgerTransactionStatus::Processing,
            idempotencyKey: 'financial-summary-refunded-bill',
        );
        $poster->creditWallet(
            wallet: $wallet->fresh(),
            amount: 15,
            contraAccount: LedgerAccountCode::InternetServiceRevenue,
            type: LedgerTransactionType::Refund,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'financial-summary-bill-refund',
            reversalOf: $refundedBill->id,
        );
        $refundedBill->update(['status' => LedgerTransactionStatus::Failed]);

        $poster->debitWallet(
            wallet: $wallet->fresh(),
            amount: 25,
            contraAccount: LedgerAccountCode::PackageRevenue,
            type: LedgerTransactionType::WifiPackage,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'financial-summary-package',
        );

        $refundedPackage = $poster->debitWallet(
            wallet: $wallet->fresh(),
            amount: 8,
            contraAccount: LedgerAccountCode::PackageRevenue,
            type: LedgerTransactionType::WifiPackage,
            status: LedgerTransactionStatus::Processing,
            idempotencyKey: 'financial-summary-refunded-package',
        );
        $poster->creditWallet(
            wallet: $wallet->fresh(),
            amount: 8,
            contraAccount: LedgerAccountCode::PackageRevenue,
            type: LedgerTransactionType::Refund,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'financial-summary-package-refund',
            reversalOf: $refundedPackage->id,
        );
        $refundedPackage->update(['status' => LedgerTransactionStatus::Failed]);

        $poster->creditWallet(
            wallet: $wallet->fresh(),
            amount: 6,
            contraAccount: LedgerAccountCode::AdjustmentExpense,
            type: LedgerTransactionType::Adjustment,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'financial-summary-adjustment-credit',
        );
        $poster->debitWallet(
            wallet: $wallet->fresh(),
            amount: 2,
            contraAccount: LedgerAccountCode::AdjustmentExpense,
            type: LedgerTransactionType::Adjustment,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'financial-summary-adjustment-debit',
        );

        TopUpCard::factory()->create([
            'amount' => 12,
            'status' => TopUpCardStatus::Active,
            'activated_at' => today()->toDateString(),
        ]);
        TopUpCard::factory()->create([
            'amount' => 7,
            'status' => TopUpCardStatus::Blocked,
            'activated_at' => today()->toDateString(),
        ]);
        TopUpCard::factory()->create([
            'amount' => 9,
            'status' => TopUpCardStatus::Expired,
            'activated_at' => today()->toDateString(),
        ]);
        TopUpCard::factory()
            ->redeemed()
            ->create([
                'amount' => 5,
                'status' => TopUpCardStatus::Used,
            ]);
        TopUpCard::factory()->create([
            'amount' => 30,
            'status' => TopUpCardStatus::Pending,
            'activated_at' => null,
        ]);

        $snapshot = FinancialSummarySnapshot::query()->create([
            'status' => 'queued',
        ]);

        (new GenerateFinancialSummarySnapshot($snapshot->id))->handle(app(FinancialSummaryService::class));

        $snapshot->refresh();
        $this->assertSame('completed', $snapshot->status);
        $this->assertSame(4, $snapshot->results['top_up_cards']['activated']['count']);
        $this->assertSame('33.00', $snapshot->results['top_up_cards']['activated']['amount']);
        $this->assertSame('12.00', $snapshot->results['top_up_cards']['active']['amount']);
        $this->assertSame('5.00', $snapshot->results['top_up_cards']['redeemed']['amount']);
        $this->assertSame(1, $snapshot->results['top_up_cards']['blocked']['count']);
        $this->assertSame('7.00', $snapshot->results['top_up_cards']['blocked']['amount']);
        $this->assertSame(1, $snapshot->results['top_up_cards']['expired']['count']);
        $this->assertSame('9.00', $snapshot->results['top_up_cards']['expired']['amount']);
        $systemMoneyAccounts = collect($snapshot->results['system_money']['accounts'])->keyBy('code');
        $this->assertSame('100.00', $systemMoneyAccounts[LedgerAccountCode::CashTopup->value]['balance']);
        $this->assertSame('40.00', $snapshot->results['system_money']['internet_service_revenue']['ftth']);
        $this->assertSame('12.00', $snapshot->results['system_money']['internet_service_revenue']['cg']);
        $this->assertSame('25.00', $systemMoneyAccounts[LedgerAccountCode::PackageRevenue->value]['balance']);
        $this->assertSame('72.00', $snapshot->results['system_money']['wallet_balance']);
        $this->assertSame('6.00', $snapshot->results['system_money']['adjustments']['debits']);
        $this->assertSame('2.00', $snapshot->results['system_money']['adjustments']['credits']);
        $this->assertSame('6.00', $snapshot->results['adjustments']['debits']);
        $this->assertSame('2.00', $snapshot->results['adjustments']['credits']);
        $this->assertSame('4.00', $snapshot->results['adjustments']['net_expense']);
    }

    private function createReportAdmin(): Admin
    {
        RolePermissionSeeder::sync();

        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['notifications.view', 'reports.view']);

        return $admin;
    }
}
