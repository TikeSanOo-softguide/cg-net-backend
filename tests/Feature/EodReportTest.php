<?php

namespace Tests\Feature;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Models\Admin;
use App\Models\LedgerTransaction;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EodReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_eod_report_summarizes_entries_and_transaction_statuses_for_selected_date(): void
    {
        $admin = Admin::factory()->create();
        $wallet = Wallet::factory()->create(['balance' => 0]);
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        $credit = $poster->creditWallet(
            wallet: $wallet->fresh(),
            amount: 1500,
            contraAccount: LedgerAccountCode::CashTopup,
            type: LedgerTransactionType::Topup,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'eod-credit',
        );
        $debit = $poster->debitWallet(
            wallet: $wallet->fresh(),
            amount: 500,
            contraAccount: LedgerAccountCode::FtthClearing,
            type: LedgerTransactionType::FtthBill,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'eod-debit',
        );
        $adjustment = $poster->creditWallet(
            wallet: $wallet->fresh(),
            amount: 250,
            contraAccount: LedgerAccountCode::AdjustmentExpense,
            type: LedgerTransactionType::Adjustment,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'eod-adjustment',
        );

        LedgerTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'status' => LedgerTransactionStatus::Failed,
            'type' => LedgerTransactionType::Topup,
            'amount' => 100,
            'created_at' => '2026-09-30 12:00:00',
            'updated_at' => '2026-09-30 12:00:00',
        ]);

        foreach (
            [
                [$credit, '2026-09-30 09:00:00'],
                [$debit, '2026-09-30 10:00:00'],
                [$adjustment, '2026-09-30 11:00:00'],
            ] as [$transaction, $createdAt]
        ) {
            $transaction->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
            $transaction->entries()->update(['created_at' => $createdAt]);
        }

        $this->actingAs($admin, 'web')
            ->get('/reports/eod?date=2026-09-30')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Reports/Eod/Index')
                    ->where('date', '2026-09-30')
                    ->where('summary.entries', 3)
                    ->where('summary.credits', 1750)
                    ->where('summary.debits', 500)
                    ->where('summary.net', 1250)
                    ->where('summary.completed_transactions', 3)
                    ->where('summary.failed_transactions', 1)
                    ->has('breakdown', 3)
                    ->has('entries.data', 3)
                    ->has('entries.data.0.ledger_entries', 2)
                    ->where('entries.data.0.ledger_entries.0.line_no', 1)
                    ->where('entries.data.0.ledger_entries.1.line_no', 2)
                    ->has('entries.data.0.ledger_entries.0.created_at')
                    ->where('entries.total', 3)
                    ->where('entries.current_page', 1)
                    ->where('entries.per_page', 15),
            );
    }

    public function test_eod_report_paginates_ledger_entries_for_selected_date(): void
    {
        $admin = Admin::factory()->create();
        $wallet = Wallet::factory()->create(['balance' => 0]);
        $poster = app(LedgerPoster::class);
        $poster->ensureSystemAccounts();
        $poster->ensureCustomerLiabilityAccount($wallet);

        for ($index = 0; $index < 16; $index++) {
            $transaction = $poster->creditWallet(
                wallet: $wallet->fresh(),
                amount: 100 + $index,
                contraAccount: LedgerAccountCode::CashTopup,
                type: LedgerTransactionType::Topup,
                status: LedgerTransactionStatus::Completed,
                idempotencyKey: "eod-page-{$index}",
            );
            $createdAt = sprintf('2026-09-30 %02d:00:00', $index);
            $transaction->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
            $transaction->entries()->update(['created_at' => $createdAt]);
        }

        $this->actingAs($admin, 'web')
            ->get('/reports/eod?date=2026-09-30')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Reports/Eod/Index')
                    ->where('entries.total', 16)
                    ->where('entries.current_page', 1)
                    ->has('entries.data', 15),
            );

        $this->actingAs($admin, 'web')
            ->get('/reports/eod?date=2026-09-30&page=2')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Reports/Eod/Index')
                    ->where('entries.total', 16)
                    ->where('entries.current_page', 2)
                    ->has('entries.data', 1),
            );
    }
}
