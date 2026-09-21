<?php

namespace Database\Seeders;

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
        $users = User::query()->with('wallet')->get();

        foreach ($users as $user) {
            $wallet =
                $user->wallet ??
                Wallet::factory()->create([
                    'user_id' => $user->id,
                    'status' => WalletStatus::Active,
                    'balance' => 0,
                    'version' => 1,
                ]);

            $this->seedWalletTransactions($wallet, $user);
        }
    }

    private function seedWalletTransactions(Wallet $wallet, User $user): void
    {
        $types = [
            WalletTransactionType::Topup,
            WalletTransactionType::Transfer,
            WalletTransactionType::FtthBill,
            WalletTransactionType::WifiPackage,
            WalletTransactionType::Refund,
            WalletTransactionType::Adjustment,
        ];

        foreach (range(1, 3) as $index) {
            $type = $types[array_rand($types)];
            $amount = fake()->numberBetween(1000, 30000);

            $transaction = WalletTransaction::factory()->create([
                'wallet_id' => $wallet->id,
                'transaction_no' => 'TXN-' . strtoupper(fake()->bothify('???-####')),
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
            $after =
                $type === WalletTransactionType::Refund || $type === WalletTransactionType::Adjustment
                    ? $before + $amount
                    : max(0, $before + $amount);

            $wallet->incrementVersion();
            $wallet->balance = $after;
            $wallet->save();

            WalletEntry::query()->create([
                'wallet_transaction_id' => $transaction->id,
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'type' => fake()->randomElement([WalletEntryType::Credit, WalletEntryType::Debit]),
            ]);

            if ($index % 2 === 0) {
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

            if ($index % 3 === 0) {
                $account = BroadbandAccount::query()->inRandomOrder()->first();

                if ($account) {
                    BillPayment::query()->create([
                        'wallet_transaction_id' => $transaction->id,
                        'broadband_account_id' => $account->id,
                        'status' => fake()->randomElement([
                            \App\Enums\BillPaymentStatus::Processing,
                            \App\Enums\BillPaymentStatus::Completed,
                            \App\Enums\BillPaymentStatus::Failed,
                        ]),
                        'external_bill_ref' => 'BILL-' . fake()->numerify('####'),
                        'external_payment_ref' => 'PAY-' . fake()->numerify('####'),
                    ]);
                }
            }

            if ($index % 2 !== 0) {
                $package = \App\Models\Package::query()->inRandomOrder()->first();

                if ($package) {
                    $packageOrder = PackageOrder::query()->create([
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'wallet_transaction_id' => $transaction->id,
                        'status' => fake()->randomElement([
                            \App\Enums\PackageOrderStatus::Processing,
                            \App\Enums\PackageOrderStatus::Completed,
                            \App\Enums\PackageOrderStatus::Failed,
                        ]),
                        'snapshot' => [
                            'package_id' => $package->id,
                            'price' => $package->price,
                        ],
                    ]);

                    if ($packageOrder->status === \App\Enums\PackageOrderStatus::Completed) {
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
                            'status' => \App\Enums\CustomerPackageStatus::Active,
                        ]);
                    }
                }
            }
        }
    }
}
