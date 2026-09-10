<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'title_en' => 'Myanmar Network',
                'title_zh' => '缅甸网络',
                'title_my' => 'မြန်မာလိုင်း',

                'description_en' => <<<'TEXT'
                Myanmar broadband service with 20Mbps, 50Mbps, 100Mbps, and 150Mbps speed plans for reliable home internet.

                ⏱️ 20Mbps, 50Mbps, 100Mbps, and 150Mbps plans
                🛡️ 99.9% Network Uptime
                🎧 24/7 technical support
                🔧 Free installation (For 6-month and 1-year plans)
                TEXT
                ,
                'description_zh' => <<<'TEXT'
                缅甸宽带服务，提供 20Mbps、50Mbps、100Mbps 和 150Mbps 多种速率套餐，满足稳定的家庭上网需求。

                ⏱️ 20Mbps、50Mbps、100Mbps 和 150Mbps 套餐
                🛡️ 99.9% 网络稳定度保证
                🎧 全天候技术支持
                🔧 免费安装（适用于 6 个月和 1 年方案）
                TEXT
                ,
                'description_my' => <<<'TEXT'
                အိမ်သုံးအင်တာနက်အတွက် 20Mbps၊ 50Mbps၊ 100Mbps နှင့် 150Mbps မြန်နှုန်းအစီအစဉ်များ ပါဝင်သော ဝန်ဆောင်မှု။

                ⏱️ 20Mbps၊ 50Mbps၊ 100Mbps နှင့် 150Mbps အစီအစဉ်များ
                🛡️ ၉၉.၉% ကွန်ရက် တည်ငြိမ်မှု အာမခံချက်
                🎧 နေ့ညမပြတ် နည်းပညာ အကူအညီ
                🔧 တပ်ဆင်ခအခမဲ့ (၆ လနှင့် ၁ နှစ် အစီအစဉ်များအတွက်)
                TEXT
                ,
                'image_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80&fit=crop',
                'status' => 'published',
                'slug' => 'myanmar-network',
            ],

            [
                'title_en' => 'Chen Guang Network',
                'title_zh' => '晨光网',
                'title_my' => 'တရုတ်လိုင်း',

                'description_en' => <<<'TEXT'
                Chen Guang broadband service offering 50Mbps and 100Mbps plans with reliable connectivity.

                🗄️ 50Mbps and 100Mbps plans
                🌐 Static IP addresses
                👤 24/7 technical support
                🛡️ Free installation (For 6-month and 1-year plans)
                TEXT
                ,
                'description_zh' => <<<'TEXT'
                晨光网宽带服务，提供 50Mbps 和 100Mbps 速率套餐，满足稳定的网络连接需求。

                🗄️ 50Mbps 和 100Mbps 套餐
                🌐 静态 IP 地址
                👤 全天候技术支持
                🛡️ SLA 保障
                TEXT
                ,
                'description_my' => <<<'TEXT'
                50Mbps နှင့် 100Mbps မြန်နှုန်းအစီအစဉ်များဖြင့် ယုံကြည်စိတ်ချရသော ချိတ်ဆက်မှုကို ပေးစွမ်းသည့် ဝန်ဆောင်မှု။

                🗄️ 50Mbps နှင့် 100Mbps အစီအစဉ်များ
                🌐 တည်ငြိမ်သော IP လိပ်စာများ
                👤 နေ့ညမပြတ် နည်းပညာ အကူအညီ
                🛡️ တပ်ဆင်ခအခမဲ့ (၆ လနှင့် ၁ နှစ် အစီအစဉ်များအတွက်)
                TEXT
                ,
                'image_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80&fit=crop',
                'status' => 'published',
                'slug' => 'chen-guang-network',
            ],

            [
                'title_en' => 'CG-NET Network',
                'title_zh' => 'CG-NET 家庭网',
                'title_my' => 'CG-NETလိုင်း',

                'description_en' => <<<'TEXT'
                Reliable CG-NET home internet service with a 20Mbps plan and convenient home connectivity.

                📡 20Mbps home internet plan
                ♾️ Unlimited data
                🛜 Receiver included
                ⚙️ Free installation (For 6-month and 1-year plans)
                TEXT
                ,
                'description_zh' => <<<'TEXT'
                可靠的 CG-NET 家庭宽带服务，提供 20Mbps 套餐，为家庭提供便捷的网络连接。

                📡 20Mbps 家庭宽带套餐
                ♾️ 不限流量
                🛜 包含接收器
                ⚙️ 免费安装（适用于 6 个月和 1 年方案）
                TEXT
                ,
                'description_my' => <<<'TEXT'
                20Mbps အစီအစဉ်ဖြင့် အိမ်သုံးအတွက် ယုံကြည်စိတ်ချရပြီး အဆင်ပြေသော CG-NET အင်တာနက်ဝန်ဆောင်မှု။

                📡 20Mbps အိမ်သုံးအင်တာနက် အစီအစဉ်
                ♾️ အကန့်အသတ်မဲ့ ဒေတာ
                🛜 လက်ခံစက် ပါဝင်
                ⚙️ တပ်ဆင်ခအခမဲ့ (၆ လနှင့် ၁ နှစ် အစီအစဉ်များအတွက်)
                TEXT
                ,
                'image_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80&fit=crop',
                'status' => 'published',
                'slug' => 'cg-net-network',
            ],

            [
                'title_en' => 'Other Services',
                'title_zh' => '其他服务',
                'title_my' => 'အခြားဝန်ဆောင်မှုများ',

                'description_en' => <<<'TEXT'
                Digital television service featuring 288 channels, bundled with extra CG-NET add-on network booster and extension services.

                📶 288 channels
                ♾️ HD digital TV content
                🛜 IPTV service
                ⚙️ Easy installation
                📡 High-speed network extension
                ⚙️ Easy configuration and setup with CG-NET add-on service
                TEXT
                ,
                'description_zh' => <<<'TEXT'
                提供 288 个频道的数字电视服务，并搭配 CG-NET 附加网络加速和扩展服务。

                📶 288 个频道
                ♾️ 高清数字电视内容
                🛜 IPTV 服务
                ⚙️ 轻松安装
                📡 高速网络扩展
                ⚙️ 借助 CG-NET 附加服务，轻松进行配置与设置
                TEXT
                ,
                'description_my' => <<<'TEXT'
                ချန်နယ် ၂၈၈ ခုပါဝင်သော ဒစ်ဂျစ်တယ် ရုပ်မြင်သံကြား ဝန်ဆောင်မှုနှင့်အတူ CG-NET ထပ်ဆောင်း ကွန်ရက်မြှင့်တင်မှုနှင့် တိုးချဲ့ဝန်ဆောင်မှုများ။

                📶 ၂၈၈ ချန်နယ်
                ♾️ HD ဒစ်ဂျစ်တယ် ရုပ်မြင်သံကြား အကြောင်းအရာ
                🛜 IPTV ဝန်ဆောင်မှု
                ⚙️ လွယ်ကူသော တပ်ဆင်မှု
                📡 အမြန်နှုန်းမြင့် ကွန်ရက် တိုးချဲ့မှု
                ⚙️ CG-NET Add-on ဝန်ဆောင်မှုဖြင့် လွယ်ကူရိုးရှင်းစွာ ချိန်ညှိပြင်ဆင်နိုင်ပါသည်
                TEXT
                ,
                'image_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80&fit=crop',
                'status' => 'published',
                'slug' => 'other-services',
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(['slug' => $service['slug']], $service);
        }
    }
}
