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

                'description_en' => <<<'TEXT'
        Our Mobile Application is now available, making it easier and more convenient for you to access our Internet and digital services anytime and anywhere.

        With our mobile application, you can easily check your account information, manage your Internet services, view available packages, purchase data cards, and access other useful services from your mobile phone.

        The application is designed to provide a simple and convenient experience for our customers. Instead of visiting a service center or making a phone call for basic service information, you can manage many of your services directly through the application.

        You can also browse available Internet packages and data card options, select the package that best suits your needs, and complete your purchase conveniently through the application.

        Download and use our Mobile Application today to enjoy easier access to our services and manage your Internet account whenever you need it.
        TEXT,

                'description_my' => <<<'TEXT'
        ကျွန်ုပ်တို့၏ Mobile Application ကို ယခုအခါ အသုံးပြုနိုင်ပြီဖြစ်ကြောင်း ဝမ်းမြောက်စွာ အသိပေးအပ်ပါသည်။ လူကြီးမင်းတို့၏ Internet နှင့် Digital ဝန်ဆောင်မှုများကို အချိန်မရွေး၊ နေရာမရွေး ပိုမိုလွယ်ကူအဆင်ပြေစွာ အသုံးပြုနိုင်ရန်အတွက် Mobile Application ကို ဖန်တီးပေးထားခြင်းဖြစ်ပါသည်။

        Mobile Application မှတစ်ဆင့် မိမိ၏ Account အချက်အလက်များကို စစ်ဆေးခြင်း၊ Internet ဝန်ဆောင်မှုများကို စီမံခြင်း၊ ရရှိနိုင်သော Internet Package များကို ကြည့်ရှုခြင်း၊ Data Card များ ဝယ်ယူခြင်းနှင့် အခြားအသုံးဝင်သော ဝန်ဆောင်မှုများကို မိမိ၏ Mobile Phone မှတစ်ဆင့် လွယ်ကူစွာ အသုံးပြုနိုင်ပါသည်။

        အခြေခံဝန်ဆောင်မှုအချက်အလက်များ စစ်ဆေးရန် Service Center သို့ သွားရောက်ခြင်း သို့မဟုတ် ဖုန်းဆက်မေးမြန်းခြင်းများ ပြုလုပ်စရာမလိုဘဲ Application မှတစ်ဆင့် လိုအပ်သော ဝန်ဆောင်မှုများကို ပိုမိုလွယ်ကူစွာ စီမံနိုင်ပါသည်။

        ထို့အပြင် မိမိအသုံးပြုလိုသော Internet Package နှင့် Data Card များကို လွယ်ကူစွာ ရွေးချယ်ကြည့်ရှုနိုင်ပြီး မိမိ၏လိုအပ်ချက်နှင့် ကိုက်ညီသော Package ကို ရွေးချယ်ဝယ်ယူနိုင်ပါသည်။

        ကျွန်ုပ်တို့၏ Mobile Application ကို ယနေ့ပင် Download ပြုလုပ်အသုံးပြုပြီး Internet ဝန်ဆောင်မှုများကို အချိန်မရွေး၊ နေရာမရွေး ပိုမိုလွယ်ကူစွာ စီမံအသုံးပြုလိုက်ပါ။
        TEXT,

                'description_zh' => <<<'TEXT'
        我们很高兴地宣布，移动应用现已正式推出，为客户提供更加便捷的互联网及数字服务体验。

        通过我们的移动应用，您可以随时随地查看账户信息、管理互联网服务、浏览可用套餐、购买数据卡以及使用其他实用功能。

        该应用旨在为客户提供简单、快捷和方便的服务体验。对于一些基本的服务查询和管理，您无需前往服务中心或拨打电话，只需通过手机应用即可轻松完成。

        您还可以通过应用浏览各种互联网套餐和数据卡，选择符合自己需求的套餐，并方便地完成购买。

        立即下载并使用我们的移动应用，随时随地轻松管理您的互联网账户，享受更加便捷的服务体验。
        TEXT,

                'image_url' => 'seeder_images/news/intro_mobile_app.png',
                'status' => NewsStatus::Published,
                'slug' => 'mobile-app-now-available'
            ],
            [
                'category_id' => 1,
                'title_en' => 'New Branch Opening Announcement',
                'title_my' => 'ရုံးခွဲသစ် ဖွင့်လှစ်ခြင်း ကြေညာခြင်း',
                'title_zh' => '新分公司开业公告',

                'description_en' => <<<'TEXT'
                    We are pleased to announce the opening of our new branch to provide more convenient and accessible services to our customers.

                    The new branch has been opened to make it easier for customers in the surrounding areas to access our Internet and telecommunications services. Customers can visit the branch to learn more about our available services, Internet packages, data cards, and other service options.

                    Our staff at the new branch are ready to assist customers with service inquiries, new service applications, account-related assistance, package information, and other customer service needs.

                    We are continuously working to expand our service locations and improve the convenience of our customers. The opening of this new branch is another step toward providing reliable and accessible services to more customers.

                    We warmly welcome you to visit our new branch and experience our services. We look forward to serving you at our new location.
                    TEXT,

                'description_my' => <<<'TEXT'
                    လူကြီးမင်းတို့အတွက် ပိုမိုလွယ်ကူအဆင်ပြေစွာ ဝန်ဆောင်မှုပေးနိုင်ရန် ရုံးခွဲသစ်ကို ဖွင့်လှစ်လိုက်ပြီဖြစ်ကြောင်း ဝမ်းမြောက်စွာ အသိပေးအပ်ပါသည်။

                    ရုံးခွဲသစ်ကို အနီးပတ်ဝန်းကျင်ရှိ လူကြီးမင်းများအနေဖြင့် ကျွန်ုပ်တို့၏ Internet နှင့် ဆက်သွယ်ရေးဝန်ဆောင်မှုများကို ပိုမိုလွယ်ကူစွာ လာရောက်အသုံးပြုနိုင်ရန် ရည်ရွယ်၍ ဖွင့်လှစ်ထားခြင်းဖြစ်ပါသည်။

                    ရုံးခွဲသို့ လာရောက်ပြီး ရရှိနိုင်သော Internet ဝန်ဆောင်မှုများ၊ Internet Package များ၊ Data Card များနှင့် အခြားဝန်ဆောင်မှုများအကြောင်း အသေးစိတ် စုံစမ်းမေးမြန်းနိုင်ပါသည်။

                    ထို့အပြင် ဝန်ဆောင်မှုအသစ် လျှောက်ထားခြင်း၊ Account နှင့်ပတ်သက်သော အကူအညီများ ရယူခြင်း၊ Package အချက်အလက်များ စုံစမ်းခြင်းနှင့် အခြား Customer Service လိုအပ်ချက်များအတွက်လည်း ရုံးခွဲရှိ ဝန်ထမ်းများထံတွင် အလွယ်တကူ အကူအညီရယူနိုင်ပါသည်။

                    လူကြီးမင်းတို့အတွက် ပိုမိုကောင်းမွန်ပြီး အဆင်ပြေသော ဝန်ဆောင်မှုများ ပေးနိုင်ရန် ကျွန်ုပ်တို့အနေဖြင့် ဝန်ဆောင်မှုနေရာများကို ဆက်လက်တိုးချဲ့ပေးလျက်ရှိပါသည်။

                    ရုံးခွဲသစ်တွင် လူကြီးမင်းတို့အား နွေးထွေးစွာ ကြိုဆိုလျက်ရှိပြီး ကျွန်ုပ်တို့၏ ဝန်ဆောင်မှုများကို လာရောက်အသုံးပြုကြရန် ဖိတ်ခေါ်အပ်ပါသည်။
                    TEXT,

                'description_zh' => <<<'TEXT'
                    我们很高兴地宣布，新分公司现已正式开业，为客户提供更加方便、便捷的服务。

                    新分公司的开设旨在方便周边地区的客户使用我们的互联网及通信服务。客户可以前往新分公司了解我们的互联网服务、套餐、数据卡以及其他相关服务。

                    新分公司的工作人员将为客户提供服务咨询、新业务申请、账户相关协助、套餐信息查询以及其他客户服务支持。

                    为了让更多客户能够更加方便地享受我们的服务，我们将持续扩大服务范围并不断提升客户体验。新分公司的开业也是我们进一步完善服务网络的重要一步。

                    欢迎您前往我们的新分公司，我们期待在新的服务地点为您提供优质、便捷的服务。
                    TEXT,

                'image_url' => 'seeder_images/news/open_new_branch.png',
                'status' => NewsStatus::Published,
                'slug' => 'new-branch-opening'
            ],
            [
                'category_id' => 1,
                'title_en' => 'Get Data Card Easily with Our Application',
                'title_my' => 'Application မှာ အကြိုက်သုံး‌ဒေတာကဒ်များကို လွယ်ကူစွာ မှာယူနိုင်ပါပြီ',
                'title_zh' => '在我们的应用上轻松订购您喜欢的数据卡',

                'description_en' => <<<'TEXT'
                    Getting your favorite data card is now easier and more convenient with our Mobile Application.

                    You can browse the available data cards directly from the application and choose the option that best matches your Internet usage and needs. With just a few simple steps, you can select your preferred data card and place your order without having to visit a service center.

                    The application allows you to conveniently check available data card options and choose the card that is suitable for your usage. This makes it easier for customers to manage their Internet needs whenever and wherever they need them.

                    Whether you need additional data for daily Internet usage or want to purchase a data card for your personal needs, you can easily place your order through our application.

                    Use our Mobile Application today and enjoy a simple, fast, and convenient way to order your preferred data cards.
                    TEXT,

                'description_my' => <<<'TEXT'
                    မိမိအကြိုက်သုံး Data Card များကို ယခုအခါ ကျွန်ုပ်တို့၏ Mobile Application မှတစ်ဆင့် ပိုမိုလွယ်ကူအဆင်ပြေစွာ မှာယူနိုင်ပြီဖြစ်ပါသည်။

                    Application ထဲတွင် ရရှိနိုင်သော Data Card များကို လွယ်ကူစွာ ကြည့်ရှုနိုင်ပြီး မိမိ၏ Internet အသုံးပြုမှုနှင့် လိုအပ်ချက်နှင့် ကိုက်ညီသော Data Card ကို ရွေးချယ်နိုင်ပါသည်။ အဆင့်အနည်းငယ်ဖြင့် မိမိနှစ်သက်ရာ Data Card ကို ရွေးချယ်ပြီး Service Center သို့ သွားရောက်ရန်မလိုဘဲ အလွယ်တကူ မှာယူနိုင်ပါသည်။

                    Application မှတစ်ဆင့် ရရှိနိုင်သော Data Card အမျိုးအစားများကို စစ်ဆေးနိုင်ပြီး မိမိ၏ Internet အသုံးပြုမှုပုံစံနှင့် ကိုက်ညီသော Data Card ကို ရွေးချယ်နိုင်ပါသည်။ ထို့ကြောင့် လိုအပ်သည့်အချိန်တွင် မိမိ၏ Internet လိုအပ်ချက်များကို ပိုမိုလွယ်ကူစွာ စီမံနိုင်ပါသည်။

                    နေ့စဉ် Internet အသုံးပြုရန်အတွက် Data ထပ်မံလိုအပ်သည်ဖြစ်စေ၊ မိမိကိုယ်ပိုင်အသုံးပြုရန် Data Card ဝယ်ယူလိုသည်ဖြစ်စေ Application မှတစ်ဆင့် အချိန်မရွေး လွယ်ကူစွာ မှာယူနိုင်ပါသည်။

                    ကျွန်ုပ်တို့၏ Mobile Application ကို အသုံးပြုပြီး မိမိနှစ်သက်ရာ Data Card များကို ရိုးရှင်းမြန်ဆန်စွာ မှာယူလိုက်ပါ။
                    TEXT,

                'description_zh' => <<<'TEXT'
                    现在，通过我们的移动应用购买您喜欢的数据卡变得更加简单方便。

                    您可以直接在应用中浏览当前可用的数据卡，并根据自己的互联网使用情况和需求选择合适的数据卡。只需几个简单步骤，即可选择您喜欢的数据卡并完成订购，无需前往服务中心。

                    通过移动应用，您可以方便地查看不同的数据卡选项，并选择最适合自己使用需求的数据卡，让您的互联网服务管理更加轻松。

                    无论您是需要额外的数据流量来满足日常互联网使用需求，还是想为个人使用购买数据卡，都可以通过我们的应用轻松完成订购。

                    立即使用我们的移动应用，以简单、快捷、方便的方式订购您喜欢的数据卡。
                    TEXT,

                'image_url' => 'seeder_images/news/data_card_news.png',
                'status' => NewsStatus::Published,
                'slug' => 'get-data-card'
            ],
            [
                'category_id' => 5,
                'title_en' => 'We Are Hiring! Join Our Team',
                'title_my' => 'ဝန်ထမ်းသစ်များ ခေါ်ယူနေပါပြီ! ကျွန်ုပ်တို့၏ Team တွင် ပါဝင်လိုက်ပါ',
                'title_zh' => '我们正在招聘！加入我们的团队',

                'description_en' => <<<'TEXT'
                    We are growing our team and are looking for talented, motivated, and passionate individuals to join us.

                    We believe that our people are an important part of our success. We are looking for individuals who are interested in technology, enjoy learning new skills, and are ready to take on new challenges and opportunities.

                    By joining our team, you will have the opportunity to work in a professional environment, develop your skills, gain valuable experience, and work together with colleagues on meaningful projects and services.

                    We welcome candidates who are responsible, motivated, willing to learn, and interested in building a career in the technology and telecommunications industry.

                    If you are looking for a new career opportunity and would like to grow together with our team, we invite you to explore our available positions and submit your application.

                    Join our team and take the next step in your career with us.
                    TEXT,

                'description_my' => <<<'TEXT'
                    ကျွန်ုပ်တို့၏ တိုးတက်လာနေသော Team တွင် ပါဝင်ရန် အရည်အချင်းရှိပြီး ကြိုးစားလိုစိတ်ရှိသူများကို ဝန်ထမ်းသစ်အဖြစ် ခေါ်ယူနေပါသည်။

                    ကျွန်ုပ်တို့၏ အောင်မြင်တိုးတက်မှုတွင် ဝန်ထမ်းများသည် အရေးကြီးသော အခန်းကဏ္ဍမှ ပါဝင်လျက်ရှိပါသည်။ နည်းပညာကို စိတ်ဝင်စားသူများ၊ အသစ်အသစ်သော အရာများကို လေ့လာလိုသူများနှင့် စိန်ခေါ်မှုအသစ်များကို ရင်ဆိုင်ရန် အသင့်ရှိသူများကို ကျွန်ုပ်တို့၏ Team တွင် ပါဝင်ရန် ဖိတ်ခေါ်အပ်ပါသည်။

                    ကျွန်ုပ်တို့၏ Team တွင် ပါဝင်ခြင်းဖြင့် Professional Working Environment တစ်ခုအတွင်း အလုပ်လုပ်နိုင်ခြင်း၊ မိမိ၏ ကျွမ်းကျင်မှုများကို တိုးတက်အောင် လေ့လာနိုင်ခြင်း၊ အတွေ့အကြုံကောင်းများ ရရှိနိုင်ခြင်းနှင့် လုပ်ဖော်ကိုင်ဖက်များနှင့်အတူ အရေးကြီးသော Project များနှင့် ဝန်ဆောင်မှုများတွင် ပူးပေါင်းပါဝင်နိုင်ခြင်းတို့ကို ရရှိနိုင်ပါသည်။

                    တာဝန်ယူမှုရှိသူများ၊ ကြိုးစားလိုစိတ်ရှိသူများ၊ အသစ်အဆန်းများကို လေ့လာသင်ယူလိုသူများနှင့် Technology နှင့် Telecommunications လုပ်ငန်းနယ်ပယ်တွင် အလုပ်အကိုင်လမ်းကြောင်းတစ်ခု တည်ဆောက်လိုသူများကို ကြိုဆိုပါသည်။

                    အလုပ်အကိုင်အခွင့်အလမ်းအသစ်တစ်ခုကို ရှာဖွေနေပြီး ကျွန်ုပ်တို့နှင့်အတူ တိုးတက်လိုသူများအနေဖြင့် လက်ရှိခေါ်ယူနေသော ရာထူးများကို လေ့လာကြည့်ရှုပြီး ယခုပဲ လျှောက်ထားနိုင်ပါသည်။

                    ကျွန်ုပ်တို့၏ Team တွင် ပါဝင်ပြီး မိမိ၏ Career အတွက် နောက်ထပ်ခြေလှမ်းတစ်ခုကို ကျွန်ုပ်တို့နှင့်အတူ စတင်လိုက်ပါ။
                    TEXT,

                'description_zh' => <<<'TEXT'
                    随着团队不断发展，我们正在寻找优秀、积极、有责任心并充满热情的人才加入我们的团队。

                    我们相信，员工是企业持续发展和成功的重要组成部分。因此，我们欢迎对科技感兴趣、愿意学习新技能，并乐于迎接新挑战和新机会的人才加入我们。

                    加入我们的团队后，您将有机会在专业的工作环境中工作，不断提升自己的技能，积累宝贵的工作经验，并与团队成员一起参与有意义的项目和服务。

                    我们欢迎有责任心、积极主动、愿意学习，并希望在科技及电信行业发展职业生涯的人才。

                    如果您正在寻找新的职业机会，并希望与我们一起成长，欢迎了解目前开放的职位并提交您的申请。

                    加入我们的团队，与我们一起开启职业发展的下一步。
                    TEXT,

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

                'description_en' => <<<'TEXT'
                    Enjoy our special Monsoon Promotion and stay connected throughout the rainy season with our Internet services.

                    During the promotional period, customers can enjoy special offers and benefits on selected Internet services and packages. Whether you need reliable Internet for work, study, entertainment, or staying connected with family and friends, our services are designed to support your everyday needs.

                    Take this opportunity to explore our available Internet packages and choose the option that best suits your usage requirements. With convenient service options and special promotional benefits, you can enjoy a better Internet experience throughout the monsoon season.

                    Don't miss this limited-time Monsoon Special Promotion. Check our available offers and enjoy the benefits during the promotional period.
                    TEXT,

                'description_my' => <<<'TEXT'
                    မိုးရာသီကာလအတွင်း Internet ချိတ်ဆက်အသုံးပြုမှုများ ပိုမိုအဆင်ပြေစေရန် ကျွန်ုပ်တို့၏ မိုးရာသီ အထူးပရိုမိုးရှင်းအစီအစဉ်ကို ပြုလုပ်ပေးထားပါသည်။

                    ပရိုမိုးရှင်းကာလအတွင်း သတ်မှတ်ထားသော Internet ဝန်ဆောင်မှုများနှင့် Package များအတွက် အထူးအစီအစဉ်များနှင့် အကျိုးခံစားခွင့်များကို ရရှိနိုင်ပါသည်။ အလုပ်လုပ်ခြင်း၊ စာလေ့လာခြင်း၊ ဖျော်ဖြေရေးအတွက် အသုံးပြုခြင်း သို့မဟုတ် မိသားစုနှင့် မိတ်ဆွေများထံ ဆက်သွယ်ခြင်းတို့အတွက် ယုံကြည်စိတ်ချရသော Internet ဝန်ဆောင်မှုကို အသုံးပြုနိုင်ပါသည်။

                    မိမိ၏ Internet အသုံးပြုမှုပုံစံနှင့် လိုအပ်ချက်များနှင့် ကိုက်ညီသော Internet Package များကို ရွေးချယ်နိုင်ပြီး ပရိုမိုးရှင်းကာလအတွင်း ရရှိနိုင်သော အထူးအကျိုးခံစားခွင့်များကို အသုံးပြုနိုင်ပါသည်။

                    မိုးရာသီအတွက် အထူးစီစဉ်ပေးထားသော ဤအချိန်ကာလအကန့်အသတ်ရှိ ပရိုမိုးရှင်းကို လက်မလွတ်စေရန် ရရှိနိုင်သော အထူးအစီအစဉ်များကို ယခုပင် လေ့လာအသုံးပြုလိုက်ပါ။
                    TEXT,

                'description_zh' => <<<'TEXT'
                    在雨季期间，我们特别推出雨季优惠活动，让您在整个雨季都能享受更加便捷的互联网服务。

                    在促销期间，客户可享受指定互联网服务及套餐的特别优惠和福利。无论是工作、学习、娱乐，还是与家人朋友保持联系，我们的互联网服务都能满足您的日常使用需求。

                    您可以了解目前提供的互联网套餐，并根据自己的使用需求选择合适的方案。在享受便捷服务的同时，也可以获得雨季特别促销期间提供的优惠福利。

                    不要错过限时雨季特别促销。立即查看可用优惠，在促销期间享受更多服务福利。
                    TEXT,

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

                'description_en' => <<<'TEXT'
                    Celebrate the Thadingyut Festival with our special Wi-Fi installation promotion.

                    During the promotional period, customers can enjoy a special discount on eligible Wi-Fi installation services. This is a great opportunity for customers who are planning to install a new Wi-Fi service or set up a reliable Internet connection for their home or workplace.

                    With a convenient installation service, you can get your Internet connection set up and enjoy reliable connectivity for everyday activities such as working, studying, watching entertainment content, and communicating with family and friends.

                    Take advantage of this special Thadingyut promotion and reduce your Wi-Fi installation cost during the promotional period.

                    Check the promotion details and contact us to learn more about the eligible services and installation offer.
                    TEXT,

                'description_my' => <<<'TEXT'
                    သီတင်းကျွတ်ပွဲတော်အထိမ်းအမှတ်အဖြစ် လူကြီးမင်းတို့အတွက် Wi-Fi တပ်ဆင်ခ အထူးလျှော့စျေး ပရိုမိုးရှင်းအစီအစဉ်ကို ပြုလုပ်ပေးထားပါသည်။

                    ပရိုမိုးရှင်းကာလအတွင်း သတ်မှတ်ထားသော Wi-Fi တပ်ဆင်ခြင်းဝန်ဆောင်မှုများကို အထူးလျှော့စျေးဖြင့် ရရှိနိုင်ပါသည်။ Wi-Fi အသစ်တပ်ဆင်လိုသူများနှင့် အိမ် သို့မဟုတ် ရုံးတွင် ယုံကြည်စိတ်ချရသော Internet ချိတ်ဆက်မှုကို ရယူလိုသူများအတွက် အထူးအဆင်ပြေသော အစီအစဉ်ဖြစ်ပါသည်။

                    Wi-Fi တပ်ဆင်ပြီးနောက် အလုပ်လုပ်ခြင်း၊ စာလေ့လာခြင်း၊ ဖျော်ဖြေရေးအစီအစဉ်များ ကြည့်ရှုခြင်းနှင့် မိသားစု၊ မိတ်ဆွေများနှင့် ဆက်သွယ်ခြင်းစသည့် နေ့စဉ်လုပ်ငန်းများအတွက် Internet ကို အဆင်ပြေစွာ အသုံးပြုနိုင်ပါသည်။

                    သီတင်းကျွတ်အထူးပရိုမိုးရှင်းကာလအတွင်း Wi-Fi တပ်ဆင်ခကို လျှော့စျေးဖြင့် ရယူပြီး အကျိုးခံစားခွင့်ကို အသုံးချလိုက်ပါ။

                    ပရိုမိုးရှင်းနှင့် သက်ဆိုင်သော ဝန်ဆောင်မှုများနှင့် တပ်ဆင်ခလျှော့စျေးအကြောင်း အသေးစိတ်သိရှိလိုပါက ယခုပင် ဆက်သွယ်စုံစမ်းနိုင်ပါသည်။
                    TEXT,

                'description_zh' => <<<'TEXT'
                    为庆祝点灯节，我们特别推出 Wi-Fi 安装费优惠活动。

                    在促销期间，客户可以享受指定 Wi-Fi 安装服务的特别折扣。对于计划安装新 Wi-Fi 服务，或希望在家中、办公室建立稳定互联网连接的客户来说，这是一个方便的选择。

                    通过便捷的安装服务，您可以快速建立互联网连接，满足日常工作、学习、娱乐以及与家人朋友保持联系等需求。

                    把握点灯节特别促销机会，在活动期间享受 Wi-Fi 安装费用优惠。

                    如需了解更多关于适用服务及安装优惠的详细信息，欢迎联系我们进行咨询。
                    TEXT,

                'start_date' => '2026-08-24',
                'end_date' => '2027-10-28',
                'is_active' => true,
                'image_url' => 'seeder_images/promotion/thadingyut-promotion.png',
            ],
            [
                'slug' => 'chinese-new-year-package-promotion',

                'title_en' => 'Chinese New Year Special: Buy a Package, Get 1 Month Free',
                'title_my' => 'တရုတ်နှစ်သစ်ကူး အထူးပရိုမိုးရှင်း - Package ဝယ်လိုက်ရုံနဲ့ တစ်လလက်ဆောင်',
                'title_zh' => '春节特别优惠：购买套餐即赠送一个月',

                'description_en' => <<<'TEXT'
                    Celebrate the Chinese New Year with our special package promotion and enjoy an extra month of service at no additional cost.

                    During the promotional period, customers who purchase an eligible Internet package can receive one additional month of service for free. This special offer allows you to enjoy longer Internet service while getting more value from your selected package.

                    Choose an eligible package according to your Internet usage and enjoy reliable connectivity for work, study, entertainment, online activities, and staying connected with family and friends.

                    The additional free month gives you more time to enjoy your Internet service without paying an extra monthly package fee for that promotional month.

                    Celebrate the Chinese New Year with more connectivity and more value. Check the eligible packages and take advantage of this special limited-time promotion.
                    TEXT,

                'description_my' => <<<'TEXT'
                    တရုတ်နှစ်သစ်ကူးအထိမ်းအမှတ်အဖြစ် Internet အသုံးပြုသူများအတွက် အထူးအကျိုးခံစားခွင့်ရရှိနိုင်မည့် Package Promotion အစီအစဉ်ကို ပြုလုပ်ပေးထားပါသည်။

                    ပရိုမိုးရှင်းကာလအတွင်း သတ်မှတ်ထားသော Internet Package ကို ဝယ်ယူလိုက်ရုံဖြင့် တစ်လစာ Internet ဝန်ဆောင်မှုကို အခမဲ့လက်ဆောင်အဖြစ် ရရှိနိုင်ပါသည်။ ထို့ကြောင့် မိမိရွေးချယ်ထားသော Package မှ ပိုမိုတန်ဖိုးရှိသော အကျိုးခံစားခွင့်ကို ရရှိနိုင်မည်ဖြစ်ပါသည်။

                    မိမိ၏ Internet အသုံးပြုမှုပုံစံနှင့် ကိုက်ညီသော သတ်မှတ်ထားသည့် Package ကို ရွေးချယ်ပြီး အလုပ်လုပ်ခြင်း၊ စာလေ့လာခြင်း၊ ဖျော်ဖြေရေးနှင့် Online ဝန်ဆောင်မှုများ အသုံးပြုခြင်းအပြင် မိသားစုနှင့် မိတ်ဆွေများနှင့် ဆက်သွယ်ခြင်းတို့ကို အဆင်ပြေစွာ ဆက်လက်အသုံးပြုနိုင်ပါသည်။

                    အခမဲ့လက်ဆောင်ရရှိသော တစ်လစာအတွင်း ထပ်မံ၍ လစဉ် Package ကြေးပေးဆောင်ရန်မလိုဘဲ Internet ဝန်ဆောင်မှုကို ဆက်လက်အသုံးပြုနိုင်ပါသည်။

                    တရုတ်နှစ်သစ်ကူးအချိန်အခါတွင် Internet ချိတ်ဆက်မှုနှင့် အကျိုးခံစားခွင့်များ ပိုမိုရရှိနိုင်ရန် သတ်မှတ်ထားသော Package များကို ယခုပင် လေ့လာပြီး ဤအချိန်ကာလအကန့်အသတ်ရှိ ပရိုမိုးရှင်းကို အသုံးချလိုက်ပါ။
                    TEXT,

                'description_zh' => <<<'TEXT'
                    为庆祝春节，我们特别推出套餐优惠活动，购买指定套餐即可免费获得额外一个月的服务。

                    在促销期间，购买符合条件的互联网套餐，即可获赠一个月免费服务。通过这项特别优惠，您可以延长互联网服务使用时间，并从所选择的套餐中获得更多价值。

                    您可以根据自己的互联网使用需求选择符合条件的套餐，用于工作、学习、娱乐、在线活动以及与家人朋友保持联系。

                    获赠的一个月服务无需额外支付该促销月份的套餐费用，让您可以继续享受稳定便捷的互联网服务。

                    春节期间享受更多连接与更多优惠。立即查看符合条件的套餐，把握这项限时特别促销。
        TEXT,

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

                'description_en' => <<<'TEXT'
                    Celebrate Thingyan with our special package promotion and enjoy 10% off eligible Internet packages during the promotional period.

                    This special Thingyan offer gives customers the opportunity to select an Internet package that matches their usage needs and enjoy a 10% discount on the package price.

                    Whether you use the Internet for work, study, entertainment, online activities, or staying connected with family and friends during the Thingyan holiday, you can choose a suitable package and enjoy the promotional discount.

                    The 10% discount provides an opportunity to save on your selected package while continuing to enjoy convenient and reliable Internet services.

                    Make the most of the Thingyan season with our special package discount. Check the available packages and enjoy 10% off during the promotional period.
                    TEXT,

                'description_my' => <<<'TEXT'
                    သင်္ကြန်ပွဲတော်အထိမ်းအမှတ်အဖြစ် လူကြီးမင်းတို့အတွက် Internet Package များကို ၁၀% လျှော့စျေးဖြင့် ရရှိနိုင်မည့် အထူးပရိုမိုးရှင်းအစီအစဉ်ကို ပြုလုပ်ပေးထားပါသည်။

                    ပရိုမိုးရှင်းကာလအတွင်း သတ်မှတ်ထားသော Internet Package များကို မိမိ၏ လိုအပ်ချက်နှင့် ကိုက်ညီစွာ ရွေးချယ်ဝယ်ယူနိုင်ပြီး Package စျေးနှုန်းအပေါ် ၁၀% လျှော့စျေး အကျိုးခံစားခွင့်ကို ရရှိနိုင်ပါသည်။

                    သင်္ကြန်ကာလအတွင်း အလုပ်လုပ်ခြင်း၊ စာလေ့လာခြင်း၊ ဖျော်ဖြေရေးအစီအစဉ်များ ကြည့်ရှုခြင်း၊ Online ဝန်ဆောင်မှုများ အသုံးပြုခြင်း သို့မဟုတ် မိသားစုနှင့် မိတ်ဆွေများနှင့် ဆက်သွယ်ခြင်းတို့အတွက် Internet အသုံးပြုမည်ဆိုပါက မိမိနှင့် ကိုက်ညီသော Package ကို ရွေးချယ်နိုင်ပါသည်။

                    မိမိရွေးချယ်ထားသော Package ကို ၁၀% လျှော့စျေးဖြင့် ရယူနိုင်သည့်အတွက် Package အသုံးပြုရာတွင် ကုန်ကျစရိတ်ကို သက်သာစေပြီး အဆင်ပြေသော Internet ဝန်ဆောင်မှုကို ဆက်လက်အသုံးပြုနိုင်ပါသည်။

                    သင်္ကြန်ကာလကို Internet Package အထူးလျှော့စျေးနှင့်အတူ ပိုမိုအဆင်ပြေစွာ ဖြတ်သန်းလိုက်ပါ။ ရရှိနိုင်သော Package များကို ယခုပင် လေ့လာပြီး ပရိုမိုးရှင်းကာလအတွင်း ၁၀% လျှော့စျေးကို ရယူလိုက်ပါ။
                    TEXT,

                'description_zh' => <<<'TEXT'
                    为庆祝泼水节，我们特别推出套餐优惠活动。在促销期间，符合条件的互联网套餐可享受 10% 折扣。

                    在活动期间，客户可以根据自己的互联网使用需求选择合适的套餐，并享受套餐价格 10% 的特别优惠。

                    无论您是在泼水节期间使用互联网进行工作、学习、娱乐、在线活动，还是与家人朋友保持联系，都可以选择符合自己需求的互联网套餐并享受特别折扣。

                    通过 10% 的套餐优惠，您可以在继续享受便捷、稳定互联网服务的同时，节省所选套餐的费用。

                    在泼水节期间享受我们的特别套餐优惠。立即查看可用套餐，并在促销期间享受 10% 折扣。
                    TEXT,

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
