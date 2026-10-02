<?php

namespace Tests\Feature;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Models\Admin;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_credit_customer_wallet(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $customer->id, 'balance' => 1000]);
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        $this->actingAs($admin, 'web')
            ->from("/customers/{$customer->id}")
            ->post("/customers/{$customer->id}/wallet/adjust", [
                'direction' => 'credit',
                'amount' => 500,
                'note' => 'Goodwill credit',
            ])
            ->assertRedirect("/customers/{$customer->id}")
            ->assertSessionHas('success', 'customers.wallet_adjust.success');

        $this->assertSame(1500, (int) $wallet->fresh()->balance);

        $adjustment = LedgerTransaction::query()->where('type', LedgerTransactionType::Adjustment)->latest('id')->first();

        $this->assertNotNull($adjustment);
        $this->assertSame(500, (int) $adjustment->amount);
        $this->assertSame('Goodwill credit', $adjustment->note);
        $this->assertNull($adjustment->related_transaction_id);
        $this->assertSame(LedgerTransactionStatus::Completed, $adjustment->status);
    }

    public function test_admin_can_debit_with_related_transaction(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $customer->id, 'balance' => 0]);
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        $source = $poster->creditWallet(
            wallet: $wallet->fresh(),
            amount: 2000,
            contraAccount: LedgerAccountCode::CashTopup,
            type: LedgerTransactionType::Topup,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'source-topup',
        );

        $this->actingAs($admin, 'web')
            ->post("/customers/{$customer->id}/wallet/adjust", [
                'direction' => 'debit',
                'amount' => 300,
                'note' => 'Correct over-credit on top-up',
                'related_transaction_id' => $source->id,
            ])
            ->assertRedirect();

        $this->assertSame(1700, (int) $wallet->fresh()->balance);

        $adjustment = LedgerTransaction::query()
            ->where('type', LedgerTransactionType::Adjustment)
            ->latest('id')
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertSame($source->id, $adjustment->related_transaction_id);
        $this->assertSame('Correct over-credit on top-up', $adjustment->note);
    }

    public function test_related_transaction_must_belong_to_customer(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $customer->id, 'balance' => 1000]);
        $otherWallet = Wallet::factory()->create(['user_id' => $other->id, 'balance' => 0]);
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);
        $poster->ensureCustomerLiabilityAccount($otherWallet);

        $foreign = $poster->creditWallet(
            wallet: $otherWallet->fresh(),
            amount: 100,
            contraAccount: LedgerAccountCode::CashTopup,
            type: LedgerTransactionType::Topup,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'foreign-topup',
        );

        $this->actingAs($admin, 'web')
            ->from("/customers/{$customer->id}")
            ->post("/customers/{$customer->id}/wallet/adjust", [
                'direction' => 'credit',
                'amount' => 50,
                'note' => 'Bad link',
                'related_transaction_id' => $foreign->id,
            ])
            ->assertRedirect("/customers/{$customer->id}")
            ->assertSessionHasErrors('related_transaction_id');

        $this->assertSame(1000, (int) $wallet->fresh()->balance);
    }

    public function test_debit_cannot_exceed_balance(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $customer->id, 'balance' => 100]);
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        $this->actingAs($admin, 'web')
            ->from("/customers/{$customer->id}")
            ->post("/customers/{$customer->id}/wallet/adjust", [
                'direction' => 'debit',
                'amount' => 500,
                'note' => 'Too much',
            ])
            ->assertRedirect("/customers/{$customer->id}")
            ->assertSessionHasErrors('amount');

        $this->assertSame(100, (int) $wallet->fresh()->balance);
    }
}
