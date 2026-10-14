<?php

namespace Database\Seeders;

use App\Enums\BillPaymentStatus;
use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletActorType;
use App\Enums\WalletStatus;
use App\Models\BillPayment;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Ledger\LedgerPoster;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BillingSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @param  list<int>  $userIds
     */
    public function run(array $userIds = []): void
    {
        echo "Billing seeder started\n";

        $users = $userIds
            ? User::query()->whereIn('id', $userIds)->orderBy('id')->get()
            : User::query()->orderByDesc('id')->limit(12)->get()->sortBy('id')->values();

        if ($users->isEmpty()) {
            throw new \RuntimeException('Run CustomerSeeder before BillingSeeder.');
        }

        $users->take(12)->values()->each(function (User $user, int $index): void {
            if (!$user->broadband_account_number) {
                return;
            }

            $paidAt = now()->subDays($index * 2);
            $amount = fake()->randomElement([50, 100, 250, 500]);
            $wallet = $user->wallet()->firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0, 'status' => WalletStatus::Active, 'version' => 1],
            );

            $poster = app(LedgerPoster::class);
            $poster->ensureCustomerLiabilityAccount($wallet);
            $wallet = $wallet->fresh();
            $timestamp = $paidAt->toDateTimeString();

            if ((int) $wallet->balance < $amount) {
                $topup = $poster->creditWallet(
                    wallet: $wallet,
                    amount: $amount - (int) $wallet->balance,
                    contraAccount: LedgerAccountCode::CashTopup,
                    type: LedgerTransactionType::Topup,
                    status: LedgerTransactionStatus::Completed,
                    idempotencyKey: 'seed-bill-topup:' . $user->id . ':' . $index,
                    actorType: WalletActorType::System,
                    actorId: $user->id,
                );
                $this->backdateLedgerTransaction($topup, $timestamp);
            }

            $transaction = $poster->debitWallet(
                wallet: $wallet->fresh(),
                amount: $amount,
                contraAccount: LedgerAccountCode::FtthClearing,
                type: LedgerTransactionType::FtthBill,
                status: LedgerTransactionStatus::Completed,
                idempotencyKey: 'seed-bill:' . $user->id . ':' . $index,
                actorType: WalletActorType::System,
                actorId: $user->id,
                transactionNo: 'BILL-' . strtoupper(fake()->bothify('???-####')),
            );
            $this->backdateLedgerTransaction($transaction, $timestamp);

            $billPayment = BillPayment::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'broadband_account_number' => $user->broadband_account_number,
                'status' => BillPaymentStatus::Completed,
                'external_bill_ref' => 'BILL-' . fake()->numerify('####'),
                'external_payment_ref' => 'PAY-' . fake()->numerify('####'),
                'external_response' => ['gateway' => 'kbzpay'],
                'confirmed_at' => $paidAt,
            ]);
            DB::table('bill_payments')
                ->where('id', $billPayment->id)
                ->update(['created_at' => $timestamp]);
        });
    }

    private function backdateLedgerTransaction(LedgerTransaction $transaction, string $timestamp): void
    {
        DB::table('ledger_transactions')->where('id', $transaction->id)->update([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'posted_at' => $timestamp,
        ]);
        DB::table('ledger_entries')
            ->where('ledger_transaction_id', $transaction->id)
            ->update(['created_at' => $timestamp]);
    }
}
