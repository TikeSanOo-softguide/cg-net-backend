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
use App\Models\Banner;
use App\Models\BroadbandAccount;
use App\Models\Category;
use App\Models\ChangePlanRequest;
use App\Models\Contact;
use App\Models\CpeDevice;
use App\Models\CustomerPackage;
use App\Models\FailureReport;
use App\Models\Gallery;
use App\Models\InstallationApplication;
use App\Models\Invoice;
use App\Models\News;
use App\Models\NotificationCustom;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Region;
use App\Models\RelocationRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\AppPermissions;
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
        // $townships = $this->seedRegions();
        $packages = $this->seedPackages();
        $users = $this->seedCustomers($packages);
        // $this->seedServiceRequests($users, $townships, $packages);
        $this->seedBilling($users);
        $this->seedNotifications();
        $this->seedBanners();
        $this->seedCms();
        $this->seedPermissions($admins);

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
     * @return Collection<int, Region>
     */
    private function seedRegions()
    {
        $tree = [
            ['Yangon', 'ရန်ကုန်', [['Bahan', 'ဗဟန်း'], ['Kamayut', 'ကမာရွတ်']]],
            ['Mandalay', 'မန္တလေး', [['Chanayethazan', 'ချမ်းအေးသာဇံ'], ['Mahaaungmye', 'မဟာအောင်မြေ']]],
            ['Shan', 'ရှမ်း', [['Taunggyi', 'တောင်ကြီး'], ['Kalaw', 'ကလော']]],
            ['Ayeyarwady', 'ဧရာဝတီ', [['Pathein', 'ပုသိမ်'], ['Hinthada', 'ဟင်္သာတ']]],
            ['Sagaing', 'စစ်ကိုင်း', [['Monywa', 'မုံရွာ'], ['Shwebo', 'ရွှေဘို']]],
        ];

        $townships = collect();

        foreach ($tree as [$en, $mm, $children]) {
            $parent = Region::factory()->create([
                'name_en' => $en,
                'name_mm' => $mm,
                'parent_id' => null,
            ]);

            foreach ($children as [$childEn, $childMm]) {
                $townships->push(
                    Region::factory()->create([
                        'name_en' => $childEn,
                        'name_mm' => $childMm,
                        'parent_id' => $parent->id,
                    ]),
                );
            }
        }

        return $townships;
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
     * @param  Collection<int, Region>  $townships
     * @param  Collection<int, Package>  $packages
     */
    private function seedServiceRequests($users, $townships, $packages): void
    {
        $sample = $users->take(8)->values();

        foreach ([ReviewStatus::UnderReview, ReviewStatus::Approved, ReviewStatus::Rejected] as $i => $status) {
            $user = $sample[$i];

            InstallationApplication::factory()->create([
                'user_id' => $user->id,
                'region_id' => $townships->random()->id,
                'plan_id' => $packages->random()->id,
                'status' => $status,
            ]);
        }

        foreach ([ReviewStatus::UnderReview, ReviewStatus::Approved, ReviewStatus::Rejected] as $i => $status) {
            $user = $sample[$i + 3];
            $account = $user->broadbandAccounts()->first();

            FailureReport::factory()->create([
                'user_id' => $user->id,
                'broadband_account_id' => $account->id,
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
            'image_url_en' => 'https://cdn.cg-net.test/banners/monsoon-promo-en.jpg',
            'sort_order' => 1,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'https://cdn.cg-net.test/banners/new-fiber-coverage-en.jpg',
            'sort_order' => 2,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'https://cdn.cg-net.test/banners/pay-with-kbzpay-en.jpg',
            'sort_order' => 3,
        ]);
    }

    private function seedCms(): void
    {
        $offers = Category::factory()->create([
            'name_en' => 'Offers',
            'name_zh' => 'Offers',
            'name_my' => 'Offers',
            'slug' => 'offers',
        ]);
        $article = News::factory()->create([
            'category_id' => $offers->id,
            'title_en' => 'New fiber coverage in Yangon',
            'slug' => 'new-fiber-coverage-yangon',
            'status' => NewsStatus::Published,
        ]);
        Promotion::factory()->create([
            'title_en' => 'Monsoon home broadband',
            'slug' => 'monsoon-home-broadband',
        ]);
        Gallery::factory()->create(['label_en' => 'Yangon office']);
        Contact::factory()->create(['contact_point' => '+959123456789']);
        Contact::factory()->create(['contact_point' => 'support@cg-net.test']);
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
