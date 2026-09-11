<?php

namespace Database\Seeders;

use App\Enums\ChangePlanStatus;
use App\Enums\CustomerPackageStatus;
use App\Enums\InvoiceStatus;
use App\Enums\NewsStatus;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\Area;
use App\Models\Banner;
use App\Models\BroadbandAccount;
use App\Models\Category;
use App\Models\ChangePasswordRequest;
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
use Database\Factories\Support\MyanmarFake;
use Database\Seeders\AreaSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\ServiceSeeder;
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
        $showcasePhones = [
            ['phone' => MyanmarFake::phone('mm'), 'name' => 'Myanmar User'],
            ['phone' => MyanmarFake::phone('th'), 'name' => 'Thailand User'],
            ['phone' => MyanmarFake::phone('cn'), 'name' => 'China User'],
        ];

        $users = collect($showcasePhones)
            ->map(fn(array $row) => User::factory()->create($row))
            ->concat(User::factory()->count(17)->create())
            ->values();

        return $users
            ->each(function (User $user, int $index) use ($packages): void {
                if ($index >= 3 && $index < 6) {
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
            'broadband_account_id' => $relocUser->broadbandAccounts()->first()->id,
            'status' => RequestStatus::UnderReview,
        ]);
        RelocationRequest::factory()->create([
            'user_id' => $sample[7]->id,
            'broadband_account_id' => $sample[7]->broadbandAccounts()->first()->id,
            'status' => RequestStatus::Approved,
        ]);

        foreach ($users->take(20)->values() as $index => $user) {
            $account = $user->broadbandAccounts()->first();

            if (!$account) {
                continue;
            }

            $currentPackageId = $account->current_package_id;
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
                    'broadband_account_id' => $account->id,
                    'current_package_id' => $currentPackageId,
                    'new_package_id' => $newPackage->id,
                    'preferred_date' => now()
                        ->{$index < 2 ? 'subDays' : 'addDays'}($index + 1)
                        ->toDateString(),
                ],
                [
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
            'image_url_en' => 'seeder_images/banner/banner_en.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh.png',
            'image_url_my' => 'seeder_images/banner/banner_my.png',
            'sort_order' => 1,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/banner_en1.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh1.png',
            'image_url_my' => 'seeder_images/banner/banner_my1.png',
            'sort_order' => 2,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/banner_en2.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh2.png',
            'image_url_my' => 'seeder_images/banner/banner_my2.png',
            'sort_order' => 3,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/banner_en3.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh3.png',
            'image_url_my' => 'seeder_images/banner/banner_my3.png',
            'sort_order' => 4,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/banner_en4.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh4.png',
            'image_url_my' => 'seeder_images/banner/banner_my4.png',
            'sort_order' => 5,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/banner_en5.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh5.png',
            'image_url_my' => 'seeder_images/banner/banner_my5.png',
            'sort_order' => 6,
        ]);
        Banner::factory()->create([
            'image_url_en' => 'seeder_images/banner/banner_en6.png',
            'image_url_zh' => 'seeder_images/banner/banner_zh6.png',
            'image_url_my' => 'seeder_images/banner/banner_my6.png',
            'sort_order' => 7,
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

        (new ServiceSeeder())->run();

        collect([
            [
                'slug' => 'thingyan-fiber-special-promo',
                'title_en' => 'Thingyan Special: 50% Off Fiber Installation',
                'title_my' => 'သင်္ကြန် အထူးအစီအစဉ် - ဖိုက်ဘာ တပ်ဆင်ခ ၅၀% လျှော့စျေး',
                'title_zh' => '泼水节特惠：光纤安装费 50% 折扣',
                'description_en' => 'Get connected this Thingyan with our exclusive fiber internet promotion. Sign up for any 1Gbps plan and get 50% off installation plus free router upgrade.',
                'description_my' => 'ယခု သင်္ကြန်ပွဲတော်တွင် ကျွန်ုပ်တို့၏ အထူးဖိုက်ဘာ အင်တာနက် ပရိုမိုးရှင်းဖြင့် ချိတ်ဆက်လိုက်ပါ။ 1Gbps အစီအစဉ် မည်သည့်ဟာကိုမဆို ရွေးချယ်ပြီး တပ်ဆင်ခ ၅၀% လျှော့စျေးနှင့် ရောက်တာ အခမဲ့ မြှင့်တင်မှုကို ရယူလိုက်ပါ။',
                'description_zh' => '通过我们的独家光纤互联网促销活动连接泼水节。注册任何 1Gbps 套餐即可享受 50% 安装折扣以及免费路由器升级。',
                'start_date' => '2026-04-01',
                'end_date' => '2026-12-30',
                'is_active' => true,
                'image_url' => 'seeder_images/promotions/thingyan_promo.png',
            ],
            [
                'slug' => 'upgrade-speed-bonus-data',
                'title_en' => 'Speed Booster: Double Your Bandwidth',
                'title_my' => 'မြန်နှုန်းမြှင့်တင်မှု - သင်၏ ဘန်းဝဒ်ကို နှစ်ဆတိုးယူပါ',
                'title_zh' => '提速计划：带宽直接翻倍',
                'description_en' => 'Existing customers can now upgrade their plans and enjoy double bandwidth speed for the first three months without extra costs.',
                'description_my' => 'လက်ရှိ ဖောက်သည်များအနေဖြင့် ၎င်းတို့၏ အစီအစဉ်များကို မြှင့်တင်နိုင်ပြီး အပိုကုန်ကျစရိတ်မရှိဘဲ ပထမသုံးလအတွက် နှစ်ဆမြန်နှုန်းကို ခံစားနိုင်ပါပြီ။',
                'description_zh' => '现有客户现在可以升级套餐，在前三个月内免费享受双倍带宽速度。',
                'start_date' => '2026-05-01',
                'end_date' => '2026-12-30',
                'is_active' => true,
                'image_url' => 'seeder_images/promotions/upgrade_promo.png',
            ],
            [
                'slug' => 'unlimited-weekend-gaming-pass',
                'title_en' => 'Weekend Gamer Pack: Zero Lag Guaranteed',
                'title_my' => 'စနေ၊ တနင်္ဂနွေ ဂိမ်းပက်ကေ့ချ် - လုံးဝ Lag မရှိစေရ',
                'title_zh' => '周末玩家包：保证零延迟',
                'description_en' => 'Level up your gaming experience. Subscribe to our gaming add-on and get prioritized routing and zero packet loss every weekend.',
                'description_my' => 'သင်၏ ဂိမ်းကစားခြင်း အတွေ့အကြုံကို မြှင့်တင်လိုက်ပါ။ ကျွန်ုပ်တို့၏ ဂိမ်း add-on ကို စာရင်းသွင်းပြီး စနေ၊ တနင်္ဂနွေတိုင်းတွင် ဦးစားပေးလိုင်းနှင့် packet loss ကင်းစင်မှုကို ရယူလိုက်ပါ။',
                'description_zh' => '提升您的游戏体验。订阅我们的游戏附加组件，每个周末享受优先路由和零丢包。',
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
                'image_url' => 'seeder_images/promotions/unlimited_promo.png',
            ],
            [
                'slug' => 'smarthome-router-bundle',
                'title_en' => 'Smart Home Wi-Fi 6 Mesh Bundle',
                'title_my' => 'စမတ်ဟုန်း Wi-Fi 6 Mesh ပက်ကေ့ချ်',
                'title_zh' => '智能家居 Wi-Fi 6 Mesh 套装',
                'description_en' => 'Eliminate dead zones in your house. Get a dual-pack Wi-Fi 6 mesh router system with free professional setup when you sign a 12-month contract.',
                'description_my' => 'သင့်အိမ်ရှိ အင်တာနက်မမိသော နေရာများကို ဖယ်ရှားလိုက်ပါ။ ၁၂ လစာချုပ် ချုပ်ဆိုပါက အခမဲ့ ကျွမ်းကျင်သူ တပ်ဆင်မှုနှင့်အတူ Wi-Fi 6 mesh router အစုံကို ရယူလိုက်ပါ။',
                'description_zh' => '消除您家中的死角。签订 12 个月合同，即可获得双包 Wi-Fi 6 mesh 路由器系统并免费进行专业设置。',
                'start_date' => null,
                'end_date' => '2026-12-31',
                'is_active' => true,
                'image_url' => 'seeder_images/promotions/smarthome_promo.png',
            ],
            [
                'slug' => 'refer-a-friend-cashback',
                'title_en' => 'Refer a Friend, Get 1 Month Free',
                'title_my' => 'သူငယ်ချင်းကို မိတ်ဆက်ပေးပြီး ၁ လ အခမဲ့ ရယူပါ',
                'title_zh' => '推荐朋友，获得 1 个月免费',
                'description_en' => 'Share the high-speed internet joy! Refer a friend to our fiber service, and both of you will receive 1 month of subscription fee credited back.',
                'description_my' => 'အမြန်နှုန်းမြင့် အင်တာနက် အတွေ့အကြုံကို မျှဝေလိုက်ပါ။ ကျွန်ုပ်တို့၏ ဖိုင်ဘာ ဝန်ဆောင်မှုသို့ သူငယ်ချင်းတစ်ဦးကို မိတ်ဆက်ပေးပြီး နှစ်ဦးစလုံး ၁ လစာ အခမဲ့ ရယူလိုက်ပါ။',
                'description_zh' => '分享高速互联网的乐趣！向朋友推荐我们的光纤服务，你们两人都将获得 1 个月的订阅费返还。',
                'start_date' => '2026-08-01',
                'end_date' => null,
                'is_active' => true,
                'image_url' => 'seeder_images/promotions/refer_promo.png',
            ],
            [
                'slug' => 'thadingyut-festival-promo',
                'title_en' => 'Thadingyut Special: Free Installation & TV Box',
                'title_my' => 'သီတင်းကျွတ် အထူးအစီအစဉ် - အခမဲ့ တပ်ဆင်ခနှင့် တီဗွီဘောက်စ်',
                'title_zh' => '点灯节特惠：免费安装 & 电视盒子',
                'description_en' => 'Celebrate the festival of lights with our new broadband plan. Enjoy completely free installation and a complimentary 4K Android TV box.',
                'description_my' => 'သီတင်းကျွတ် မီးထွန်းပွဲတော်ကို ကျွန်ုပ်တို့၏ ဘရော့ဘန်း အစီအစဉ်သစ်ဖြင့် ဆင်နွှဲလိုက်ပါ။ လုံးဝ အခမဲ့ တပ်ဆင်မှုနှင့်အတူ 4K Android TV box ကို လက်ဆောင်ရယူလိုက်ပါ။',
                'description_zh' => '通过我们的新宽带计划庆祝点灯节。享受完全免费的安装和赠送的 4K Android 电视盒子。',
                'start_date' => '2026-10-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
                'image_url' => 'seeder_images/promotions/thadingyut_promo.png',
            ],
        ])->each(fn(array $promotion) => Promotion::create($promotion));

        collect([
            [
                'image_url' => 'seeder_images/gallery/service_center.png',
                'label_en' => 'Our modern service center',
                'label_my' => 'ကျွန်ုပ်တို့၏ ခေတ်မီ ဝန်ဆောင်မှုစင်တာ',
                'label_zh' => '我们的现代服务中心',
            ],
            [
                'image_url' => 'seeder_images/gallery/reliable.png',
                'label_en' => 'Fast and reliable connectivity',
                'label_my' => 'မြန်ဆန်ပြီး ယုံကြည်စိတ်ချရသော ချိတ်ဆက်မှု',
                'label_zh' => '快速可靠的网络连接',
            ],
            [
                'image_url' => 'seeder_images/gallery/future.png',
                'label_en' => 'Built for a connected future',
                'label_my' => 'ချိတ်ဆက်ထားသော အနာဂတ်အတွက် တည်ဆောက်ထားသည်',
                'label_zh' => '为互联未来而建',
            ],
            [
                'image_url' => 'seeder_images/gallery/technoloy.png',
                'label_en' => 'Technology that brings people together',
                'label_my' => 'လူများကို ပေါင်းစည်းပေးသော နည်းပညာ',
                'label_zh' => '让人们紧密相连的科技',
            ],
            [
                'image_url' => 'seeder_images/gallery/forward.png',
                'label_en' => 'Together, we move forward',
                'label_my' => 'အတူတကွ ရှေ့ဆက်လှမ်းကြမည်',
                'label_zh' => '携手共创美好未来',
            ],
        ])->each(fn(array $gallery) => Gallery::create($gallery));

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
