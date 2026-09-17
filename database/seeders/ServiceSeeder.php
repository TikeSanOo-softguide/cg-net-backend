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
                TEXT,
                'description_zh' => <<<'TEXT'
                缅甸宽带服务，提供 20Mbps、50Mbps、100Mbps 和 150Mbps 多种速率套餐，满足稳定的家庭上网需求。

                ⏱️ 20Mbps、50Mbps、100Mbps 和 150Mbps 套餐
                🛡️ 99.9% 网络稳定度保证
                🎧 全天候技术支持
                🔧 免费安装（适用于 6 个月和 1 年方案）
                TEXT,
                'description_my' => <<<'TEXT'
                အိမ်သုံးအင်တာနက်အတွက် 20Mbps၊ 50Mbps၊ 100Mbps နှင့် 150Mbps မြန်နှုန်းအစီအစဉ်များ ပါဝင်သော ဝန်ဆောင်မှု။

                ⏱️ 20Mbps၊ 50Mbps၊ 100Mbps နှင့် 150Mbps အစီအစဉ်များ
                🛡️ ၉၉.၉% ကွန်ရက် တည်ငြိမ်မှု အာမခံချက်
                🎧 နေ့ညမပြတ် နည်းပညာ အကူအညီ
                🔧 တပ်ဆင်ခအခမဲ့ (၆ လနှင့် ၁ နှစ် အစီအစဉ်များအတွက်)
                TEXT,
                'image_url' => 'seeder_images/service/myanmar-service.png',
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
                TEXT,
                'description_zh' => <<<'TEXT'
                晨光网宽带服务，提供 50Mbps 和 100Mbps 速率套餐，满足稳定的网络连接需求。

                🗄️ 50Mbps 和 100Mbps 套餐
                🌐 静态 IP 地址
                👤 全天候技术支持
                🛡️ SLA 保障
                TEXT,
                'description_my' => <<<'TEXT'
                50Mbps နှင့် 100Mbps မြန်နှုန်းအစီအစဉ်များဖြင့် ယုံကြည်စိတ်ချရသော ချိတ်ဆက်မှုကို ပေးစွမ်းသည့် ဝန်ဆောင်မှု။

                🗄️ 50Mbps နှင့် 100Mbps အစီအစဉ်များ
                🌐 တည်ငြိမ်သော IP လိပ်စာများ
                👤 နေ့ညမပြတ် နည်းပညာ အကူအညီ
                🛡️ တပ်ဆင်ခအခမဲ့ (၆ လနှင့် ၁ နှစ် အစီအစဉ်များအတွက်)
                TEXT,
                'image_url' => 'seeder_images/service/china-service.png',
                'status' => 'published',
                'slug' => 'chen-guang-network',
            ],

            [
                'title_en' => 'CG-NET Network',
                'title_zh' => 'CG-NET 家庭网',
                'title_my' => 'CG-NETလိုင်း',
                'description_en' => <<<'TEXT'
                Reliable CG-NET home internet service with a 20Mbps plan and convenient home connectivity.

                🗄️ 20Mbps home internet plan
                ♾️ Unlimited data
                📡 Receiver included
                ⚙️ Free installation (For 6-month and 1-year plans)
                TEXT,
                'description_zh' => <<<'TEXT'
                可靠的 CG-NET 家庭宽带服务，提供 20Mbps 套餐，为家庭提供便捷的网络连接。

                🗄️ 20Mbps 家庭宽带套餐
                ♾️ 不限流量
                📡 包含接收器
                ⚙️ 免费安装（适用于 6 个月和 1 年方案）
                TEXT,
                'description_my' => <<<'TEXT'
                20Mbps အစီအစဉ်ဖြင့် အိမ်သုံးအတွက် ယုံကြည်စိတ်ချရပြီး အဆင်ပြေသော CG-NET အင်တာနက်ဝန်ဆောင်မှု။

                🗄️ 20Mbps အိမ်သုံးအင်တာနက် အစီအစဉ်
                ♾️ အကန့်အသတ်မဲ့ ဒေတာ
                📡 လက်ခံစက် ပါဝင်
                ⚙️ တပ်ဆင်ခအခမဲ့ (၆ လနှင့် ၁ နှစ် အစီအစဉ်များအတွက်)
                TEXT,
                'image_url' => 'seeder_images/service/CG-service.png',
                'status' => 'published',
                'slug' => 'cg-net-network',
            ],

            [
                'title_en' => 'IPTV',
                'title_zh' => 'IPTV',
                'title_my' => 'IPTV',
                'description_en' => <<<'TEXT'
                    Digital television service featuring 288 channels with high-definition digital TV content.

                    📶 288 channels
                    ♾️ HD digital TV content
                    📺 IPTV service
                    👨‍🔧 Easy installation
                    TEXT,
                'description_zh' => <<<'TEXT'
                    提供 288 个频道的数字电视服务，享受高清数字电视内容。

                    📶 288 个频道
                    ♾️ 高清数字电视内容
                    📺 IPTV 服务
                    👨‍🔧 轻松安装
                    TEXT,
                'description_my' => <<<'TEXT'
                    ချန်နယ် ၂၈၈ ခုပါဝင်သော ဒစ်ဂျစ်တယ် ရုပ်မြင်သံကြား ဝန်ဆောင်မှုနှင့် ကြည်လင်ပြတ်သားသော HD အကြောင်းအရာများ။

                    📶 ၂၈၈ ချန်နယ်
                    ♾️ HD ဒစ်ဂျစ်တယ် ရုပ်မြင်သံကြား အကြောင်းအရာ
                    📺 IPTV ဝန်ဆောင်မှု
                    👨‍🔧 လွယ်ကူသော တပ်ဆင်မှု
                    TEXT,
                'image_url' => 'seeder_images/service/iptv.png',
                'status' => 'published',
                'slug' => 'iptv-service',
            ],
            [
                'title_en' => 'CG-NET Router',
                'title_zh' => 'CG-NET 路由器',
                'title_my' => 'CG-NET ရောက်တာ',
                'description_en' => <<<'TEXT'
                High-performance Wi-Fi router offering extended signal range, high-speed coverage, and seamless multi-device connectivity.

                📶 High-speed Wi-Fi performance
                📡 Extended signal coverage
                ⚡ Multi-device optimization
                👨‍🔧 Professional setup & support
                TEXT,
                'description_zh' => <<<'TEXT'
                高性能 Wi-Fi 路由器，提供更广的网络信号覆盖、高速连接以及无缝多设备接入。

                📶 高速 Wi-Fi 性能
                📡 强劲信号广覆盖
                ⚡ 多设备连接优化
                👨‍🔧 专业安装与技术支持
                TEXT,
                'description_my' => <<<'TEXT'
                ကျယ်ဝန်းသော လိုင်းလွှမ်းခြုံမှု၊ မြန်ဆန်သော လိုင်းအမြန်နှုန်းနှင့် စက်ပစ္စည်းအများအပြားကို အဆင်ပြေစွာ ချိတ်ဆက်နိုင်သည့် စွမ်းဆောင်ရည်မြင့် CG-NET ရောက်တာ။

                📶 မြန်ဆန်သော Wi-Fi စွမ်းဆောင်ရည်
                📡 ကျယ်ဝန်းသော လိုင်းလွှမ်းခြုံမှု
                ⚡ စက်ပစ္စည်းအများအပြား သီးသန့်ချိတ်ဆက်နိုင်မှု
                👨‍🔧 ကျွမ်းကျင်သော တပ်ဆင်မှုနှင့် အကူအညီ
                TEXT,
                'image_url' => 'seeder_images/service/router.png',
                'status' => 'published',
                'slug' => 'cg-net-router',
            ],
            [
                'title_en' => 'DIA - Dedicated Internet Access',
                'title_zh' => 'DIA 专线网络',
                'title_my' => 'DIA - သီးသန့် အင်တာနက် အသုံးပြုခွင့်',
                'description_en' => <<<'TEXT'
                    High-speed, reliable, and secure Dedicated Internet Access tailored for business performance and seamless connectivity.

                    🚀 Guaranteed bandwidth & symmetrical speeds
                    🔒 Secure & private dedicated line
                    📈 High reliability with minimal latency
                    👨‍🔧 24/7 technical support & monitoring
                    TEXT,
                'description_zh' => <<<'TEXT'
                    高速、稳定且安全的专线网络服务，专为企业需求打造，提供卓越的连接体验。

                    🚀 独享带宽与上下行对称网速
                    🔒 安全私密的专用网络连接
                    📈 高稳定性与极低延迟
                    👨‍🔧 24/7 全天候技术支持与监控
                    TEXT,
                'description_my' => <<<'TEXT'
                    စီးပွားရေးလုပ်ငန်းများအတွက် အထူးသီးသန့် စိတ်ချယုံကြည်ရပြီး လုံခြုံမှုရှိသော မြန်နှုန်းမြင့် DIA အင်တာနက်ဝန်ဆောင်မှု။

                    🚀 အာမခံချက်ရှိသော လိုင်းအမြန်နှုန်းနှင့် သီးသန့် ဘန်းဝစ်
                    🔒 လုံခြုံစိတ်ချရသော သီးသန့်လိုင်းစနစ်
                    📈 တည်ငြိမ်မှုရှိပြီး ကြလွန်မှုနည်းပါးခြင်း
                    👨‍🔧 ၂၄ နာရီပတ်လုံး ကျွမ်းကျင်သူများ၏ နည်းပညာအကူအညီ
                    TEXT,
                'image_url' => 'seeder_images/service/DIA-service.png',
                'status' => 'published',
                'slug' => 'dedicated-internet-access',
            ]
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(['slug' => $service['slug']], $service);
        }
    }
}
