<?php

namespace Database\Seeders;

use App\Enums\ChangePlanStatus;
use App\Enums\CustomerPackageStatus;
use App\Enums\InvoiceStatus;
use App\Enums\NewsStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\Area;
use App\Models\Banner;
use App\Models\BroadbandAccount;
use App\Models\Category;
use App\Models\ChangePlanRequest;
use App\Models\Contact;
use App\Models\CpeDevice;
use App\Models\CustomerPackage;
use App\Models\Gallery;
use App\Models\InstallationApplication;
use App\Models\Invoice;
use App\Models\News;
use App\Models\NotificationCustom;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\RelocationRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\AppPermissions;
use Database\Seeders\AreaSeeder;
use Database\Seeders\PackageSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admins = $this->seedAdmins();
        $areas = $this->seedAreas();
        $packages = $this->seedPackages();
        $users = $this->seedCustomers($packages);
        $this->seedServiceRequests($users, $areas, $packages);
        $this->seedFailureReports();
        $this->seedBilling($users);
        $this->seedNotifications();
        $this->seedBanners();
        $this->seedCms();
        $this->seedPermissions($admins);
        $this->seedAnnouncements();

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

    private function seedFailureReports(): void
    {
        (new FailureReportSeeder())->run();
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
        return User::factory()
            ->count(20)
            ->create()
            ->each(function (User $user, int $index) use ($packages): void {
                if ($index < 3) {
                    $user->update(['status' => UserStatus::Suspended]);
                }

                $package = $packages->random();

                $account = BroadbandAccount::factory()->create([
                    'user_id' => $user->id,
                    'customer_name' => $user->name,
                    'current_package_id' => $package->id,
                ]);

                CustomerPackage::factory()->create([
                    'user_id' => $user->id,
                    'broadband_account_id' => $account->id,
                    'package_id' => $package->id,
                    'start_date' => now()->subDays(10),
                    'expiry_date' => now()->addDays($package->validity_days - 10),
                    'status' => CustomerPackageStatus::Active,
                ]);

                if ($index % 4 === 0) {
                    CustomerPackage::factory()
                        ->expired()
                        ->create([
                            'user_id' => $user->id,
                            'broadband_account_id' => $account->id,
                            'package_id' => $packages->random()->id,
                        ]);
                }

                $wallet = Wallet::factory()->create([
                    'user_id' => $user->id,
                    'balance_mmk' => fake()->randomElement([0, 5000, 15000, 42000]),
                ]);

                WalletTransaction::factory()
                    ->count(3)
                    ->create([
                        'wallet_id' => $wallet->id,
                        'type' => fake()->randomElement(WalletTransactionType::cases()),
                    ]);

                CpeDevice::factory()->create([
                    'broadband_account_id' => $account->id,
                ]);

                $user
                    ->forceFill([
                        'created_at' => now()->subDays(fake()->numberBetween(0, 29)),
                    ])
                    ->save();
            })
            ->tap(function () use ($packages): void {
                BroadbandAccount::factory()
                    ->unbound()
                    ->count(3)
                    ->create([
                        'current_package_id' => $packages->random()->id,
                    ]);
            });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $users
     * @param  Collection<int, Area>  $areas
     * @param  Collection<int, Package>  $packages
     */
    private function seedServiceRequests($users, $areas, $packages): void
    {
        $sample = $users->take(8)->values();

        foreach ([ReviewStatus::UnderReview, ReviewStatus::Approved, ReviewStatus::Rejected] as $i => $status) {
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
            'broadband_account_id' => $relocUser->broadbandAccounts()->first()->id,
            'status' => ReviewStatus::UnderReview,
        ]);
        RelocationRequest::factory()->create([
            'user_id' => $sample[7]->id,
            'broadband_account_id' => $sample[7]->broadbandAccounts()->first()->id,
            'status' => ReviewStatus::Approved,
        ]);

        $planUser = $sample[0];
        $account = $planUser->broadbandAccounts()->first();
        $current = $account->current_package_id;
        $new = $packages->first(fn(Package $package) => $package->id !== $current) ?? $packages->last();

        ChangePlanRequest::factory()->create([
            'user_id' => $planUser->id,
            'broadband_account_id' => $account->id,
            'current_package_id' => $current,
            'new_package_id' => $new->id,
            'status' => ChangePlanStatus::UnderReview,
        ]);
        ChangePlanRequest::factory()->create([
            'user_id' => $sample[1]->id,
            'broadband_account_id' => $sample[1]->broadbandAccounts()->first()->id,
            'current_package_id' => $sample[1]->broadbandAccounts()->first()->current_package_id,
            'new_package_id' => $new->id,
            'status' => ChangePlanStatus::Approved,
        ]);
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
                $account = $user->broadbandAccounts()->first();

                if (!$account) {
                    return;
                }

                $paidAt = now()->subDays($index * 2);
                $amount = fake()->randomElement([15000, 25000, 35000, 45000]);

                $invoice = Invoice::factory()->create([
                    'broadband_account_id' => $account->id,
                    'amount' => $amount,
                    'status' => InvoiceStatus::Paid,
                    'due_date' => $paidAt->copy()->addDays(7),
                ]);

                Payment::factory()->create([
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'status' => PaymentStatus::Paid,
                    'paid_at' => $paidAt,
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
            'image_url_en' => 'cms/banners/mlQcLNgXn10179i32Nz65TxxXEkRNrwwKyI5z2Xy.png',
            'image_url_zh' => 'cms/banners/mlQcLNgXn10179i32Nz65TxxXEkRNrwwKyI5z2Xy.png',
            'image_url_my' => 'cms/banners/mlQcLNgXn10179i32Nz65TxxXEkRNrwwKyI5z2Xy.png',
            'sort_order' => 1,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'cms/banners/mlQcLNgXn10179i32Nz65TxxXEkRNrwwKyI5z2Xy.png',
            'image_url_zh' => 'cms/banners/mlQcLNgXn10179i32Nz65TxxXEkRNrwwKyI5z2Xy.png',
            'image_url_my' => 'cms/banners/mlQcLNgXn10179i32Nz65TxxXEkRNrwwKyI5z2Xy.png',
            'sort_order' => 2,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'cms/banners/mlQcLNgXn10179i32Nz65TxxXEkRNrwwKyI5z2Xy.png',
            'image_url_zh' => 'cms/banners/mlQcLNgXn10179i32Nz65TxxXEkRNrwwKyI5z2Xy.png',
            'image_url_my' => 'cms/banners/mlQcLNgXn10179i32Nz65TxxXEkRNrwwKyI5z2Xy.png',
            'sort_order' => 3,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'cms/banners/mlQcLNgXn10179i32Nz65TxxXEkRNrwwKyI5z2Xy.png',
            'image_url_zh' => 'cms/banners/mlQcLNgXn10179i32Nz65TxxXEkRNrwwKyI5z2Xy.png',
            'image_url_my' => 'cms/banners/mlQcLNgXn10179i32Nz65TxxXEkRNrwwKyI5z2Xy.png',
            'sort_order' => 4,
        ]);
    }

    private function seedCms(): void
    {
        $categories = Category::factory()->createMany([
            [
                'name_en' => 'Promotions',
                'name_zh' => '促销',
                'name_my' => 'ပရိုမိုးရှင်းများ',
                'slug' => 'promotions',
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
        ]);

        News::factory()
            ->count(15)
            ->state(
                fn() => [
                    'category_id' => $categories->random()->id,
                    'status' => NewsStatus::Published,
                ],
            )
            ->create();

        Promotion::factory()->count(10)->create();

        Gallery::factory()->count(5)->create();

        Contact::factory()->createMany([
            [
                'contact_point' => '+959123456789',
            ],
            [
                'contact_point' => '+959987654321',
            ],
            [
                'contact_point' => '+959456789123',
            ],
            [
                'contact_point' => 'support@cg-net.test',
            ],
            [
                'contact_point' => 'No. 123, Mong La, Shan State, Myanmar',
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
