<?php

namespace Database\Seeders;

use App\Enums\CustomerPackageStatus;
use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\UserStatus;
use App\Enums\WalletActorType;
use App\Models\CustomerPackage;
use App\Models\LedgerTransaction;
use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use Database\Factories\Support\MyanmarFake;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var list<int> */
    public array $seededUserIds = [];

    /**
     * @var list<array{account_number: string, name: string, status: UserStatus, customer_package_id: int}>
     */
    private const SHOWCASE_CUSTOMERS = [
        ['account_number' => 'CG0000001', 'name' => 'Robert Anderson', 'status' => UserStatus::Active, 'customer_package_id' => 78],
        ['account_number' => 'CG0000002', 'name' => 'Patricia Martinez', 'status' => UserStatus::Active, 'customer_package_id' => 77],
        ['account_number' => 'CG0000003', 'name' => 'Aung Aung', 'status' => UserStatus::Suspended, 'customer_package_id' => 4],
        ['account_number' => 'CG0000004', 'name' => 'Su Su', 'status' => UserStatus::Suspended, 'customer_package_id' => 5],
        ['account_number' => 'CG0000005', 'name' => 'Kyaw Kyaw', 'status' => UserStatus::Active, 'customer_package_id' => 7],
        ['account_number' => 'CG0000006', 'name' => 'Hla Hla', 'status' => UserStatus::Active, 'customer_package_id' => 8],
        ['account_number' => 'CG0000007', 'name' => 'Ko Ko', 'status' => UserStatus::Suspended, 'customer_package_id' => 9],
        ['account_number' => 'CG0000008', 'name' => 'Su Mon', 'status' => UserStatus::Active, 'customer_package_id' => 10],
        ['account_number' => 'CG0000009', 'name' => 'Min Min', 'status' => UserStatus::Suspended, 'customer_package_id' => 12],
        ['account_number' => 'CG0000010', 'name' => 'Thiri', 'status' => UserStatus::Active, 'customer_package_id' => 13],
    ];

    public function run(): void
    {
        echo "Customer seeder started\n";

        app(LedgerPoster::class)->ensureSystemAccounts();
        $packages = Package::query()->get();

        if ($packages->isEmpty()) {
            throw new \RuntimeException('Run PackageSeeder before CustomerSeeder.');
        }

        $showcaseCustomers = self::SHOWCASE_CUSTOMERS;
        $users = collect($showcaseCustomers)
            ->map(fn(array $row) => User::factory()->create([
                'phone' => MyanmarFake::phone('mm'),
                'name' => $row['name'],
                'status' => $row['status'],
                'broadband_account_number' => $row['account_number'],
            ]))
            ->concat(User::factory()->count(10)->create())
            ->values();

        $this->seededUserIds = $users->map(fn(User $user) => $user->getKey())->all();

        $users->each(function (User $user, int $index) use ($packages, $showcaseCustomers): void {
            if ($index === count($showcaseCustomers)) {
                $this->syncCustomerPackageIdSequence();
            }

            if ($index >= 10 && $index < 13) {
                $user->update(['status' => UserStatus::Suspended]);
            }

            $package = $packages->random();
            $showcaseCustomer = $showcaseCustomers[$index] ?? null;
            $accountNumber = $showcaseCustomer['account_number'] ?? 'CG' . fake()->unique()->numerify('########');
            $user->update(['broadband_account_number' => $accountNumber]);

            $customerPackage = CustomerPackage::factory()->make([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'starts_at' => now()->subDays(10),
                'expires_at' => now()->addDays($package->validity_days - 10),
                'status' => CustomerPackageStatus::Active,
            ]);

            if ($showcaseCustomer) {
                $customerPackage->forceFill(['id' => $showcaseCustomer['customer_package_id']]);
            }

            $customerPackage->save();

            if ($index % 4 === 0) {
                CustomerPackage::factory()->expired()->create([
                    'user_id' => $user->id,
                    'package_id' => $packages->random()->id,
                ]);
            }

            $wallet = Wallet::factory()->create([
                'user_id' => $user->id,
                'balance' => fake()->randomElement([0, 5000, 15000, 42000]),
            ]);

            $ledger = app(LedgerPoster::class);
            $ledger->ensureCustomerLiabilityAccount($wallet);

            if ((int) $wallet->balance > 0) {
                $opening = (int) $wallet->balance;
                $wallet->update(['balance' => 0]);
                $ledger->creditWallet(
                    wallet: $wallet->fresh(),
                    amount: $opening,
                    contraAccount: LedgerAccountCode::AdjustmentExpense,
                    type: LedgerTransactionType::Adjustment,
                    status: LedgerTransactionStatus::Completed,
                    idempotencyKey: 'seed-opening:' . $wallet->id,
                    actorType: WalletActorType::System,
                    actorId: $user->id,
                );
            }

            LedgerTransaction::factory()->count(2)->create([
                'wallet_id' => $wallet->id,
                'type' => fake()->randomElement([
                    LedgerTransactionType::Topup,
                    LedgerTransactionType::FtthBill,
                    LedgerTransactionType::Refund,
                ]),
                'status' => LedgerTransactionStatus::Pending,
                'amount' => fake()->numberBetween(1000, 5000),
            ]);

            $user->forceFill([
                'created_at' => now()->subDays(fake()->numberBetween(0, 29)),
            ])->save();
        });
    }

    private function syncCustomerPackageIdSequence(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "SELECT setval(pg_get_serial_sequence('customer_packages', 'id'), COALESCE(MAX(id), 1), COUNT(*) > 0) FROM customer_packages",
        );
    }
}
