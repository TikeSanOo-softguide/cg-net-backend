<?php

namespace Database\Seeders;

use App\Enums\QuickReplyCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuickReplySeeder extends Seeder
{
    public function run(): void
    {
        echo "Quick reply seeder started\n";

        foreach ($this->quickReplies() as $reply) {
            DB::table('quick_replies')->updateOrInsert(
                ['keyword' => $reply['keyword']],
                array_merge($reply, [
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]),
            );
        }
    }

    /**
     * @return list<array{keyword: string, category: string, response_en: string, response_my: string, response_zh: string}>
     */
    private function quickReplies(): array
    {
        return [
            [
                'keyword' => 'Welcome',
                'category' => QuickReplyCategory::Welcome->value,
                'response_en' => 'Hello! Welcome to CG-Net. How may I assist you?',
                'response_my' => 'မင်္ဂလာပါ။ CG-Net မှ ကြိုဆိုပါတယ်။ ဘယ်များ ကူညီဆောင်ရွက်ပေးရမလဲ။',
                'response_zh' => '您好，欢迎来到 CG-Net。请问有什么我可以帮您的吗？',
            ],
            [
                'keyword' => 'No Internet',
                'category' => QuickReplyCategory::Internet->value,
                'response_en' => "We're sorry to hear you're not getting internet. Let's troubleshoot this together. First, please try restarting your modem and router.",
                'response_my' => 'အင်တာနက်အသုံးပြု၍ မရသည့်အတွက် တောင်းပန်ပါတယ်။ Modem နှင့် Router ကို Restart ပြုလုပ်ပြီး ပြန်လည်စမ်းသပ်ပေးပါ။',
                'response_zh' => '很抱歉您无法连接互联网。请先重新启动您的调制解调器和路由器，然后再次尝试连接。',
            ],
            [
                'keyword' => 'Slow Internet',
                'category' => QuickReplyCategory::Internet->value,
                'response_en' => "I understand your internet is running slowly. Let's check a few things to improve your connection.",
                'response_my' => 'အင်တာနက်နှေးကွေးနေသည်ကို နားလည်ပါတယ်။ Connection ပိုမိုကောင်းမွန်စေရန် အချက်အချို့ကို စစ်ဆေးပေးပါမယ်။',
                'response_zh' => '我了解您的网络运行缓慢。让我们检查几个方面来改善您的连接。',
            ],
            [
                'keyword' => 'Payment Confirmation',
                'category' => QuickReplyCategory::Payment->value,
                'response_en' => 'Your payment has been confirmed. Your service will remain active.',
                'response_my' => 'သင့်ငွေပေးချေမှုကို အတည်ပြုပြီးပါပြီ။ သင့်ဝန်ဆောင်မှု ဆက်လက်အသုံးပြုနိုင်ပါတယ်။',
                'response_zh' => '您的付款已经确认。您的服务将继续保持有效。',
            ],
            [
                'keyword' => 'Package Information',
                'category' => QuickReplyCategory::Package->value,
                'response_en' => 'Your package is currently in transit. You can track it using the tracking number on our website or in the app.',
                'response_my' => 'သင့် Package သည် ပို့ဆောင်နေဆဲဖြစ်ပါတယ်။ Website သို့မဟုတ် App မှ Tracking Number ဖြင့် စစ်ဆေးနိုင်ပါတယ်။',
                'response_zh' => '您的套餐目前正在运输中。您可以通过网站或应用程序中的追踪号码查看。',
            ],
            [
                'keyword' => 'Router Restart',
                'category' => QuickReplyCategory::Technical->value,
                'response_en' => 'Please unplug your router, wait 30 seconds, and plug it back in. This can help resolve many connection issues.',
                'response_my' => 'Router ကို ဖြုတ်ပြီး စက္ကန့် ၃၀ ခန့်စောင့်ပြီး ပြန်တပ်ပေးပါ။ Connection ပြဿနာများကို ဖြေရှင်းနိုင်ပါတယ်။',
                'response_zh' => '请拔掉路由器电源，等待30秒，然后重新插入。这可以帮助解决许多连接问题。',
            ],
            [
                'keyword' => 'Conversation Closed',
                'category' => QuickReplyCategory::Closing->value,
                'response_en' => 'Thank you for contacting CG-NET Support. This conversation is now closed. If you need more help, please start a new chat.',
                'response_my' => 'CG-NET Support သို့ ဆက်သွယ်ပေးသည့်အတွက် ကျေးဇူးတင်ပါသည်။ ဤစကားပြောဆိုမှုကို ပိတ်လိုက်ပါပြီ။ အကူအညီထပ်မံလိုအပ်ပါက စကားပြောခန်းအသစ် စတင်နိုင်ပါသည်။',
                'response_zh' => '感谢您联系 CG-NET 客服。本次对话现已结束。如需进一步帮助，请开始新的聊天。',
            ],
        ];
    }
}
