<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\SupportContact;
use App\Models\TermAndCondition;
use Illuminate\Database\Seeder;

class GeneralSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "General setting seeder started\n";

        TermAndCondition::query()->updateOrCreate(
            ['id' => 1],
            [
                'title_en' => 'Terms & Conditions',
                'title_zh' => '条款与条件',
                'title_my' => 'စည်းကမ်းနှင့် သတ်မှတ်ချက်များ',

                'description_en' => <<<'TEXT'
                By using the CG-NET mobile app, you agree to use our internet services lawfully and keep your account credentials secure.

                You are responsible for activity under your account. CG-NET may update these terms to improve service quality, security, and compliance.

                We collect only the information needed to provide connectivity, billing, and support. Your data is handled according to our privacy practices.

                Service availability depends on network coverage and plan status. For support, contact CG-NET Help from the app anytime.
                TEXT
                ,
                'description_zh' => <<<'TEXT'
                使用 CG-NET 移动应用即表示您同意合法使用我们的互联网服务，并妥善保护您的账户凭证。

                您需要对账户下的所有活动负责。CG-NET 可能会根据服务质量、安全性和合规要求更新本条款。

                我们仅收集提供网络连接、账单和客户支持所需的信息。您的数据将按照我们的隐私政策进行处理。

                服务可用性取决于网络覆盖范围和套餐状态。如需帮助，您可以随时通过应用联系 CG-NET Help。
                TEXT
                ,
                'description_my' => <<<'TEXT'
                CG-NET မိုဘိုင်းအက်ပ်ကို အသုံးပြုခြင်းဖြင့် ကျွန်ုပ်တို့၏ အင်တာနက်ဝန်ဆောင်မှုများကို တရားဝင်အသုံးပြုရန်နှင့် သင့်အကောင့်အချက်အလက်များကို လုံခြုံစွာ ထိန်းသိမ်းရန် သဘောတူပါသည်။

                သင့်အကောင့်အောက်တွင် ပြုလုပ်သည့် လုပ်ဆောင်ချက်များအားလုံးအတွက် သင်တွင် တာဝန်ရှိပါသည်။ ဝန်ဆောင်မှုအရည်အသွေး၊ လုံခြုံရေးနှင့် စည်းမျဉ်းစည်းကမ်းများနှင့် ကိုက်ညီမှုရှိစေရန် CG-NET သည် ဤစည်းကမ်းချက်များကို အပ်ဒိတ်လုပ်နိုင်ပါသည်။

                ချိတ်ဆက်မှု၊ ငွေတောင်းခံမှုနှင့် ပံ့ပိုးကူညီမှုများအတွက် လိုအပ်သော အချက်အလက်များကိုသာ စုဆောင်းပါသည်။ သင့်ဒေတာများကို ကျွန်ုပ်တို့၏ ကိုယ်ရေးအချက်အလက်ဆိုင်ရာ မူဝါဒများနှင့်အညီ ကိုင်တွယ်ဆောင်ရွက်ပါသည်။

                ဝန်ဆောင်မှုရရှိနိုင်မှုသည် ကွန်ရက်လွှမ်းခြုံမှုနှင့် ပလန်အခြေအနေပေါ်တွင် မူတည်ပါသည်။ အကူအညီလိုအပ်ပါက အက်ပ်မှတစ်ဆင့် CG-NET Help ကို အချိန်မရွေး ဆက်သွယ်နိုင်ပါသည်။
                TEXT
            ,
            ],
        );

        $faqs = [
            [
                'title_en' => 'Which phone numbers can I use?',
                'title_zh' => '哪些手机号码可以使用？',
                'title_my' => 'မည်သည့်ဖုန်းနံပါတ်များကို အသုံးပြုနိုင်ပါသလဲ။',
                'description_en' => 'Myanmar (+95), Thailand (+66), and China (+86) mobile numbers are supported.',
                'description_zh' => '支持缅甸（+95）、泰国（+66）和中国（+86）的手机号码。',
                'description_my' =>
                    'မြန်မာ (+95)၊ ထိုင်း (+66) နှင့် တရုတ် (+86) မိုဘိုင်းဖုန်းနံပါတ်များကို အသုံးပြုနိုင်ပါသည်။',
            ],
            [
                'title_en' => 'I did not receive the OTP.',
                'title_zh' => '我没有收到 OTP。',
                'title_my' => 'OTP မရရှိပါက ဘာလုပ်ရမလဲ။',
                'description_en' => 'Wait a moment and tap Resend. Check your signal and phone number.',
                'description_zh' => '请稍等片刻并点击“重新发送”。请检查网络信号和手机号码。',
                'description_my' =>
                    'ခဏစောင့်ပြီး “ပြန်လည်ပေးပို့ရန်” ကို နှိပ်ပါ။ ဖုန်းလိုင်းနှင့် ဖုန်းနံပါတ်ကို စစ်ဆေးပါ။',
            ],
            [
                'title_en' => 'Why must I accept the Terms & Conditions?',
                'title_zh' => '为什么必须接受条款与条件？',
                'title_my' => 'စည်းကမ်းနှင့် သတ်မှတ်ချက်များကို ဘာကြောင့် သဘောတူရပါသလဲ။',
                'description_en' => 'You must agree before we can send an OTP and create your account.',
                'description_zh' => '您必须同意后，我们才能发送 OTP 并创建您的账户。',
                'description_my' => 'OTP ပေးပို့ပြီး အကောင့်ဖန်တီးရန် သဘောတူရပါမည်။',
            ],
            [
                'title_en' => 'Who can I call for help?',
                'title_zh' => '如需帮助，我可以联系谁？',
                'title_my' => 'အကူအညီလိုအပ်ပါက မည်သူ့ကို ဆက်သွယ်ရမလဲ။',
                'description_en' => 'Call the hotline shown on the login screen for your country.',
                'description_zh' => '请拨打登录页面上显示的您所在国家/地区的客服热线。',
                'description_my' => 'အကောင့်ဝင်သည့်စာမျက်နှာတွင် ဖော်ပြထားသော သင့်နိုင်ငံ၏ Hotline ကို ဆက်သွယ်ပါ။',
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::query()->create($faq);
        }

        foreach (['+95 9 123 456 789', '+95 9 987 654 321', '+95 9 555 666 777'] as $phone) {
            SupportContact::query()->create([
                'phone' => $phone,
            ]);
        }
    }
}
