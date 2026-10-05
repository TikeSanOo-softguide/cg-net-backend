<?php

namespace Database\Seeders;

use App\Enums\BillPaymentStatus;
use App\Enums\ChangePlanStatus;
use App\Enums\CustomerPackageStatus;
use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\RequestStatus;
use App\Enums\UserStatus;
use App\Enums\WalletActorType;
use App\Enums\WalletStatus;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\Area;
use App\Models\Banner;
use App\Models\BillPayment;
use App\Models\Category;
use App\Models\ChangePasswordRequest;
use App\Models\ChangePlanRequest;
use App\Models\Contact;
use App\Models\CpeDevice;
use App\Models\CustomerPackage;
use App\Models\Gallery;
use App\Models\InstallationApplication;
use App\Models\LedgerTransaction;
use App\Models\NotificationCustom;
use App\Models\Package;
use App\Models\RelocationRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use App\Support\AppPermissions;
use Database\Factories\Support\MyanmarFake;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        echo "Database seeder started\n";

        $admins = $this->seedAdmins();
        $areas = $this->seedAreas();
        $packages = $this->seedPackages();
        app(LedgerPoster::class)->ensureSystemAccounts();
        $users = $this->seedCustomers($packages);
        $this->seedOffices();
        $this->seedServiceRequests($users, $areas, $packages);
        $this->seedFailureReports();
        $this->seedBilling($users);
        $this->seedNotifications();
        $this->seedBanners();
        $this->seedCms();
        $this->seedPermissions($admins);
        $this->seedAnnouncements();
        $this->seedTopUpCards();
        $this->seedWalletSystem();
        $this->seedChatConversations();
        $this->seedAppVersions();

        Setting::factory()->create([
            'key' => 'support_hotline',
            'value' => '+959123456789',
        ]);

        unset($admins);
    }

    /**
     * @return Collection<int, Admin>
     */
    private function seedAdmins()
    {
        return collect([
            ['username' => 'Super Admin'],
            ['username' => 'Staff Officer'],
            ['username' => 'Support Agent'],
        ])->map(function (array $admin) {
            $legacy = [
                'Super Admin' => 'admin',
                'Staff Officer' => 'staff',
                'Support Agent' => 'support',
            ][$admin['username']];

            $existing = Admin::query()
                ->whereIn('username', [$admin['username'], $legacy])
                ->first();

            if ($existing) {
                $existing->update(['username' => $admin['username']]);

                return $existing;
            }

            return Admin::factory()->create($admin);
        });
    }

    /**
     * @return Collection<int, Area>
     */
    private function seedAreas()
    {
        (new AreaSeeder())->run();

        return Area::all();
    }

    private function seedTopUpCards(): void
    {
        (new TopUpCardSeeder())->run();
    }

    private function seedWalletSystem(): void
    {
        (new LedgerAccountSeeder())->run();
        (new WalletSeeder())->run();
    }

    private function seedFailureReports(): void
    {
        (new FailureReportSeeder())->run();
    }

    private function seedOffices(): void
    {
        (new OfficeSeeder())->run();
    }

    private function seedChatConversations(): void
    {
        (new ChatConversationSeeder())->run();
    }

    private function seedAppVersions(): void
    {
        (new AppVersionSeeder())->run();
    }

    private function seedAnnouncements(): void
    {
        Announcement::factory()->active()->create();
        Announcement::factory()->inactive()->create();
        Announcement::factory()->upcoming()->create();
        Announcement::factory()->expired()->create();
    }

    /**
     * @return Collection<int, Package>
     */
    private function seedPackages()
    {
        (new PackageSeeder())->run();

        return Package::all();
    }

    /**
     * @param  Collection<int, Package>  $packages
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    private function seedCustomers($packages)
    {
        $showcaseCustomers = $this->showcaseCustomers();

        $users = collect($showcaseCustomers)
            ->map(fn (array $row) => User::factory()->create([
                'phone' => MyanmarFake::phone('mm'),
                'name' => $row['name'],
                'status' => $row['status'],
                'broadband_account_number' => $row['account_number'],
            ]))
            ->concat(User::factory()->count(10)->create())
            ->values();

        return $users->each(function (User $user, int $index) use ($packages, $showcaseCustomers): void {
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
                CustomerPackage::factory()
                    ->expired()
                    ->create([
                        'user_id' => $user->id,
                        'package_id' => $packages->random()->id,
                    ]);
            }

            $wallet = Wallet::factory()->create([
                'user_id' => $user->id,
                'balance' => fake()->randomElement([0, 5000, 15000, 42000]),
            ]);

            app(LedgerPoster::class)->ensureCustomerLiabilityAccount($wallet);

            // Seeded opening balances are represented as an adjustment credit so the ledger reconciles.
            if ((int) $wallet->balance > 0) {
                $opening = (int) $wallet->balance;
                $wallet->update(['balance' => 0]);
                app(LedgerPoster::class)->creditWallet(
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

            LedgerTransaction::factory()
                ->count(2)
                ->create([
                    'wallet_id' => $wallet->id,
                    'type' => fake()->randomElement([
                        LedgerTransactionType::Topup,
                        LedgerTransactionType::FtthBill,
                        LedgerTransactionType::Refund,
                    ]),
                    'status' => LedgerTransactionStatus::Pending,
                    'amount' => fake()->numberBetween(1000, 5000),
                ]);

            CpeDevice::factory()->create([
                'user_id' => $user->id,
            ]);

            $user
                ->forceFill([
                    'created_at' => now()->subDays(fake()->numberBetween(0, 29)),
                ])
                ->save();
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

    /**
     * @return list<array{account_number: string, name: string, status: UserStatus, customer_package_id: int}>
     */
    private function showcaseCustomers(): array
    {
        return [
            [
                'account_number' => 'CG0000001',
                'name' => 'Robert Anderson',
                'status' => UserStatus::Active,
                'customer_package_id' => 78,
            ],
            [
                'account_number' => 'CG0000002',
                'name' => 'Patricia Martinez',
                'status' => UserStatus::Active,
                'customer_package_id' => 77,
            ],
            [
                'account_number' => 'CG0000003',
                'name' => 'Aung Aung',
                'status' => UserStatus::Suspended,
                'customer_package_id' => 4,
            ],
            [
                'account_number' => 'CG0000004',
                'name' => 'Su Su',
                'status' => UserStatus::Suspended,
                'customer_package_id' => 5,
            ],
            [
                'account_number' => 'CG0000005',
                'name' => 'Kyaw Kyaw',
                'status' => UserStatus::Active,
                'customer_package_id' => 7,
            ],
            [
                'account_number' => 'CG0000006',
                'name' => 'Hla Hla',
                'status' => UserStatus::Active,
                'customer_package_id' => 8,
            ],
            [
                'account_number' => 'CG0000007',
                'name' => 'Ko Ko',
                'status' => UserStatus::Suspended,
                'customer_package_id' => 9,
            ],
            [
                'account_number' => 'CG0000008',
                'name' => 'Su Mon',
                'status' => UserStatus::Active,
                'customer_package_id' => 10,
            ],
            [
                'account_number' => 'CG0000009',
                'name' => 'Min Min',
                'status' => UserStatus::Suspended,
                'customer_package_id' => 12,
            ],
            [
                'account_number' => 'CG0000010',
                'name' => 'Thiri',
                'status' => UserStatus::Active,
                'customer_package_id' => 13,
            ],
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $users
     * @param  Collection<int, Area>  $areas
     * @param  Collection<int, Package>  $packages
     */
    private function seedServiceRequests($users, $areas, $packages): void
    {
        $sample = $users->take(8)->values();
        $admin = Admin::query()->first();

        foreach ([RequestStatus::UnderReview, RequestStatus::Approved, RequestStatus::Cancelled] as $i => $status) {
            $user = $sample[$i];

            InstallationApplication::factory()->create([
                'user_id' => $user->id,
                'area_id' => $areas->random()->id,
                'package_id' => $packages->random()->id,
                'status' => $status,
            ]);
        }

        $relocUser = $sample[6];
        RelocationRequest::factory()->create([
            'user_id' => $relocUser->id,
            'broadband_account_number' => $relocUser->broadband_account_number,
            'status' => RequestStatus::UnderReview,
        ]);
        RelocationRequest::factory()->create([
            'user_id' => $sample[7]->id,
            'broadband_account_number' => $sample[7]->broadband_account_number,
            'status' => RequestStatus::Approved,
        ]);

        foreach ($users->take(20)->values() as $index => $user) {
            $customerPackage = $user
                ->customerPackages()
                ->where('status', CustomerPackageStatus::Active->value)
                ->first();

            if (!$customerPackage) {
                continue;
            }

            $currentPackageId = $customerPackage->package_id;
            $currentPackage = Package::query()->with('speed')->find($currentPackageId);

            $condition = $index % 3;
            $status =
                $index < 10
                    ? ChangePlanStatus::UnderReview
                    : ($index < 17
                        ? ChangePlanStatus::Approved
                        : ChangePlanStatus::Cancelled);

            $newPackage = match ($condition) {
                0 => $currentPackage?->speed
                    ? Package::query()
                        ->whereKeyNot($currentPackageId)
                        ->whereHas('speed', fn($query) => $query->where('mbps', '<', $currentPackage->speed->mbps))
                        ->first()
                    : null,
                1 => $currentPackage?->speed
                    ? Package::query()
                        ->whereKeyNot($currentPackageId)
                        ->whereHas('speed', fn($query) => $query->where('mbps', '>', $currentPackage->speed->mbps))
                        ->first()
                    : null,
                default => $currentPackage?->speed
                    ? Package::query()
                        ->whereKeyNot($currentPackageId)
                        ->where('network_id', '!=', $currentPackage->network_id)
                        ->whereHas('speed', fn($query) => $query->where('mbps', $currentPackage->speed->mbps))
                        ->first()
                    : null,
            };

            $newPackage ??= Package::query()->whereKeyNot($currentPackageId)->first();

            if (!$newPackage) {
                continue;
            }

            $adminId = $status === ChangePlanStatus::Approved ? $admin?->id : null;
            do {
                $contactPhone = MyanmarFake::phone();
            } while ($contactPhone === $user->phone);

            ChangePlanRequest::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'broadband_account_number' => $user->broadband_account_number,
                    'current_package_id' => $currentPackageId,
                    'new_package_id' => $newPackage->id,
                    'preferred_date' => now()
                        ->{$index < 2 ? 'subDays' : 'addDays'}($index + 1)
                        ->toDateString(),
                ],
                [
                    'broadband_account_number' => $user->broadband_account_number,
                    'contact_name' => $user->name,
                    'contact_phone' => $contactPhone,
                    'note' => 'Seeded change plan request.',
                    'status' => $status,
                    'admin_id' => $adminId,
                ],
            );
        }
        ChangePasswordRequest::factory()->count(10)->underReview()->create();
        ChangePasswordRequest::factory()->count(6)->approved()->create();
        ChangePasswordRequest::factory()->count(4)->cancelled()->create();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $users
     */
    private function seedBilling($users): void
    {
        $users
            ->take(12)
            ->values()
            ->each(function (User $user, int $index): void {
                if (!$user->broadband_account_number) {
                    return;
                }

                $paidAt = now()->subDays($index * 2);
                $amount = fake()->randomElement([50, 100, 250, 500]);

                $wallet = $user->wallet()->firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'balance' => 0,
                        'status' => WalletStatus::Active,
                        'version' => 1,
                    ],
                );

                app(LedgerPoster::class)->ensureCustomerLiabilityAccount($wallet);

                $poster = app(LedgerPoster::class);
                $wallet = $wallet->fresh();

                if ((int) $wallet->balance < $amount) {
                    $poster->creditWallet(
                        wallet: $wallet,
                        amount: $amount - (int) $wallet->balance,
                        contraAccount: LedgerAccountCode::CashTopup,
                        type: LedgerTransactionType::Topup,
                        status: LedgerTransactionStatus::Completed,
                        idempotencyKey: 'seed-bill-topup:' . $user->id . ':' . $index,
                        actorType: WalletActorType::System,
                        actorId: $user->id,
                    );
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

                BillPayment::query()->create([
                    'ledger_transaction_id' => $transaction->id,
                    'broadband_account_number' => $user->broadband_account_number,
                    'status' => BillPaymentStatus::Completed,
                    'external_bill_ref' => 'BILL-' . fake()->numerify('####'),
                    'external_payment_ref' => 'PAY-' . fake()->numerify('####'),
                    'external_response' => ['gateway' => 'kbzpay'],
                    'confirmed_at' => $paidAt,
                ]);
            });
    }

    private function seedNotifications(): void
    {
        NotificationCustom::factory()
            ->count(4)
            ->create(['is_read' => false, 'user_id' => null]);
        NotificationCustom::factory()
            ->count(2)
            ->create(['is_read' => true, 'user_id' => null]);
    }

    private function seedBanners(): void
    {
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/banner_en.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh.png',
            'image_url_my' => 'seeder_images/banner/banner_my.png',
            'type' => 'web_background',
            'sort_order' => 1,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/banner_en1.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh1.png',
            'image_url_my' => 'seeder_images/banner/banner_my1.png',
            'type' => 'web_background',
            'sort_order' => 2,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/banner_en2.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh2.png',
            'image_url_my' => 'seeder_images/banner/banner_my2.png',
            'type' => 'web_background',
            'sort_order' => 3,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/banner_en3.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh3.png',
            'image_url_my' => 'seeder_images/banner/banner_my3.png',
            'type' => 'web_background',
            'sort_order' => 4,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/banner_en4.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh4.png',
            'image_url_my' => 'seeder_images/banner/banner_my4.png',
            'type' => 'web_background',
            'sort_order' => 5,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/banner_en5.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh5.png',
            'image_url_my' => 'seeder_images/banner/banner_my5.png',
            'type' => 'web_background',
            'sort_order' => 6,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/banner_en6.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh6.png',
            'image_url_my' => 'seeder_images/banner/banner_my6.png',
            'type' => 'web_background',
            'sort_order' => 7,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/web_popup/popup1.png',
            'image_url_zh' => 'seeder_images/banner/web_popup/popup1.png',
            'image_url_my' => 'seeder_images/banner/web_popup/popup1.png',
            'type' => 'web_popup',
            'sort_order' => 8,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/web_popup/popup2.png',
            'image_url_zh' => 'seeder_images/banner/web_popup/popup2.png',
            'image_url_my' => 'seeder_images/banner/web_popup/popup2.png',
            'type' => 'web_popup',
            'sort_order' => 9,
        ]);
    }

    private function seedCms(): void
    {
        Category::factory()->createMany([
            [
                'name_en' => 'Announcement',
                'name_zh' => '公告',
                'name_my' => 'ကြေငြာချက်',
                'slug' => 'announcement',
            ],
            [
                'name_en' => 'Awards',
                'name_zh' => '奖项',
                'name_my' => 'ဆုများ',
                'slug' => 'awards',
            ],
            [
                'name_en' => 'Games',
                'name_zh' => '游戏',
                'name_my' => 'ဂိမ်းများ',
                'slug' => 'games',
            ],
            [
                'name_en' => 'Charity',
                'name_zh' => '慈善',
                'name_my' => 'အလှူအတန်း',
                'slug' => 'charity',
            ],
            [
                'name_en' => 'Career',
                'name_my' => 'အလုပ်အကိုင်အခွင့်အလမ်း',
                'name_zh' => '招聘',
                'slug' => 'career',
            ],
        ]);

        (new NewsSeeder())->run();

        (new ServiceSeeder())->run();

        (new PromotionSeeder())->run();

        collect([
            [
                'image_url' => 'seeder_images/gallery/thingyan.jfif',
                'label_en' => 'Thingyan Water Festival Celebration',
                'label_my' => 'သင်္ကြန်ရေသဘင်ပွဲတော် ဆင်နွှဲခြင်း',
                'label_zh' => '泼水节欢庆活动',
            ],
            [
                'image_url' => 'seeder_images/gallery/chinesenewyear.jfif',
                'label_en' => 'Traditional Chinese New Year Lion Dance',
                'label_my' => 'တရုတ်နှစ်သစ်ကူး ခြင်္သေ့အက ဖျော်ဖြေပွဲ',
                'label_zh' => '传统农历新年舞狮表演',
            ],
            [
                'image_url' => 'seeder_images/gallery/newyear.jfif',
                'label_en' => 'New Year 2026 Sparkler Celebration',
                'label_my' => '၂၀၂၆ ခုနှစ် သစ်ဆန်းနှစ်သစ်ကူး ကြိုဆိုပွဲတော်',
                'label_zh' => '2026跨年烟花棒庆祝',
            ],
            [
                'image_url' => 'seeder_images/gallery/staffparty.jfif',
                'label_en' => 'Company Staff Party & Office Event',
                'label_my' => 'ဝန်ထမ်းများ၏ ရုံးတွင်း ပျော်ပွဲရွှင်ပွဲ ပွဲတော်',
                'label_zh' => '公司员工聚会与办公室活动',
            ],
            [
                'image_url' => 'seeder_images/gallery/thadingyut.jfif',
                'label_en' => 'Thadingyut Festival of Lights & Lanterns',
                'label_my' => 'သီတင်းကျွတ် မီးထွန်းပွဲတော်နှင့် မီးပုံးပျံလွှတ်တင်ခြင်း',
                'label_zh' => '点灯节与放天灯活动',
            ],
        ])->each(fn(array $gallery) => Gallery::create($gallery));

        Contact::factory()->createMany([
            [
                'contact_point' => '📞 09 887288882',
            ],
            [
                'contact_point' => '📞 09 421823339',
            ],
            [
                'contact_point' => '📩 contact@chenguangnetwork.com',
            ],
            [
                'contact_point' => '🏠 130/A Eain Twin Hmu Street,Mae Khong Ward,Tachileik.',
            ],
            [
                'contact_point' => '🧭 Monday to Sunday: 9:00 AM - 9:00 PM',
            ],
            [
                'contact_point' => '📅 Serving you 7 days a week',
            ],
        ]);
    }

    /**
     * @param  Collection<int, Admin>  $admins
     */
    private function seedPermissions(Collection $admins): void
    {
        RolePermissionSeeder::sync();

        $admins
            ->first(fn(Admin $admin) => $admin->username === 'Super Admin')
            ?->syncRoles([AppPermissions::SuperAdmin]);

        $admins
            ->first(fn(Admin $admin) => $admin->username === 'Staff Officer')
            ?->syncRoles([AppPermissions::StaffOfficer]);

        $admins
            ->first(fn(Admin $admin) => $admin->username === 'Support Agent')
            ?->syncRoles([AppPermissions::SupportAgent]);
    }
}
