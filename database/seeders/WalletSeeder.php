<?php

namespace Database\Seeders;

use App\Enums\BillPaymentStatus;
use App\Enums\CustomerPackageStatus;
use App\Enums\PackageOrderStatus;
use App\Enums\WalletActorType;
use App\Enums\WalletEntryType;
use App\Enums\WalletStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\BillPayment;
use App\Models\BroadbandAccount;
use App\Models\CustomerPackage;
use App\Models\Package;
use App\Models\PackageOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class WalletSeeder extends Seeder
{
    /** Max number of Transfer / FtthBill / WifiPackage records per wallet, to keep things tidy. */
    private const MAX_LINKED_PER_TYPE = 10;

    /** Scratch cards only come in these fixed denominations — a topup can never be any other amount. */
    private const TOPUP_AMOUNTS = [50, 100, 250, 500];

    /** networks.id values: 1 and 2 are FTTH, 3 is WiFi (per the networks table). */
    private const FTTH_NETWORK_IDS = [1, 2];

    private const WIFI_NETWORK_ID = 3;

    /** Running counter so transaction_no stays unique even across a fast loop. */
    private int $sequence = 0;

    public function run(): void
    {
        echo "Wallet seeder started\n";

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

            // 12 transactions for standard users, 100 for the last user
            $transactionCount = $index === $totalUsers - 1 ? 100 : 12;

            $this->seedWalletTransactions($wallet, $user, $transactionCount);
        }
    }

    private function seedWalletTransactions(Wallet $wallet, User $user, int $count): void
    {
        // Every wallet starts from a few guaranteed scratch-card topups (not one arbitrary
        // lump sum) so later FTTH/WiFi debits have realistic funds to draw from.
        foreach ([500, 500, 250] as $seedAmount) {
            $this->createTopup($wallet, $user, $seedAmount, WalletTransactionStatus::Completed);
        }

        $movableTypes = [
            WalletTransactionType::Topup,
            WalletTransactionType::Transfer,
            WalletTransactionType::FtthBill,
            WalletTransactionType::WifiPackage,
            WalletTransactionType::Adjustment,
            // Refund is intentionally not picked at random — it only ever appears
            // as the automatic reversal of a failed FtthBill/WifiPackage below.
        ];

        $linkedCounts = [
            WalletTransactionType::Transfer->value => 0,
            WalletTransactionType::FtthBill->value => 0,
            WalletTransactionType::WifiPackage->value => 0,
        ];

        for ($index = 2; $index <= $count; $index++) {
            // Respect the per-type cap by excluding maxed-out types before picking.
            $available = collect($movableTypes)
                ->reject(
                    fn($t) => isset($linkedCounts[$t->value]) && $linkedCounts[$t->value] >= self::MAX_LINKED_PER_TYPE,
                )
                ->values();

            $type = $this->pickWeightedType($available);
            $amount = fake()->numberBetween(100, 5000);

            match ($type) {
                WalletTransactionType::Topup => $this->createTopup(
                    $wallet,
                    $user,
                    fake()->randomElement(self::TOPUP_AMOUNTS),
                    $this->topupStatus(),
                ),

                WalletTransactionType::Transfer => $this->handleTransfer($wallet, $user, $amount, $linkedCounts),

                WalletTransactionType::FtthBill => $this->handleFtthBill($wallet, $user, $linkedCounts),

                WalletTransactionType::WifiPackage => $this->handleWifiPackage($wallet, $user, $linkedCounts),

                WalletTransactionType::Adjustment => $this->handleAdjustment($wallet, $user, $amount),

                default => null,
            };
        }
    }

    /**
     * -------- Topup: always a Credit. Balance only moves if Completed. --------
     */
    private function createTopup(
        Wallet $wallet,
        User $user,
        int $amount,
        WalletTransactionStatus $status,
    ): WalletTransaction {
        $transaction = $this->makeTransaction($wallet, $user, WalletTransactionType::Topup, $status, $amount);

        if ($status === WalletTransactionStatus::Completed) {
            $this->applyEntry($wallet, $transaction, WalletEntryType::Credit, $amount);
        }

        return $transaction;
    }

    /**
     * -------- Transfer: a Debit on this wallet, mirrored as a Credit on the other wallet. --------
     */
    private function handleTransfer(Wallet $wallet, User $user, int $amount, array &$linkedCounts): void
    {
        $otherWallet = Wallet::query()->where('id', '!=', $wallet->id)->inRandomOrder()->first();

        // No counterpart wallet exists yet — fall back to a Topup instead of faking a transfer.
        if (!$otherWallet) {
            $this->createTopup(
                $wallet,
                $user,
                fake()->randomElement(self::TOPUP_AMOUNTS),
                WalletTransactionStatus::Completed,
            );

            return;
        }

        $status = $this->randomStatus();
        $transaction = $this->makeTransaction($wallet, $user, WalletTransactionType::Transfer, $status, $amount);

        $linkedCounts[WalletTransactionType::Transfer->value]++;

        WalletTransfer::query()->create([
            'wallet_transaction_id' => $transaction->id,
            'from_wallet_id' => $wallet->id,
            'to_wallet_id' => $otherWallet->id,
            'amount' => $amount,
            'note' => 'Sample wallet transfer',
        ]);

        if ($status !== WalletTransactionStatus::Completed) {
            // Pending/Processing/Failed transfers never move money.
            return;
        }

        // Can't send more than the wallet actually has.
        $sendAmount = min($amount, $wallet->balance);

        if ($sendAmount <= 0) {
            // Nothing to send — treat the attempt as failed instead of debiting a phantom amount.
            $transaction->update(['status' => WalletTransactionStatus::Failed]);

            return;
        }

        $this->applyEntry($wallet, $transaction, WalletEntryType::Debit, $sendAmount);

        // Mirror transaction on the receiving wallet so its ledger also balances.
        // Attributed to the receiver (not the sender) since it's their wallet being credited,
        // and actor_type is System because the receiver didn't initiate this themselves.
        $receiver = $otherWallet->user ?? $user;
        $incoming = $this->makeTransaction(
            $otherWallet,
            $receiver,
            WalletTransactionType::Transfer,
            WalletTransactionStatus::Completed,
            $sendAmount,
        );
        $incoming->update(['actor_type' => WalletActorType::System]);
        $this->applyEntry($otherWallet, $incoming, WalletEntryType::Credit, $sendAmount);
    }

    /**
     * -------- FTTH Bill: a Debit tied 1:1 to a BillPayment. --------
     * If the downstream bill payment fails, the wallet is auto-refunded.
     */
    private function handleFtthBill(Wallet $wallet, User $user, array &$linkedCounts): void
    {
        $account =
            BroadbandAccount::query()->where('user_id', $user->id)->inRandomOrder()->first() ??
            BroadbandAccount::query()->inRandomOrder()->first();

        $package = Package::query()->whereIn('network_id', self::FTTH_NETWORK_IDS)->inRandomOrder()->first();

        if (!$account || !$package) {
            $this->createTopup(
                $wallet,
                $user,
                fake()->randomElement(self::TOPUP_AMOUNTS),
                WalletTransactionStatus::Completed,
            );

            return;
        }

        $price = (int) round((float) $package->price);
        $debitAmount = min($price, $wallet->balance);

        if ($debitAmount <= 0) {
            // Not enough balance yet — simulate the user topping up before the bill would go through.
            $this->createTopup(
                $wallet,
                $user,
                fake()->randomElement(self::TOPUP_AMOUNTS),
                WalletTransactionStatus::Completed,
            );

            return;
        }

        $billOutcome = fake()->randomElement([
            BillPaymentStatus::Processing,
            BillPaymentStatus::Completed,
            BillPaymentStatus::Failed,
        ]);

        $linkedCounts[WalletTransactionType::FtthBill->value]++;

        if ($billOutcome === BillPaymentStatus::Processing) {
            // Payment hasn't actually settled yet — no wallet entry, no money moved.
            $transaction = $this->makeTransaction(
                $wallet,
                $user,
                WalletTransactionType::FtthBill,
                WalletTransactionStatus::Processing,
                $price,
            );
            $this->createBillPayment($transaction, $account, BillPaymentStatus::Processing);

            return;
        }

        // Both Completed and Failed bills mean the wallet was already charged —
        // the wallet transaction itself is Completed either way.
        $transaction = $this->makeTransaction(
            $wallet,
            $user,
            WalletTransactionType::FtthBill,
            WalletTransactionStatus::Completed,
            $debitAmount,
        );
        $this->applyEntry($wallet, $transaction, WalletEntryType::Debit, $debitAmount);
        $this->createBillPayment($transaction, $account, $billOutcome);

        if ($billOutcome === BillPaymentStatus::Failed) {
            $this->refund($wallet, $user, $debitAmount, $transaction);
        }
    }

    /**
     * -------- WiFi Package: a Debit tied 1:1 to a PackageOrder. --------
     * Completed orders activate a CustomerPackage; failed orders are auto-refunded.
     */
    private function handleWifiPackage(Wallet $wallet, User $user, array &$linkedCounts): void
    {
        $package = Package::query()->where('network_id', self::WIFI_NETWORK_ID)->inRandomOrder()->first();

        if (!$package) {
            $this->createTopup(
                $wallet,
                $user,
                fake()->randomElement(self::TOPUP_AMOUNTS),
                WalletTransactionStatus::Completed,
            );

            return;
        }

        $price = (int) round((float) $package->price);
        $debitAmount = min($price, $wallet->balance);

        if ($debitAmount <= 0) {
            // Not enough balance yet — simulate the user topping up before the purchase would go through.
            $this->createTopup(
                $wallet,
                $user,
                fake()->randomElement(self::TOPUP_AMOUNTS),
                WalletTransactionStatus::Completed,
            );

            return;
        }

        $orderOutcome = fake()->randomElement([
            PackageOrderStatus::Processing,
            PackageOrderStatus::Completed,
            PackageOrderStatus::Failed,
        ]);

        $linkedCounts[WalletTransactionType::WifiPackage->value]++;

        if ($orderOutcome === PackageOrderStatus::Processing) {
            $transaction = $this->makeTransaction(
                $wallet,
                $user,
                WalletTransactionType::WifiPackage,
                WalletTransactionStatus::Processing,
                $price,
            );
            $this->createPackageOrder($user, $package, $transaction, PackageOrderStatus::Processing);

            return;
        }

        $transaction = $this->makeTransaction(
            $wallet,
            $user,
            WalletTransactionType::WifiPackage,
            WalletTransactionStatus::Completed,
            $debitAmount,
        );
        $this->applyEntry($wallet, $transaction, WalletEntryType::Debit, $debitAmount);
        $packageOrder = $this->createPackageOrder($user, $package, $transaction, $orderOutcome);

        if ($orderOutcome === PackageOrderStatus::Completed) {
            CustomerPackage::query()->create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'package_order_id' => $packageOrder->id,
                'broadband_account_id' => BroadbandAccount::query()->where('user_id', $user->id)->value('id'),
                'start_date' => now()->subDays(7),
                'expiry_date' => now()->addDays(30),
                'auto_renew' => false,
                'status' => CustomerPackageStatus::Active,
            ]);
        } else {
            $this->refund($wallet, $user, $debitAmount, $transaction);
        }
    }

    /**
     * -------- Adjustment: admin correction, can go either way, always Completed. --------
     */
    private function handleAdjustment(Wallet $wallet, User $user, int $amount): void
    {
        $entryType = fake()->randomElement([WalletEntryType::Credit, WalletEntryType::Debit]);

        if ($entryType === WalletEntryType::Debit) {
            $amount = min($amount, $wallet->balance);

            if ($amount <= 0) {
                $entryType = WalletEntryType::Credit;
                $amount = fake()->numberBetween(100, 1000);
            }
        }

        $transaction = $this->makeTransaction(
            $wallet,
            $user,
            WalletTransactionType::Adjustment,
            WalletTransactionStatus::Completed,
            $amount,
        );
        $transaction->update(['actor_type' => WalletActorType::Admin]);
        $this->applyEntry($wallet, $transaction, $entryType, $amount);
    }

    /**
     * -------- Refund: system-generated Credit reversing a failed debit. --------
     */
    private function refund(Wallet $wallet, User $user, int $amount, WalletTransaction $reversedTransaction): void
    {
        $transaction = $this->makeTransaction(
            $wallet,
            $user,
            WalletTransactionType::Refund,
            WalletTransactionStatus::Completed,
            $amount,
            $reversedTransaction->id,
        );
        $transaction->update(['actor_type' => WalletActorType::System]);
        $this->applyEntry($wallet, $transaction, WalletEntryType::Credit, $amount);
    }

    /**
     * -------- Shared helpers --------
     */
    private function makeTransaction(
        Wallet $wallet,
        User $user,
        WalletTransactionType $type,
        WalletTransactionStatus $status,
        int $amount,
        ?int $reversalOf = null,
    ): WalletTransaction {
        $this->sequence++;

        return WalletTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'transaction_no' =>
                now()->format('YmdHis') . $wallet->id . str_pad((string) $this->sequence, 5, '0', STR_PAD_LEFT),
            'type' => $type,
            'status' => $status,
            'amount' => $amount,
            'idempotency_key' => fake()->unique()->uuid(),
            'reversal_of' => $reversalOf,
            'actor_type' => fake()->randomElement([
                WalletActorType::User,
                WalletActorType::Admin,
                WalletActorType::System,
            ]),
            'actor_id' => $user->id,
        ]);
    }

    /**
     * Mutates the wallet balance/version and writes the matching before/after ledger entry.
     * Debits are clamped to the current balance so it can never go negative.
     */
    private function applyEntry(
        Wallet $wallet,
        WalletTransaction $transaction,
        WalletEntryType $entryType,
        int $amount,
    ): WalletEntry {
        $before = $wallet->balance;

        if ($entryType === WalletEntryType::Debit) {
            $amount = min($amount, $before);
        }

        $after = $entryType === WalletEntryType::Credit ? $before + $amount : $before - $amount;

        $wallet->incrementVersion();
        $wallet->balance = $after;
        $wallet->save();

        return WalletEntry::query()->create([
            'wallet_transaction_id' => $transaction->id,
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'type' => $entryType,
        ]);
    }

    private function createBillPayment(
        WalletTransaction $transaction,
        BroadbandAccount $account,
        BillPaymentStatus $status,
    ): BillPayment {
        return BillPayment::query()->create([
            'wallet_transaction_id' => $transaction->id,
            'broadband_account_id' => $account->id,
            'status' => $status,
            'external_bill_ref' => 'BILL-' . fake()->numerify('####'),
            'external_payment_ref' => 'PAY-' . fake()->numerify('####'),
        ]);
    }

    private function createPackageOrder(
        User $user,
        Package $package,
        WalletTransaction $transaction,
        PackageOrderStatus $status,
    ): PackageOrder {
        return PackageOrder::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'wallet_transaction_id' => $transaction->id,
            'status' => $status,
            'snapshot' => [
                'package_id' => $package->id,
                'price' => $package->price,
            ],
        ]);
    }

    /**
     * Picks a transaction type weighted by how often it'd realistically occur — people pay
     * bills and buy packages far more often than they redeem a scratch card or get an
     * admin adjustment, so Topup/Adjustment should be the rare cases, not the common ones.
     */
    private function pickWeightedType(Collection $available): WalletTransactionType
    {
        $weight = fn(WalletTransactionType $type): int => match ($type) {
            WalletTransactionType::FtthBill => 4,
            WalletTransactionType::WifiPackage => 4,
            WalletTransactionType::Transfer => 3,
            WalletTransactionType::Topup => 2,
            WalletTransactionType::Adjustment => 1,
            default => 1,
        };

        $pool = $available->flatMap(fn($type) => array_fill(0, $weight($type), $type));

        return $pool[array_rand($pool->all())];
    }

    private function randomStatus(): WalletTransactionStatus
    {
        // Weighted so most transactions actually complete, matching real traffic.
        return fake()->randomElement([
            WalletTransactionStatus::Completed,
            WalletTransactionStatus::Completed,
            WalletTransactionStatus::Completed,
            WalletTransactionStatus::Pending,
            WalletTransactionStatus::Processing,
            WalletTransactionStatus::Failed,
        ]);
    }

    /**
     * A scratch-card topup is a code redemption, not a gateway payment — there's no external
     * party to wait on, so it resolves immediately: either the code was valid (Completed) or
     * it wasn't (Failed, e.g. invalid/already-used/expired card). No Pending/Processing.
     */
    private function topupStatus(): WalletTransactionStatus
    {
        return fake()->randomElement([
            WalletTransactionStatus::Completed,
            WalletTransactionStatus::Completed,
            WalletTransactionStatus::Completed,
            WalletTransactionStatus::Completed,
            WalletTransactionStatus::Completed,
            WalletTransactionStatus::Completed,
            WalletTransactionStatus::Completed,
            WalletTransactionStatus::Completed,
            WalletTransactionStatus::Completed,
            WalletTransactionStatus::Failed,
        ]);
    }
}
