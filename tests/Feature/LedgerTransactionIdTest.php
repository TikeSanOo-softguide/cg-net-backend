<?php

namespace Tests\Feature;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\TransactionIdPrefix;
use App\Enums\WalletStatus;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use App\Services\Ledger\TransactionIdGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LedgerTransactionIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_wifi_package_posts_use_the_pkg_transaction_id_format(): void
    {
        $transaction = $this->postDebit(LedgerTransactionType::WifiPackage, 'pkg-id-format-1');

        $this->assertTrue(app(TransactionIdGenerator::class)->matches(
            $transaction->transaction_no,
            TransactionIdPrefix::BuyPackage,
        ));
    }

    public function test_ftth_bill_posts_default_to_pbft_when_no_prefix_is_passed(): void
    {
        $transaction = $this->postDebit(LedgerTransactionType::FtthBill, 'ftth-id-format-1');

        $this->assertTrue(app(TransactionIdGenerator::class)->matches(
            $transaction->transaction_no,
            TransactionIdPrefix::PayBillFtth,
        ));
    }

    public function test_pay_bill_cg_prefix_is_used_when_provided(): void
    {
        $transaction = $this->postDebit(
            LedgerTransactionType::FtthBill,
            'cg-id-format-1',
            TransactionIdPrefix::PayBillCg,
        );

        $this->assertTrue(app(TransactionIdGenerator::class)->matches(
            $transaction->transaction_no,
            TransactionIdPrefix::PayBillCg,
        ));
    }

    public function test_topup_posts_keep_the_legacy_transaction_id_format(): void
    {
        $wallet = $this->wallet();
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        $transaction = $poster->creditWallet(
            wallet: $wallet,
            amount: 1000,
            contraAccount: LedgerAccountCode::CashTopup,
            type: LedgerTransactionType::Topup,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'topup-legacy-id-1',
        );

        $this->assertMatchesRegularExpression('/^TOPUP-[0-9A-HJKMNP-TV-Z]{26}$/i', $transaction->transaction_no);
        $this->assertFalse(app(TransactionIdGenerator::class)->matches(
            $transaction->transaction_no,
            TransactionIdPrefix::TopUp,
        ));
    }

    public function test_duplicate_transaction_numbers_are_rejected_by_the_database(): void
    {
        LedgerTransaction::factory()->create(['transaction_no' => 'PKG261109ABCDEFG']);

        $this->expectException(UniqueConstraintViolationException::class);

        LedgerTransaction::factory()->create(['transaction_no' => 'PKG261109ABCDEFG']);
    }

    public function test_transaction_no_collisions_are_retried_with_a_new_id(): void
    {
        LedgerTransaction::factory()->create(['transaction_no' => 'PKG261109AAAAAAA']);

        $generator = Mockery::mock(TransactionIdGenerator::class);
        $generator->shouldReceive('generate')
            ->times(2)
            ->andReturn('PKG261109AAAAAAA', 'PKG261109BBBBBBB');
        $this->app->instance(TransactionIdGenerator::class, $generator);

        $transaction = $this->postDebit(LedgerTransactionType::WifiPackage, 'pkg-collision-retry-1');

        $this->assertSame('PKG261109BBBBBBB', $transaction->transaction_no);
    }

    public function test_idempotency_returns_the_original_transaction_id(): void
    {
        $first = $this->postDebit(LedgerTransactionType::WifiPackage, 'pkg-idemp-same-1');
        $second = $this->postDebit(LedgerTransactionType::WifiPackage, 'pkg-idemp-same-1');

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->transaction_no, $second->transaction_no);
        $this->assertSame(1, LedgerTransaction::query()->where('idempotency_key', 'pkg-idemp-same-1')->count());
    }

    public function test_explicit_transaction_no_is_not_regenerated_on_collision(): void
    {
        LedgerTransaction::factory()->create(['transaction_no' => 'PKG261109CCCCCCC']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->postDebit(
            LedgerTransactionType::WifiPackage,
            'pkg-explicit-collision-1',
            TransactionIdPrefix::BuyPackage,
            'PKG261109CCCCCCC',
        );
    }

    private function postDebit(
        LedgerTransactionType $type,
        string $idempotencyKey,
        ?TransactionIdPrefix $prefix = null,
        ?string $transactionNo = null,
    ): LedgerTransaction {
        $wallet = $this->wallet();
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        $contra = $type === LedgerTransactionType::FtthBill
            ? LedgerAccountCode::FtthClearing
            : LedgerAccountCode::PackageRevenue;

        return $poster->debitWallet(
            wallet: $wallet,
            amount: 1000,
            contraAccount: $contra,
            type: $type,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: $idempotencyKey,
            transactionNo: $transactionNo,
            transactionIdPrefix: $prefix,
        );
    }

    private function wallet(): Wallet
    {
        $user = User::factory()->create();

        return Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => 5000,
            'status' => WalletStatus::Active,
        ]);
    }
}
