<?php

namespace Database\Seeders;

use App\Enums\ChangePlanStatus;
use App\Enums\CustomerPackageStatus;
use App\Enums\InvoiceStatus;
use App\Enums\NewsStatus;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
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

        collect([
            [
                'category_id' => 1,
                'title_en' => 'Our Mobile App Is Now Available',
                'title_my' => 'Mobile App ကို အသုံးပြုနိုင်ပါပြီ',
                'title_zh' => '我们的移动应用现已推出',
                'description_en' => 'Our mobile application is now available for you to enjoy convenient access to our services. Easily check your account, manage your services, purchase data cards, and more, anytime and anywhere.',
                'description_my' => 'ကျွန်ုပ်တို့၏ Mobile App ကို ယခုအခါ အသုံးပြုနိုင်ပါပြီ။ Account စစ်ဆေးခြင်း၊ ဝန်ဆောင်မှုများ စီမံခြင်း၊ Data Card များ ဝယ်ယူခြင်းနှင့် အခြားဝန်ဆောင်မှုများကို အချိန်မရွေး၊ နေရာမရွေး လွယ်ကူစွာ အသုံးပြုနိုင်ပါသည်။',
                'description_zh' => '我们的移动应用现已推出，为您提供更加便捷的服务。您可以随时随地查看账户、管理服务、购买数据卡以及使用更多功能。',
                'image_url' => 'seeder_images/news/intro_mobile_app.png',
                'status' => NewsStatus::Published,
                'slug' => 'mobile-app-now-available'
            ],
            [
                'category_id' => 1,
                'title_en' => 'New Branch Opening Announcement',
                'title_my' => 'ရုံးခွဲသစ် ဖွင့်လှစ်ခြင်း ကြေညာခြင်း',
                'title_zh' => '新分公司开业公告',
                'description_en' => 'We are pleased to announce the opening of our new branch. Our new branch is now ready to provide convenient and reliable services to customers in the area. We look forward to serving you at our new location.',
                'description_my' => 'လူကြီးမင်းတို့အတွက် ပိုမိုလွယ်ကူအဆင်ပြေစွာ ဝန်ဆောင်မှုပေးနိုင်ရန် ရုံးခွဲသစ်ကို ဖွင့်လှစ်လိုက်ပြီဖြစ်ကြောင်း ဝမ်းမြောက်စွာ အသိပေးအပ်ပါသည်။ ရုံးခွဲသစ်တွင် အင်တာနက်နှင့် ဆက်သွယ်ရေးဝန်ဆောင်မှုများကို အဆင်ပြေစွာ လာရောက်အသုံးပြုနိုင်ပါသည်။ လူကြီးမင်းတို့အား ရုံးခွဲသစ်တွင် နွေးထွေးစွာ ကြိုဆိုပါသည်။',
                'description_zh' => '我们很高兴地宣布新分公司正式开业。欢迎前往新分公司，享受更加便捷的服务。',
                'image_url' => 'seeder_images/news/open_new_branch.png',
                'status' => NewsStatus::Published,
                'slug' => 'new-branch-opening'
            ],
            [
                'category_id' => 1,
                'title_en' => 'Get Data Card Easily with Our Application',
                'title_my' => 'Application မှာ အကြိုက်သုံး‌ဒေတာကဒ်များကို လွယ်ကူစွာ မှာယူနိုင်ပါပြီ',
                'title_zh' => '在我们的应用上轻松订购您喜欢的数据卡',
                'description_en' => 'Easily order your favorite data cards anytime through our application. Choose the data card that suits your needs and enjoy a convenient ordering experience.',
                'description_my' => 'Application မှတစ်ဆင့် မိမိအကြိုက်သုံးဒေတာကဒ်များကို အချိန်မရွေး လွယ်ကူစွာ မှာယူနိုင်ပါပြီ။ လိုအပ်ချက်နှင့် ကိုက်ညီသော ဒေတာကဒ်ကို ရွေးချယ်ပြီး အဆင်ပြေစွာ မှာယူအသုံးပြုနိုင်ပါသည်။',
                'description_zh' => '通过我们的应用，您可以随时轻松订购喜欢的数据卡。选择适合您需求的数据卡，享受便捷的订购体验。',
                'image_url' => 'seeder_images/news/data_card_news.png',
                'status' => NewsStatus::Published,
                'slug' => 'get-data-card'
            ],
            [
                'category_id' => 5,
                'title_en' => 'We Are Hiring! Join Our Team',
                'title_my' => 'ဝန်ထမ်းသစ်များ ခေါ်ယူနေပါပြီ! ကျွန်ုပ်တို့၏ Team တွင် ပါဝင်လိုက်ပါ',
                'title_zh' => '我们正在招聘！加入我们的团队',
                'description_en' => 'We are looking for talented and motivated individuals to join our growing team. If you are passionate about technology and ready for a new career opportunity, apply now and grow with us.',
                'description_my' => 'ကျွန်ုပ်တို့၏ တိုးတက်လာနေသော Team တွင် ပါဝင်ရန် အရည်အချင်းရှိပြီး ကြိုးစားလိုစိတ်ရှိသူများကို ဝန်ထမ်းသစ်အဖြစ် ခေါ်ယူနေပါသည်။ နည်းပညာကို စိတ်ဝင်စားပြီး အလုပ်အကိုင်အခွင့်အလမ်းအသစ်များကို ရှာဖွေနေသူများအနေဖြင့် ယခုပဲ လျှောက်ထားပြီး ကျွန်ုပ်တို့နှင့်အတူ တိုးတက်လိုက်ပါ။',
                'description_zh' => '我们正在寻找优秀且积极进取的人才加入不断发展的团队。如果您热爱科技并正在寻找新的职业机会，欢迎立即申请，与我们一起成长。',
                'image_url' => 'seeder_images/news/hiring_news.png',
                'status' => NewsStatus::Published,
                'slug' => 'hiring'
            ],
        ])->each(fn(array $news) => News::create($news));

        (new ServiceSeeder())->run();

        collect([
            [
                'slug' => 'monsoon-special-promotion',
                'title_en' => 'Monsoon Special Promotion',
                'title_my' => 'မိုးရာသီ အထူးပရိုမိုးရှင်း',
                'title_zh' => '雨季特别促销',
                'description_en' => 'Enjoy our special monsoon promotion during the promotional period.',
                'description_my' => 'မိုးရာသီ အထူးပရိုမိုးရှင်းကာလအတွင်း အထူးအစီအစဉ်များကို ရယူလိုက်ပါ။',
                'description_zh' => '在促销期间享受我们的雨季特别优惠。',
                'start_date' => '2026-07-24',
                'end_date' => '2027-07-31',
                'is_active' => true,
                'image_url' => 'seeder_images/promotion/monsoon-promotion.png',
            ],
            [
                'slug' => 'thadingyut-wifi-promotion',
                'title_en' => 'Thadingyut Special Promotion: Wi-Fi Installation Discount',
                'title_my' => 'သီတင်းကျွတ် အထူးအစီအစဉ် - Wi-Fi တပ်ဆင်ခ လျှော့စျေး',
                'title_zh' => '点灯节特别促销：Wi-Fi 安装费优惠',
                'description_en' => 'Celebrate Thadingyut with a special discount on Wi-Fi installation services.',
                'description_my' => 'သီတင်းကျွတ် အထူးပရိုမိုးရှင်းကာလအတွင်း Wi-Fi တပ်ဆင်ခကို အထူးလျှော့စျေးဖြင့် ရယူလိုက်ပါ။',
                'description_zh' => '点灯节期间，Wi-Fi 安装服务享受特别优惠。',
                'start_date' => '2026-8-24',
                'end_date' => '2027-10-28',
                'is_active' => true,
                'image_url' => 'seeder_images/promotion/thadingyut-promotion.png',
            ],
            [
                'slug' => 'chinese-new-year-package-promotion',
                'title_en' => 'Chinese New Year Special: Buy a Package, Get 1 Month Free',
                'title_my' => 'တရုတ်နှစ်သစ်ကူး အထူးပရိုမိုးရှင်း - Package ဝယ်လိုက်ရုံနဲ့ တစ်လလက်ဆောင်',
                'title_zh' => '春节特别优惠：购买套餐即赠送一个月',
                'description_en' => 'Celebrate Chinese New Year with our special promotion! Purchase an eligible package and enjoy one additional month free. Get more value with your package this Chinese New Year.',
                'description_my' => 'တရုတ်နှစ်သစ်ကူး အထူးပရိုမိုးရှင်းအနေဖြင့် သတ်မှတ်ထားသော Package ကို ဝယ်ယူလိုက်ရုံဖြင့် တစ်လစာ အခမဲ့လက်ဆောင် ရရှိနိုင်ပါပြီ။ ဒီနှစ်သစ်ကူးမှာ Package တစ်ခုဝယ်ပြီး တစ်လစာအပိုလက်ဆောင်ကို ရယူလိုက်ပါ။',
                'description_zh' => '春节特别优惠！购买指定套餐即可获赠一个月免费服务。新春期间购买套餐，享受更多优惠与惊喜。',
                'start_date' => null,
                'end_date' => '2027-02-28',
                'is_active' => true,
                'image_url' => 'seeder_images/promotion/chinese-new-year-promotion.png',
            ],
            [
                'slug' => 'thingyan-package-discount',
                'title_en' => 'Thingyan Special Promotion: 10% Off Packages',
                'title_my' => 'သင်္ကြန် အထူးပရိုမိုးရှင်း - Package အားလုံး ၁၀% လျှော့စျေး',
                'title_zh' => '泼水节特别优惠：套餐九折',
                'description_en' => 'Enjoy 10% off our packages during the Thingyan special promotion.',
                'description_my' => 'သင်္ကြန် အထူးပရိုမိုးရှင်းကာလအတွင်း Package အားလုံးကို ၁၀% လျှော့စျေးဖြင့် ရယူလိုက်ပါ။',
                'description_zh' => '泼水节特别促销期间，所有套餐享受 10% 折扣。',
                'start_date' => '2026-04-13',
                'end_date' => '2027-04-17',
                'is_active' => true,
                'image_url' => 'seeder_images/promotion/thinggyan-promotion.png',
            ],
        ])->each(fn(array $promotion) => Promotion::create($promotion));

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
                'contact_point' => '📞 +959887288882',
            ],
            [
                'contact_point' => '📞 +959421823339',
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
