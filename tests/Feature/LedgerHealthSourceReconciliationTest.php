<?php

namespace Tests\Feature;

use App\Enums\BillPaymentStatus;
use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\PackageOrderStatus;
use App\Enums\TopUpCardStatus;
use App\Models\BillPayment;
use App\Models\Network;
use App\Models\Package;
use App\Models\PackageOrder;
use App\Models\TopUpCard;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use App\Services\Reports\LedgerHealthReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerHealthSourceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_used_top_up_card_without_ledger_and_orphan_topup(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 0]);
        $user = $wallet->user;
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        TopUpCard::factory()->redeemed($user)->create([
            'amount' => 100,
            'ledger_transaction_id' => null,
        ]);

        $poster->creditWallet(
            wallet: $wallet->fresh(),
            amount: 250,
            contraAccount: LedgerAccountCode::CashTopup,
            type: LedgerTransactionType::Topup,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'orphan-topup',
        );

        $report = app(LedgerHealthReportService::class)->generate();

        $this->assertGreaterThanOrEqual(2, $report['health']['source_mismatches']);
        $this->assertTrue(
            collect($report['sourceMismatches'])->contains(
                fn (array $row): bool => $row['source'] === 'topup_card' && $row['issue'] === 'missing_ledger',
            ),
        );
        $this->assertTrue(
            collect($report['sourceMismatches'])->contains(
                fn (array $row): bool => $row['source'] === 'ledger_transaction'
                    && $row['issue'] === 'missing_source'
                    && $row['detail'] === LedgerTransactionType::Topup->value,
            ),
        );
    }

    public function test_detects_top_up_card_amount_and_status_mismatches(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 0]);
        $user = $wallet->user;
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        $transaction = $poster->creditWallet(
            wallet: $wallet->fresh(),
            amount: 100,
            contraAccount: LedgerAccountCode::CashTopup,
            type: LedgerTransactionType::Topup,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'card-amount',
        );

        TopUpCard::factory()->redeemed($user)->create([
            'amount' => 500,
            'ledger_transaction_id' => $transaction->id,
        ]);

        $processing = $poster->creditWallet(
            wallet: $wallet->fresh(),
            amount: 50,
            contraAccount: LedgerAccountCode::CashTopup,
            type: LedgerTransactionType::Topup,
            status: LedgerTransactionStatus::Processing,
            idempotencyKey: 'card-status',
        );

        TopUpCard::factory()->redeemed($user)->create([
            'amount' => 50,
            'ledger_transaction_id' => $processing->id,
        ]);

        $report = app(LedgerHealthReportService::class)->generate();

        $this->assertTrue(
            collect($report['sourceMismatches'])->contains(
                fn (array $row): bool => $row['source'] === 'topup_card' && $row['issue'] === 'amount_mismatch',
            ),
        );
        $this->assertTrue(
            collect($report['sourceMismatches'])->contains(
                fn (array $row): bool => $row['source'] === 'topup_card' && $row['issue'] === 'status_mismatch',
            ),
        );
    }

    public function test_detects_bill_payment_status_mismatch_and_orphan_ftth_bill(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 1000]);
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        $completedTxn = $poster->debitWallet(
            wallet: $wallet->fresh(),
            amount: 100,
            contraAccount: LedgerAccountCode::FtthClearing,
            type: LedgerTransactionType::FtthBill,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'bill-status',
        );

        BillPayment::query()->create([
            'ledger_transaction_id' => $completedTxn->id,
            'broadband_account_number' => 'CG12345678',
            'status' => BillPaymentStatus::Processing,
        ]);

        $poster->debitWallet(
            wallet: $wallet->fresh(),
            amount: 50,
            contraAccount: LedgerAccountCode::FtthClearing,
            type: LedgerTransactionType::FtthBill,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'orphan-bill',
        );

        $report = app(LedgerHealthReportService::class)->generate();

        $this->assertTrue(
            collect($report['sourceMismatches'])->contains(
                fn (array $row): bool => $row['source'] === 'bill_payment' && $row['issue'] === 'status_mismatch',
            ),
        );
        $this->assertTrue(
            collect($report['sourceMismatches'])->contains(
                fn (array $row): bool => $row['source'] === 'ledger_transaction'
                    && $row['issue'] === 'missing_source'
                    && $row['detail'] === LedgerTransactionType::FtthBill->value,
            ),
        );
    }

    public function test_detects_package_order_missing_ledger_and_amount_mismatch(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 5000]);
        $user = $wallet->user;
        $package = Package::factory()->create([
            'network_id' => Network::factory(),
            'price' => 2000,
        ]);
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        PackageOrder::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'ledger_transaction_id' => null,
            'status' => PackageOrderStatus::Completed,
            'snapshot' => ['package_id' => $package->id, 'price' => 2000],
            'completed_at' => now(),
        ]);

        $transaction = $poster->debitWallet(
            wallet: $wallet->fresh(),
            amount: 1500,
            contraAccount: LedgerAccountCode::PackageRevenue,
            type: LedgerTransactionType::WifiPackage,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'package-amount',
        );

        PackageOrder::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'ledger_transaction_id' => $transaction->id,
            'status' => PackageOrderStatus::Completed,
            'snapshot' => ['package_id' => $package->id, 'price' => 2000],
            'completed_at' => now(),
        ]);

        $report = app(LedgerHealthReportService::class)->generate();

        $this->assertTrue(
            collect($report['sourceMismatches'])->contains(
                fn (array $row): bool => $row['source'] === 'package_order' && $row['issue'] === 'missing_ledger',
            ),
        );
        $this->assertTrue(
            collect($report['sourceMismatches'])->contains(
                fn (array $row): bool => $row['source'] === 'package_order' && $row['issue'] === 'amount_mismatch',
            ),
        );
    }

    public function test_matching_sources_are_not_flagged(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 10000]);
        $user = $wallet->user;
        $package = Package::factory()->create([
            'network_id' => Network::factory(),
            'price' => 500,
        ]);
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        $topup = $poster->creditWallet(
            wallet: $wallet->fresh(),
            amount: 100,
            contraAccount: LedgerAccountCode::CashTopup,
            type: LedgerTransactionType::Topup,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'ok-topup',
        );
        TopUpCard::factory()->redeemed($user)->create([
            'amount' => 100,
            'status' => TopUpCardStatus::Used,
            'ledger_transaction_id' => $topup->id,
        ]);

        $bill = $poster->debitWallet(
            wallet: $wallet->fresh(),
            amount: 200,
            contraAccount: LedgerAccountCode::FtthClearing,
            type: LedgerTransactionType::FtthBill,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'ok-bill',
        );
        BillPayment::query()->create([
            'ledger_transaction_id' => $bill->id,
            'broadband_account_number' => 'CG87654321',
            'status' => BillPaymentStatus::Completed,
            'confirmed_at' => now(),
        ]);

        $packageTxn = $poster->debitWallet(
            wallet: $wallet->fresh(),
            amount: 500,
            contraAccount: LedgerAccountCode::PackageRevenue,
            type: LedgerTransactionType::WifiPackage,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'ok-package',
        );
        PackageOrder::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'ledger_transaction_id' => $packageTxn->id,
            'status' => PackageOrderStatus::Completed,
            'snapshot' => ['package_id' => $package->id, 'price' => 500],
            'completed_at' => now(),
        ]);

        $report = app(LedgerHealthReportService::class)->generate();

        $this->assertSame(0, $report['health']['source_mismatches']);
        $this->assertSame([], $report['sourceMismatches']);
    }
}
