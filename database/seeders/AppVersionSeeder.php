<?php

namespace Database\Seeders;

use App\Models\AppVersion;
use Illuminate\Database\Seeder;

class AppVersionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $releaseNotesEn = implode("\n", [
            '• Manage your CG Net internet network',
            '• Redeem the balance for yourself or a friend',
            '• View your redeem history',
            '• Purchase internet packages with unlimited data',
            '• Choose internet packages that offer high speed',
            '• Let application automatically buys your favorite internet package',
            '• Check your daily internet usage',
            '• Review history of purchased, active or expired internet packages',
            '• Chat to our customer support for friendly assistance',
            '• Easily navigate through all services',
            '• Manage your CPE account and plan',
            '• Use the application with your preferred language',
        ]);

        $releaseNotesZh = implode("\n", [
            '• 管理您的 CG Net 宽带网络',
            '• 为自己或好友充值/兑换余额',
            '• 查看充值/兑换历史记录',
            '• 购买无限流量套餐',
            '• 选择高速上网套餐',
            '• 支持自动续订您喜爱的网络套餐',
            '• 查询每日网络使用情况',
            '• 查看已购买、使用中或已过期的套餐历史',
            '• 在线咨询客服以获取热情协助',
            '• 轻松畅享各项便捷服务',
            '• 管理您的 CPE 账户及方案',
            '• 自由切换您首选的语言进行使用',
        ]);

        $releaseNotesMy = implode("\n", [
            '• မိမိ၏ CG Net အင်တာနက်ကွန်ရက်ကို စီမံခန့်ခွဲနိုင်ခြင်း',
            '• မိမိကိုယ်တိုင် သို့မဟုတ် မိတ်ဆွေများအတွက် လက်ကျန်ငွေဖြည့်သွင်း/လဲလှယ်နိုင်ခြင်း',
            '• လက်ကျန်ငွေဖြည့်သွင်း/လဲလှယ်ထားသော မှတ်တမ်းများကို ကြည့်ရှုနိုင်ခြင်း',
            '• Unlimited data ပါဝင်သော အင်တာနက်ပက်ကေ့ဂျ်များကို ဝယ်ယူနိုင်ခြင်း',
            '• အမြန်နှုန်းမြင့်မားသော အင်တာနက်ပက်ကေ့ဂျ်များကို ရွေးချယ်နိုင်ခြင်း',
            '• မိမိနှစ်သက်သော အင်တာနက်ပက်ကေ့ဂျ်ကို အလိုအလျောက် ဝယ်ယူပေးနိုင်ခြင်း',
            '• နေ့စဉ် အင်တာနက်အသုံးပြုမှုပမာဏကို စစ်ဆေးနိုင်ခြင်း',
            '• ဝယ်ယူထားသော၊ လက်ရှိသုံးနေသော သို့မဟုတ် သက်တမ်းကုန်ဆုံးသွားသော ပက်ကေ့ဂျ်မှတ်တမ်းများကို ကြည့်ရှုနိုင်ခြင်း',
            '• ဖော်ရွေသော အကူအညီများရယူရန် Customer Support နှင့် တိုက်ရိုက်စကားပြောနိုင်ခြင်း',
            '• ဝန်ဆောင်မှုအားလုံးကို လွယ်ကူလျင်မြန်စွာ အသုံးပြုနိုင်ခြင်း',
            '• မိမိ၏ CPE အကောင့်နှင့် plan များကို စီမံခန့်ခွဲနိုင်ခြင်း',
            '• မိမိစိတ်ကြိုက် ဘာသာစကားဖြင့် အက်ပ်ကို အဆင်ပြေစွာ အသုံးပြုနိုင်ခြင်း',
        ]);

        $records = [
            [
                'platform' => 'ios',
                'version' => '2.1.0',
                'minimum_version' => '2.0.0',
                'download_url' => 'https://apps.apple.com/app/id6441234567',
                'release_notes_en' => $releaseNotesEn,
                'release_notes_zh' => $releaseNotesZh,
                'release_notes_my' => $releaseNotesMy,
                'force_update' => false,
                'status' => 'active',
            ],
            [
                'platform' => 'android',
                'version' => '2.1.0',
                'minimum_version' => '2.0.0',
                'download_url' => 'https://play.google.com/store/apps/details?id=com.cgnet.customer',
                'release_notes_en' => $releaseNotesEn,
                'release_notes_zh' => $releaseNotesZh,
                'release_notes_my' => $releaseNotesMy,
                'force_update' => false,
                'status' => 'active',
            ],
            [
                'platform' => 'ios',
                'version' => '2.0.0',
                'minimum_version' => '1.8.0',
                'download_url' => 'https://download.cgnet.com.mm/app/latest.apk',
                'release_notes_en' => $releaseNotesEn,
                'release_notes_zh' => $releaseNotesZh,
                'release_notes_my' => $releaseNotesMy,
                'force_update' => false,
                'status' => 'inactive',
            ],
        ];

        foreach ($records as $record) {
            AppVersion::query()->updateOrCreate(
                [
                    'platform' => $record['platform'],
                    'version' => $record['version'],
                ],
                $record,
            );
        }
    }
}
