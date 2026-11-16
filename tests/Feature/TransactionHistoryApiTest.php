<?php

namespace Tests\Feature;

use App\Enums\BillPaymentStatus;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\PackageOrderStatus;
use App\Models\BillPayment;
use App\Models\LedgerTransaction;
use App\Models\PackageOrder;
use App\Models\TopUpCard;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_cursor_paginate_only_their_transaction_history(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $otherUser = User::factory()->create();
        $otherWallet = Wallet::factory()->for($otherUser)->create();

        $ownTransactions = LedgerTransaction::factory()
            ->count(3)
            ->for($wallet)
            ->create();
        $otherTransaction = LedgerTransaction::factory()
            ->for($otherWallet)
            ->create();

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token, 'Bearer')
            ->getJson('/api/customer/transactions?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');

        $firstPageIds = collect($response->json('data'))->pluck('id');
        $this->assertCount(2, $firstPageIds->intersect($ownTransactions->pluck('id')));
        $this->assertNotContains($otherTransaction->id, $firstPageIds);

        parse_str((string) parse_url($response->json('links.next'), PHP_URL_QUERY), $nextPageQuery);
        $this->assertArrayHasKey('cursor', $nextPageQuery);

        $secondPageIds = collect(
            $this->withToken($token, 'Bearer')
                ->getJson('/api/customer/transactions?'.http_build_query($nextPageQuery))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->json('data'),
        )->pluck('id');

        $this->assertCount(3, $firstPageIds->merge($secondPageIds)->unique());
        $this->assertNotContains($otherTransaction->id, $secondPageIds);
    }

    public function test_transaction_history_includes_title_and_description_codes(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        LedgerTransaction::factory()->for($wallet)->create([
            'type' => LedgerTransactionType::Topup,
            'status' => LedgerTransactionStatus::Completed,
        ]);
        LedgerTransaction::factory()->for($wallet)->create([
            'type' => LedgerTransactionType::WifiPackage,
            'status' => LedgerTransactionStatus::Completed,
        ]);
        LedgerTransaction::factory()->for($wallet)->create([
            'type' => LedgerTransactionType::WifiPackage,
            'status' => LedgerTransactionStatus::Processing,
        ]);
        LedgerTransaction::factory()->for($wallet)->create([
            'type' => LedgerTransactionType::FtthBill,
            'status' => LedgerTransactionStatus::Completed,
        ]);
        LedgerTransaction::factory()->for($wallet)->create([
            'type' => LedgerTransactionType::CgBill,
            'status' => LedgerTransactionStatus::Processing,
        ]);
        LedgerTransaction::factory()->for($wallet)->create([
            'type' => LedgerTransactionType::Refund,
            'status' => LedgerTransactionStatus::Completed,
        ]);

        $codes = collect(
            $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
                ->getJson('/api/customer/transactions')
                ->assertOk()
                ->json('data'),
        )->mapWithKeys(fn (array $row) => [$row['type'].':'.$row['status'] => [
            $row['title_code'],
            $row['description_code'],
        ]]);

        $this->assertSame(['MSG-TOPUP-010', 'MSG-TOPUP-010'], $codes['topup:completed']);
        $this->assertSame(['MSG-PKG-005', 'MSG-PKG-005'], $codes['wifi_package:completed']);
        $this->assertSame(['MSG-PKG-008', 'MSG-PKG-008'], $codes['wifi_package:processing']);
        $this->assertSame(['MSG-FTTH-008', 'MSG-FTTH-008'], $codes['ftth_bill:completed']);
        $this->assertSame(['MSG-FTTH-010', 'MSG-FTTH-010'], $codes['cg_bill:processing']);
        $this->assertSame([null, null], $codes['refund:completed']);
    }

    public function test_transaction_detail_returns_type_specific_fields_for_the_owner(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $other = User::factory()->create();
        $otherWallet = Wallet::factory()->for($other)->create();
        $token = $user->createToken('test')->plainTextToken;

        $topup = LedgerTransaction::factory()->for($wallet)->create([
            'type' => LedgerTransactionType::Topup,
            'status' => LedgerTransactionStatus::Completed,
            'amount' => 10000,
        ]);
        TopUpCard::factory()->create([
            'serial_no' => '1234567890123456',
            'ledger_transaction_id' => $topup->id,
            'redeemed_by' => $user->id,
        ]);

        $package = LedgerTransaction::factory()->for($wallet)->create([
            'type' => LedgerTransactionType::WifiPackage,
            'status' => LedgerTransactionStatus::Completed,
            'amount' => 50000,
        ]);
        PackageOrder::query()->create([
            'user_id' => $user->id,
            'ledger_transaction_id' => $package->id,
            'status' => PackageOrderStatus::Completed,
            'product_id' => 'pkg-20',
            'product_price' => 50000,
            'snapshot' => [
                'product_id' => 'pkg-20',
                'product_name' => 'Home 20',
                'product_speed' => '20Mbps',
            ],
        ]);

        $bill = LedgerTransaction::factory()->for($wallet)->create([
            'type' => LedgerTransactionType::FtthBill,
            'status' => LedgerTransactionStatus::Completed,
            'amount' => 15000,
        ]);
        BillPayment::query()->create([
            'ledger_transaction_id' => $bill->id,
            'broadband_account_number' => '10001234',
            'order_no' => 'FTTH-DETAIL-1',
            'service_type' => 'FTTH',
            'product_id' => 'ftth-100',
            'product_price' => 15000,
            'remark' => 'April bill',
            'snapshot' => [
                'product_name' => 'FTTH 100M',
                'product_speed' => '100M',
            ],
            'status' => BillPaymentStatus::Completed,
            'confirmed_at' => now(),
        ]);

        $foreign = LedgerTransaction::factory()->for($otherWallet)->create([
            'type' => LedgerTransactionType::Topup,
            'status' => LedgerTransactionStatus::Completed,
        ]);

        $this->withToken($token, 'Bearer')
            ->getJson('/api/customer/transactions/'.$topup->id)
            ->assertOk()
            ->assertJsonPath('data.serial_masked', '1234****3456')
            ->assertJsonPath('data.title_code', 'MSG-TOPUP-010')
            ->assertJsonMissingPath('data.pin')
            ->assertJsonMissingPath('data.serial_no');

        $this->withToken($token, 'Bearer')
            ->getJson('/api/customer/transactions/'.$package->id)
            ->assertOk()
            ->assertJsonPath('data.package.product_id', 'pkg-20')
            ->assertJsonPath('data.package.product_name', 'Home 20')
            ->assertJsonPath('data.package.product_speed', '20Mbps')
            ->assertJsonPath('data.title_code', 'MSG-PKG-005');

        $this->withToken($token, 'Bearer')
            ->getJson('/api/customer/transactions/'.$bill->id)
            ->assertOk()
            ->assertJsonPath('data.account', '10001234')
            ->assertJsonPath('data.service_type', 'FTTH')
            ->assertJsonPath('data.remark', 'April bill')
            ->assertJsonPath('data.package.product_name', 'FTTH 100M')
            ->assertJsonPath('data.package.product_speed', '100M')
            ->assertJsonPath('data.title_code', 'MSG-FTTH-008');

        $this->withToken($token, 'Bearer')
            ->getJson('/api/customer/transactions/'.$foreign->id)
            ->assertNotFound()
            ->assertJsonPath('code', 'MSG-SYS-004');
    }

    public function test_transaction_detail_requires_authentication(): void
    {
        $this->getJson('/api/customer/transactions/1')->assertUnauthorized();
    }

    public function test_transaction_history_requires_authentication(): void
    {
        $this->getJson('/api/customer/transactions')->assertUnauthorized();
    }

    public function test_transaction_history_validates_page_size(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/customer/transactions?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
}
