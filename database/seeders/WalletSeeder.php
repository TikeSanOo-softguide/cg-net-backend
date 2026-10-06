<?php

namespace Database\Seeders;

use App\Enums\BillPaymentStatus;
use App\Enums\CustomerPackageStatus;
use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\PackageOrderStatus;
use App\Enums\WalletActorType;
use App\Enums\WalletStatus;
use App\Models\BillPayment;
use App\Models\CustomerPackage;
use App\Models\LedgerTransaction;
use App\Models\Package;
use App\Models\PackageOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletSeeder extends Seeder
{
    private const MAX_LINKED_PER_TYPE = 10;

    private const TOPUP_AMOUNTS = [50, 100, 250, 500];

    private const FTTH_NETWORK_IDS = [1, 2];

    private const WIFI_NETWORK_ID = 3;

    private int $sequence = 0;

    public function run(): void
    {
        echo "Wallet seeder started\n";

        $this->ledger()->ensureSystemAccounts();

        $users = User::query()->with('wallet')->get();
        $totalUsers = $users->count();

        foreach ($users as $index => $user) {
            $wallet =
                $user->wallet ??
                Wallet::factory()->create([
                    'user_id' => $user->id,
                    'status' => WalletStatus::Active,
                    'balance' => 0,
                    'version' => 1,
                ]);

            $this->ledger()->ensureCustomerLiabilityAccount($wallet);

            $transactionCount = $index === $totalUsers - 1 ? 100 : 12;
            $this->seedWalletTransactions($wallet, $user, $transactionCount);
        }
    }

    private function seedWalletTransactions(Wallet $wallet, User $user, int $count): void
    {
        $lastTransactionId = (int) LedgerTransaction::query()->where('wallet_id', $wallet->id)->max('id');

        foreach ([500, 500, 250] as $seedAmount) {
            $this->createTopup($wallet, $user, $seedAmount, LedgerTransactionStatus::Completed);
        }

        $movableTypes = [
            LedgerTransactionType::Topup,
            LedgerTransactionType::FtthBill,
            LedgerTransactionType::WifiPackage,
            LedgerTransactionType::Adjustment,
        ];

        $linkedCounts = [
            LedgerTransactionType::FtthBill->value => 0,
            LedgerTransactionType::WifiPackage->value => 0,
        ];

        for ($index = 2; $index <= $count; $index++) {
            $available = collect($movableTypes)
                ->reject(
                    fn ($t) => isset($linkedCounts[$t->value]) && $linkedCounts[$t->value] >= self::MAX_LINKED_PER_TYPE,
                )
                ->values();

            $type = $this->pickWeightedType($available);
            $amount = fake()->numberBetween(100, 5000);

            match ($type) {
                LedgerTransactionType::Topup => $this->createTopup(
                    $wallet,
                    $user,
                    fake()->randomElement(self::TOPUP_AMOUNTS),
                    $this->topupStatus(),
                ),
                LedgerTransactionType::FtthBill => $this->handleFtthBill($wallet, $user, $linkedCounts),
                LedgerTransactionType::WifiPackage => $this->handleWifiPackage($wallet, $user, $linkedCounts),
                LedgerTransactionType::Adjustment => $this->handleAdjustment($wallet, $user, $amount),
                default => null,
            };
        }

        $this->spreadTransactionDates($wallet, $lastTransactionId);
    }

    private function spreadTransactionDates(Wallet $wallet, int $lastTransactionId): void
    {
        $transactions = LedgerTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('id', '>', $lastTransactionId)
            ->orderBy('id')
            ->get(['id', 'posted_at']);

        if ($transactions->isEmpty()) {
            return;
        }

        $windowStart = now()->subDays(29)->startOfDay();
        $windowEnd = now();
        $windowSeconds = (int) $windowStart->diffInSeconds($windowEnd);
        $lastIndex = max($transactions->count() - 1, 1);

        foreach ($transactions as $index => $transaction) {
            $timestamp = $windowStart
                ->copy()
                ->addSeconds((int) round(($windowSeconds * $index) / $lastIndex))
                ->toDateTimeString();

            DB::table('ledger_transactions')
                ->where('id', $transaction->id)
                ->update([
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'posted_at' => $transaction->posted_at === null ? null : $timestamp,
                ]);

            DB::table('ledger_entries')
                ->where('ledger_transaction_id', $transaction->id)
                ->update(['created_at' => $timestamp]);

            DB::table('bill_payments')->where('ledger_transaction_id', $transaction->id)->update([
                'created_at' => $timestamp,
            ]);
            DB::table('bill_payments')
                ->where('ledger_transaction_id', $transaction->id)
                ->whereNotNull('confirmed_at')
                ->update(['confirmed_at' => $timestamp]);

            DB::table('package_orders')->where('ledger_transaction_id', $transaction->id)->update([
                'created_at' => $timestamp,
            ]);
            DB::table('package_orders')
                ->where('ledger_transaction_id', $transaction->id)
                ->whereNotNull('completed_at')
                ->update(['completed_at' => $timestamp]);
        }
    }

    private function createTopup(
        Wallet $wallet,
        User $user,
        int $amount,
        LedgerTransactionStatus $status,
    ): void {
        if ($status !== LedgerTransactionStatus::Completed) {
            LedgerTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'transaction_no' => $this->nextTransactionNo($wallet),
                'type' => LedgerTransactionType::Topup,
                'status' => $status,
                'amount' => $amount,
                'idempotency_key' => (string) Str::uuid(),
                'actor_type' => WalletActorType::User,
                'actor_id' => $user->id,
                'posted_at' => null,
            ]);

            return;
        }

        $this->ledger()->creditWallet(
            wallet: $wallet->fresh(),
            amount: $amount,
            contraAccount: LedgerAccountCode::CashTopup,
            type: LedgerTransactionType::Topup,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: (string) Str::uuid(),
            actorType: WalletActorType::User,
            actorId: $user->id,
            transactionNo: $this->nextTransactionNo($wallet),
        );
    }

    private function handleFtthBill(Wallet $wallet, User $user, array &$linkedCounts): void
    {
        $accountNumber = $user->broadband_account_number ?? $this->generateUniqueAccountNumber();
        $user->forceFill(['broadband_account_number' => $accountNumber])->save();

        $package = Package::query()->whereIn('network_id', self::FTTH_NETWORK_IDS)->inRandomOrder()->first();

        if (! $package) {
            $this->createTopup($wallet, $user, fake()->randomElement(self::TOPUP_AMOUNTS), LedgerTransactionStatus::Completed);

            return;
        }

        $price = (int) $package->price;
        $wallet->refresh();
        $debitAmount = min($price, (int) $wallet->balance);

        if ($debitAmount <= 0) {
            $this->createTopup($wallet, $user, fake()->randomElement(self::TOPUP_AMOUNTS), LedgerTransactionStatus::Completed);

            return;
        }

        $billOutcome = fake()->randomElement([
            BillPaymentStatus::Processing,
            BillPaymentStatus::Completed,
            BillPaymentStatus::Failed,
        ]);

        $linkedCounts[LedgerTransactionType::FtthBill->value]++;

        $status = match ($billOutcome) {
            BillPaymentStatus::Processing => LedgerTransactionStatus::Processing,
            BillPaymentStatus::Completed => LedgerTransactionStatus::Completed,
            BillPaymentStatus::Failed => LedgerTransactionStatus::Processing,
        };

        $transaction = $this->ledger()->debitWallet(
            wallet: $wallet->fresh(),
            amount: $debitAmount,
            contraAccount: LedgerAccountCode::FtthClearing,
            type: LedgerTransactionType::FtthBill,
            status: $status,
            idempotencyKey: (string) Str::uuid(),
            actorType: WalletActorType::User,
            actorId: $user->id,
            transactionNo: $this->nextTransactionNo($wallet),
        );

        $this->createBillPayment($transaction, $accountNumber, $billOutcome);

        if ($billOutcome === BillPaymentStatus::Failed) {
            $this->refund($wallet->fresh(), $user, $debitAmount, $transaction);
            $transaction->update(['status' => LedgerTransactionStatus::Failed]);
        }
    }

    private function handleWifiPackage(Wallet $wallet, User $user, array &$linkedCounts): void
    {
        $package = Package::query()->where('network_id', self::WIFI_NETWORK_ID)->inRandomOrder()->first();

        if (! $package) {
            $this->createTopup($wallet, $user, fake()->randomElement(self::TOPUP_AMOUNTS), LedgerTransactionStatus::Completed);

            return;
        }

        $price = (int) $package->price;
        $wallet->refresh();
        $debitAmount = min($price, (int) $wallet->balance);

        if ($debitAmount <= 0) {
            $this->createTopup($wallet, $user, fake()->randomElement(self::TOPUP_AMOUNTS), LedgerTransactionStatus::Completed);

            return;
        }

        $orderOutcome = fake()->randomElement([
            PackageOrderStatus::Processing,
            PackageOrderStatus::Completed,
            PackageOrderStatus::Failed,
        ]);

        $linkedCounts[LedgerTransactionType::WifiPackage->value]++;

        $status = match ($orderOutcome) {
            PackageOrderStatus::Processing => LedgerTransactionStatus::Processing,
            PackageOrderStatus::Completed => LedgerTransactionStatus::Completed,
            PackageOrderStatus::Failed => LedgerTransactionStatus::Processing,
        };

        $transaction = $this->ledger()->debitWallet(
            wallet: $wallet->fresh(),
            amount: $debitAmount,
            contraAccount: LedgerAccountCode::PackageRevenue,
            type: LedgerTransactionType::WifiPackage,
            status: $status,
            idempotencyKey: (string) Str::uuid(),
            actorType: WalletActorType::User,
            actorId: $user->id,
            transactionNo: $this->nextTransactionNo($wallet),
        );

        $packageOrder = $this->createPackageOrder($user, $package, $transaction, $orderOutcome);

        if ($orderOutcome === PackageOrderStatus::Completed) {
            CustomerPackage::query()->create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'package_order_id' => $packageOrder->id,
                'starts_at' => now()->subDays(7),
                'expires_at' => now()->addDays(30),
                'status' => CustomerPackageStatus::Active,
            ]);
        } elseif ($orderOutcome === PackageOrderStatus::Failed) {
            $this->refund($wallet->fresh(), $user, $debitAmount, $transaction);
            $transaction->update(['status' => LedgerTransactionStatus::Failed]);
        }
    }

    private function handleAdjustment(Wallet $wallet, User $user, int $amount): void
    {
        $wallet->refresh();
        $isCredit = fake()->boolean();

        if (! $isCredit) {
            $amount = min($amount, (int) $wallet->balance);

            if ($amount <= 0) {
                $isCredit = true;
                $amount = fake()->numberBetween(100, 1000);
            }
        }

        if ($isCredit) {
            $this->ledger()->creditWallet(
                wallet: $wallet,
                amount: $amount,
                contraAccount: LedgerAccountCode::AdjustmentExpense,
                type: LedgerTransactionType::Adjustment,
                status: LedgerTransactionStatus::Completed,
                idempotencyKey: (string) Str::uuid(),
                actorType: WalletActorType::Admin,
                actorId: $user->id,
                transactionNo: $this->nextTransactionNo($wallet),
            );
        } else {
            $this->ledger()->debitWallet(
                wallet: $wallet,
                amount: $amount,
                contraAccount: LedgerAccountCode::AdjustmentExpense,
                type: LedgerTransactionType::Adjustment,
                status: LedgerTransactionStatus::Completed,
                idempotencyKey: (string) Str::uuid(),
                actorType: WalletActorType::Admin,
                actorId: $user->id,
                transactionNo: $this->nextTransactionNo($wallet),
            );
        }
    }

    private function refund(Wallet $wallet, User $user, int $amount, LedgerTransaction $reversedTransaction): void
    {
        $contra = match ($reversedTransaction->type) {
            LedgerTransactionType::FtthBill => LedgerAccountCode::FtthClearing,
            LedgerTransactionType::WifiPackage => LedgerAccountCode::PackageRevenue,
            default => LedgerAccountCode::AdjustmentExpense,
        };

        $this->ledger()->creditWallet(
            wallet: $wallet,
            amount: $amount,
            contraAccount: $contra,
            type: LedgerTransactionType::Refund,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'refund:'.$reversedTransaction->idempotency_key,
            actorType: WalletActorType::System,
            actorId: $user->id,
            transactionNo: $this->nextTransactionNo($wallet),
            reversalOf: $reversedTransaction->id,
        );
    }

    private function createBillPayment(
        LedgerTransaction $transaction,
        string $accountNumber,
        BillPaymentStatus $status,
    ): BillPayment {
        return BillPayment::query()->create([
            'ledger_transaction_id' => $transaction->id,
            'broadband_account_number' => $accountNumber,
            'status' => $status,
            'external_bill_ref' => 'BILL-'.fake()->numerify('####'),
            'external_payment_ref' => 'PAY-'.fake()->numerify('####'),
            'external_response' => ['broadband_account_number' => $accountNumber],
        ]);
    }

    private function createPackageOrder(
        User $user,
        Package $package,
        LedgerTransaction $transaction,
        PackageOrderStatus $status,
    ): PackageOrder {
        return PackageOrder::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'ledger_transaction_id' => $transaction->id,
            'status' => $status,
            'snapshot' => [
                'package_id' => $package->id,
                'price' => $package->price,
            ],
        ]);
    }

    private function pickWeightedType(Collection $available): LedgerTransactionType
    {
        $weight = fn (LedgerTransactionType $type): int => match ($type) {
            LedgerTransactionType::FtthBill => 4,
            LedgerTransactionType::WifiPackage => 4,
            LedgerTransactionType::Topup => 2,
            LedgerTransactionType::Adjustment => 1,
            default => 1,
        };

        $pool = $available->flatMap(fn ($type) => array_fill(0, $weight($type), $type));

        return $pool[array_rand($pool->all())];
    }

    private function nextTransactionNo(Wallet $wallet): string
    {
        $this->sequence++;

        return now()->format('YmdHis').$wallet->id.str_pad((string) $this->sequence, 5, '0', STR_PAD_LEFT);
    }

    private function generateUniqueAccountNumber(): string
    {
        do {
            $accountNumber = 'CG'.fake()->unique()->numerify('########');
        } while (User::query()->where('broadband_account_number', $accountNumber)->exists());

        return $accountNumber;
    }

    private function topupStatus(): LedgerTransactionStatus
    {
        return fake()->randomElement([
            LedgerTransactionStatus::Completed,
            LedgerTransactionStatus::Completed,
            LedgerTransactionStatus::Completed,
            LedgerTransactionStatus::Completed,
            LedgerTransactionStatus::Completed,
            LedgerTransactionStatus::Completed,
            LedgerTransactionStatus::Completed,
            LedgerTransactionStatus::Completed,
            LedgerTransactionStatus::Completed,
            LedgerTransactionStatus::Failed,
        ]);
    }

    private function ledger(): LedgerPoster
    {
        return app(LedgerPoster::class);
    }
}
