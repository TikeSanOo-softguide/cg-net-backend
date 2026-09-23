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
use App\Models\PackageOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
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
        $types = [
            WalletTransactionType::Topup,
            WalletTransactionType::Transfer,
            WalletTransactionType::FtthBill,
            WalletTransactionType::WifiPackage,
            WalletTransactionType::Refund,
            WalletTransactionType::Adjustment,
        ];

        // Cap related records to a maximum of 10 per user for balance and neatness
        $indices = range(1, $count);
        $transferIndices = collect($indices)->random(min(10, $count))->toArray();
        $billIndices = collect($indices)->random(min(10, $count))->toArray();
        $packageIndices = collect($indices)->random(min(10, $count))->toArray();

        for ($index = 1; $index <= $count; $index++) {
            $type = $types[array_rand($types)];
            $amount = fake()->numberBetween(100, 5000);

            $transaction = WalletTransaction::factory()->create([
                'wallet_id' => $wallet->id,
                'transaction_no' => now()->format('YmdHis') . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                'type' => $type,
                'status' => fake()->randomElement([
                    WalletTransactionStatus::Pending,
                    WalletTransactionStatus::Processing,
                    WalletTransactionStatus::Completed,
                    WalletTransactionStatus::Failed,
                ]),
                'amount' => $amount,
                'idempotency_key' => fake()->unique()->uuid(),
                'actor_type' => fake()->randomElement([
                    WalletActorType::User,
                    WalletActorType::Admin,
                    WalletActorType::System,
                ]),
                'actor_id' => $user->id,
            ]);

            $before = $wallet->balance;
            $entryType = fake()->randomElement([WalletEntryType::Credit, WalletEntryType::Debit]);

            $after = $entryType === WalletEntryType::Credit ? $before + $amount : max(0, $before - $amount);

            $wallet->incrementVersion();
            $wallet->balance = $after;
            $wallet->save();

            WalletEntry::query()->create([
                'wallet_transaction_id' => $transaction->id,
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'type' => $entryType,
            ]);

            // Wallet Transfers (capped to ~10)
            if (in_array($index, $transferIndices)) {
                $otherWallet = Wallet::query()->where('id', '!=', $wallet->id)->inRandomOrder()->first();

                if ($otherWallet) {
                    WalletTransfer::query()->create([
                        'wallet_transaction_id' => $transaction->id,
                        'from_wallet_id' => $wallet->id,
                        'to_wallet_id' => $otherWallet->id,
                        'amount' => $amount,
                        'note' => 'Sample wallet transfer',
                    ]);
                }
            }

            // Bill Payments (capped to ~10)
            if (in_array($index, $billIndices)) {
                $account = BroadbandAccount::query()->inRandomOrder()->first();

                if ($account) {
                    BillPayment::query()->create([
                        'wallet_transaction_id' => $transaction->id,
                        'broadband_account_id' => $account->id,
                        'status' => fake()->randomElement([
                            BillPaymentStatus::Processing,
                            BillPaymentStatus::Completed,
                            BillPaymentStatus::Failed,
                        ]),
                        'external_bill_ref' => 'BILL-' . fake()->numerify('####'),
                        'external_payment_ref' => 'PAY-' . fake()->numerify('####'),
                    ]);
                }
            }

            // Package Orders & Customer Packages (capped to ~10, 1:1 match for completed orders)
            if (in_array($index, $packageIndices)) {
                $package = \App\Models\Package::query()->inRandomOrder()->first();

                if ($package) {
                    $orderStatus = fake()->randomElement([
                        PackageOrderStatus::Processing,
                        PackageOrderStatus::Completed,
                        PackageOrderStatus::Failed,
                    ]);

                    $packageOrder = PackageOrder::query()->create([
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'wallet_transaction_id' => $transaction->id,
                        'status' => $orderStatus,
                        'snapshot' => [
                            'package_id' => $package->id,
                            'price' => $package->price,
                        ],
                    ]);

                    if ($packageOrder->status === PackageOrderStatus::Completed) {
                        \App\Models\CustomerPackage::query()->create([
                            'user_id' => $user->id,
                            'package_id' => $package->id,
                            'package_order_id' => $packageOrder->id,
                            'broadband_account_id' => BroadbandAccount::query()
                                ->where('user_id', $user->id)
                                ->value('id'),
                            'start_date' => now()->subDays(7),
                            'expiry_date' => now()->addDays(30),
                            'auto_renew' => false,
                            'status' => CustomerPackageStatus::Active,
                        ]);
                    }
                }
            }
        }
    }
}
