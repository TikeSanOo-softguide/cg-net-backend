<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
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

                'start_date' => null,
                'end_date' => null,
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
                'slug' => 'tazaungdaing-special-promotion',
                'title_en' => 'Tazaungdaing Special Promotion',
                'title_my' => 'တန်ဆောင်တိုင် အထူးပရိုမိုးရှင်း',
                'title_zh' => '点灯节特别优惠',

                'description_en' => <<<'TEXT'
                    Celebrate the Tazaungdaing Festival with our special promotion and enjoy more convenient Internet services for your everyday needs.

                    During the promotional period, customers can explore our special offers and selected Internet packages designed to provide greater convenience and value. Whether you use the Internet for work, study, entertainment, or staying connected with family and friends, there is an option to suit your needs.

                    Take this opportunity to choose an Internet package that matches your usage and enjoy the benefits available during our Tazaungdaing promotion.

                    Make your Tazaungdaing celebration more connected with our special Internet offers. Check the available promotions and enjoy the benefits during the promotional period.
                    TEXT,

                'description_my' => <<<'TEXT'
                    တန်ဆောင်တိုင်ပွဲတော်အထိမ်းအမှတ်အဖြစ် လူကြီးမင်းတို့၏ နေ့စဉ် Internet အသုံးပြုမှုများ ပိုမိုအဆင်ပြေစေရန် အထူးပရိုမိုးရှင်းအစီအစဉ်ကို ပြုလုပ်ပေးထားပါသည်။

                    ပရိုမိုးရှင်းကာလအတွင်း သတ်မှတ်ထားသော အထူးအစီအစဉ်များနှင့် Internet Package များကို လေ့လာရွေးချယ်နိုင်ပြီး လူကြီးမင်းတို့၏ လိုအပ်ချက်နှင့် ကိုက်ညီသော Package များကို အသုံးပြုနိုင်ပါသည်။

                    အလုပ်လုပ်ခြင်း၊ စာလေ့လာခြင်း၊ ဖျော်ဖြေရေးအစီအစဉ်များ ကြည့်ရှုခြင်းနှင့် မိသားစု၊ မိတ်ဆွေများနှင့် ဆက်သွယ်ခြင်းစသည့် နေ့စဉ် Internet အသုံးပြုမှုများအတွက် မိမိနှင့် သင့်တော်သော Package ကို ရွေးချယ်နိုင်ပါသည်။

                    တန်ဆောင်တိုင်ပွဲတော်ကာလကို ပိုမိုအဆင်ပြေသော Internet ဝန်ဆောင်မှုများနှင့်အတူ ဖြတ်သန်းနိုင်ရန် ကျွန်ုပ်တို့၏ အထူးပရိုမိုးရှင်းအစီအစဉ်များကို ယခုပင် လေ့လာအသုံးချလိုက်ပါ။

                    ပရိုမိုးရှင်းကာလအတွင်း ရရှိနိုင်သော အထူးအစီအစဉ်များကို လက်မလွတ်စေရန် ယခုပင် စုံစမ်းကြည့်ရှုလိုက်ပါ။
                    TEXT,

                'description_zh' => <<<'TEXT'
                    为庆祝点灯节，我们特别推出优惠活动，让您在日常生活中享受更加便捷的互联网服务。

                    在促销期间，客户可以了解我们的特别优惠及指定互联网套餐，并根据自己的使用需求选择合适的服务。

                    无论是工作、学习、娱乐，还是与家人朋友保持联系，都可以选择适合自己的互联网套餐，满足日常网络使用需求。

                    让今年的点灯节与更加便捷的互联网服务相伴。立即查看我们的特别优惠，并在促销期间享受相关福利。
                    TEXT,

                'start_date' => '2025-11-01',
                'end_date' => '2026-11-30',
                'is_active' => true,
                'image_url' => 'seeder_images/promotion/tazaungdaing-promotion.png',
            ],
            [
                'slug' => 'year-end-special-promotion',
                'title_en' => 'Year-End Special Promotion',
                'title_my' => 'နှစ်ကုန် အထူးပရိုမိုးရှင်း',
                'title_zh' => '年末特别优惠',

                'description_en' => <<<'TEXT'
                    Celebrate the end of the year with our special Year-End Promotion and enjoy convenient Internet services for work, entertainment, and staying connected.

                    During the promotional period, customers can explore our special offers and selected Internet packages and choose the services that best match their needs.

                    Whether you are staying connected with family and friends, enjoying online entertainment, working from home, or preparing for the new year, our Internet services help you stay connected throughout the season.

                    Take this opportunity to review our available packages and make the most of our special Year-End offers.

                    End the year with a better-connected experience and get ready for the new year with our special promotion. Check the available offers and enjoy the benefits during the promotional period.
                    TEXT,

                'description_my' => <<<'TEXT'
                    နှစ်ကုန်ကာလအထိမ်းအမှတ်အဖြစ် လူကြီးမင်းတို့အတွက် Internet အသုံးပြုမှုများ ပိုမိုအဆင်ပြေစေရန် နှစ်ကုန် အထူးပရိုမိုးရှင်းအစီအစဉ်ကို ပြုလုပ်ပေးထားပါသည်။

                    ပရိုမိုးရှင်းကာလအတွင်း သတ်မှတ်ထားသော အထူးအစီအစဉ်များနှင့် Internet Package များကို လေ့လာကြည့်ရှုနိုင်ပြီး မိမိ၏ Internet အသုံးပြုမှုပုံစံနှင့် လိုအပ်ချက်နှင့် ကိုက်ညီသော ဝန်ဆောင်မှုများကို ရွေးချယ်နိုင်ပါသည်။

                    နှစ်ကုန်ကာလအတွင်း မိသားစုနှင့် မိတ်ဆွေများနှင့် ဆက်သွယ်ခြင်း၊ Online ဖျော်ဖြေရေးအစီအစဉ်များ အသုံးပြုခြင်း၊ အိမ်မှ အလုပ်လုပ်ခြင်းနှင့် နှစ်သစ်အတွက် ပြင်ဆင်ခြင်းများတွင် Internet ဝန်ဆောင်မှုကို အဆင်ပြေစွာ အသုံးပြုနိုင်ပါသည်။

                    နှစ်ကုန်အထူးပရိုမိုးရှင်းကာလအတွင်း ရရှိနိုင်သော Package များကို လေ့လာပြီး မိမိနှင့် သင့်တော်သော ဝန်ဆောင်မှုကို ရွေးချယ်အသုံးပြုလိုက်ပါ။

                    နှစ်ဟောင်းကို ပိုမိုကောင်းမွန်သော Internet ချိတ်ဆက်မှုနှင့်အတူ နှုတ်ဆက်ပြီး နှစ်သစ်ကို ပိုမိုအဆင်ပြေစွာ စတင်နိုင်ရန် ကျွန်ုပ်တို့၏ နှစ်ကုန် အထူးပရိုမိုးရှင်းကို ယခုပင် လေ့လာလိုက်ပါ။
                    TEXT,

                'description_zh' => <<<'TEXT'
                    为庆祝年末，我们特别推出年末优惠活动，让您在工作、娱乐以及与亲朋好友保持联系时享受更加便捷的互联网服务。

                    在促销期间，客户可以了解我们的特别优惠及指定互联网套餐，并根据自己的实际使用需求选择合适的服务。

                    无论是与家人朋友保持联系、享受在线娱乐、居家办公，还是为新的一年做好准备，我们的互联网服务都能帮助您保持稳定连接。

                    把握年末机会，了解我们目前提供的套餐和特别优惠，并选择适合自己的服务。

                    用更加便捷的互联网体验结束这一年，并为新的一年做好准备。立即查看我们的年末特别优惠，享受活动期间的相关福利。
                    TEXT,

                'start_date' => '2025-12-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
                'image_url' => 'seeder_images/promotion/year-end-promotion.png',
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
    }
}
