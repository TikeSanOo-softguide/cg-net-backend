<?php

namespace Database\Seeders;

use App\Enums\ChatFlowOptionAction;
use App\Models\ChatFlowOption;
use App\Models\ChatFlowStep;
use Illuminate\Database\Seeder;

class ChatFlowSeeder extends Seeder
{
    public function run(): void
    {
        echo "Chat flow seeder started\n";

        ChatFlowStep::query()->where('is_start', true)->update(['is_start' => false]);

        $steps = [];

        foreach ($this->steps() as $stepData) {
            $step = ChatFlowStep::query()->updateOrCreate(
                ['name' => $stepData['name']],
                [
                    'message_en' => $stepData['message_en'],
                    'message_my' => $stepData['message_my'],
                    'message_zh' => $stepData['message_zh'],
                    'is_start' => $stepData['is_start'],
                ],
            );

            $steps[$stepData['name']] = $step;
        }

        foreach ($this->options() as $optionData) {
            $step = $steps[$optionData['step']];
            $nextStep = isset($optionData['next_step']) ? $steps[$optionData['next_step']] : null;

            ChatFlowOption::query()->updateOrCreate(
                [
                    'step_id' => $step->id,
                    'option_en' => $optionData['option_en'],
                ],
                [
                    'option_my' => $optionData['option_my'],
                    'option_zh' => $optionData['option_zh'],
                    'action' => $optionData['action'],
                    'next_step_id' => $nextStep?->id,
                    'url' => $optionData['url'] ?? null,
                    'reply_text_en' => $optionData['reply_text_en'] ?? null,
                    'reply_text_my' => $optionData['reply_text_my'] ?? null,
                    'reply_text_zh' => $optionData['reply_text_zh'] ?? null,
                    'sort_order' => $optionData['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return list<array{name: string, message_en: string, message_my: string, message_zh: string, is_start: bool}>
     */
    private function steps(): array
    {
        return [
            [
                'name' => 'Welcome',
                'message_en' => 'Mingalapar! Welcome to CG-NET Chat, which category do you want to inquiry?',
                'message_my' => 'မင်္ဂလာပါ။ CG-NET Chat ကို ကြိုဆိုပါတယ်။ ဘယ်အမျိုးအစားကို မေးမြန်းလိုပါသလဲ။',
                'message_zh' => '您好！欢迎来到 CG-NET 聊天服务。请问您想咨询哪个类别？',
                'is_start' => true,
            ],
            [
                'name' => 'Service',
                'message_en' => 'Thank you for your selection. Which service do you want to inquiry?',
                'message_my' => 'ရွေးချယ်ပေးသည့်အတွက် ကျေးဇူးတင်ပါသည်။ မည်သည့်ဝန်ဆောင်မှုအကြောင်း မေးမြန်းလိုပါသလဲ။',
                'message_zh' => '感谢您的选择。请问您想咨询哪项服务？',
                'is_start' => false,
            ],
            [
                'name' => 'FTTH',
                'message_en' => 'Thank you for your selection. This is FTTH (Dome Pyan Home Fiber Internet) service menu. Please select customer type.',
                'message_my' => 'ရွေးချယ်ပေးသည့်အတွက် ကျေးဇူးတင်ပါသည်။ ဤသည်မှာ FTTH (Dome Pyan အိမ်သုံး ဖိုင်ဘာအင်တာနက်) ဝန်ဆောင်မှုမီနူးဖြစ်ပါသည်။ ဖောက်သည်အမျိုးအစားကို ရွေးချယ်ပါ။',
                'message_zh' => '感谢您的选择。这是 FTTH（Dome Pyan 家庭光纤互联网）服务菜单。请选择客户类型。',
                'is_start' => false,
            ],
            [
                'name' => 'Mobile Service',
                'message_en' => 'Mingalar Par! Welcome to mobile service menu. How can I help you!',
                'message_my' => 'မင်္ဂလာပါ။ မိုဘိုင်းဝန်ဆောင်မှုမီနူးမှ ကြိုဆိုပါတယ်။ ဘာကူညီပေးရမလဲ။',
                'message_zh' => '您好！欢迎使用移动服务菜单。请问有什么可以帮您？',
                'is_start' => false,
            ],
            [
                'name' => 'New Customer',
                'message_en' => 'Thank you for your selection. This is FTTH (Dome Pyan Home Fiber Internet) service menu for new customer. Please select an option.',
                'message_my' => 'ရွေးချယ်ပေးသည့်အတွက် ကျေးဇူးတင်ပါသည်။ ဤသည်မှာ FTTH (Dome Pyan အိမ်သုံး ဖိုင်ဘာအင်တာနက်) ဖောက်သည်အသစ်များအတွက် ဝန်ဆောင်မှုမီနူးဖြစ်ပါသည်။ ရွေးချယ်စရာတစ်ခုကို ရွေးချယ်ပါ။',
                'message_zh' => '感谢您的选择。这是面向 FTTH（Dome Pyan 家庭光纤互联网）新客户的服务菜单。请选择一项。',
                'is_start' => false,
            ],
            [
                'name' => 'Existing Customer',
                'message_en' => 'Dear customer, please contact call center or with Live chat.',
                'message_my' => 'လေးစားရပါသော ဖောက်သည်၊ Call Center သို့ ဆက်သွယ်ပါ သို့မဟုတ် Live Chat ကို အသုံးပြုပါ။',
                'message_zh' => '尊敬的客户，请联系呼叫中心或使用在线聊天功能。',
                'is_start' => false,
            ],
        ];
    }

    /**
     * @return list<array{
     *     step: string,
     *     option_en: string,
     *     option_my: string,
     *     option_zh: string,
     *     action: string,
     *     sort_order: int,
     *     next_step?: string,
     *     url?: string,
     *     reply_text_en?: string,
     *     reply_text_my?: string,
     *     reply_text_zh?: string
     * }>
     */
    private function options(): array
    {
        return [
            [
                'step' => 'Welcome',
                'option_en' => 'Personal',
                'option_my' => 'ကိုယ်ရေးကိုယ်တာ',
                'option_zh' => '个人的',
                'action' => ChatFlowOptionAction::GoToStep->value,
                'next_step' => 'Service',
                'sort_order' => 1,
            ],
            [
                'step' => 'Service',
                'option_en' => 'Home Fiber Internet',
                'option_my' => 'အိမ်သုံးဖိုင်ဘာအင်တာနက်',
                'option_zh' => '家庭光纤宽带',
                'action' => ChatFlowOptionAction::GoToStep->value,
                'next_step' => 'FTTH',
                'sort_order' => 1,
            ],
            [
                'step' => 'Service',
                'option_en' => 'Mobile Services',
                'option_my' => 'မိုဘိုင်းဝန်ဆောင်မှုများ',
                'option_zh' => '移动服务',
                'action' => ChatFlowOptionAction::GoToStep->value,
                'next_step' => 'Mobile Service',
                'sort_order' => 2,
            ],
            [
                'step' => 'Existing Customer',
                'option_en' => 'Live Chat',
                'option_my' => 'တိုက်ရိုက်စကားပြောခန်း',
                'option_zh' => '在线聊天',
                'action' => ChatFlowOptionAction::TransferAgent->value,
                'sort_order' => 1,
            ],
            [
                'step' => 'Existing Customer',
                'option_en' => 'Call Center',
                'option_my' => 'Call Center',
                'option_zh' => '呼叫中心',
                'action' => ChatFlowOptionAction::ReplyText->value,
                'reply_text_en' => 'Call from mobile number 01254355',
                'reply_text_my' => 'မိုဘိုင်းဖုန်းနံပါတ် 01254355 မှ ဆက်သွယ်ပါ။',
                'reply_text_zh' => '请拨打手机号码 01254355 联系客服。',
                'sort_order' => 2,
            ],
            [
                'step' => 'FTTH',
                'option_en' => 'New Customer',
                'option_my' => 'ဖောက်သည်အသစ်',
                'option_zh' => '新客户',
                'action' => ChatFlowOptionAction::GoToStep->value,
                'next_step' => 'New Customer',
                'sort_order' => 1,
            ],
            [
                'step' => 'FTTH',
                'option_en' => 'Existing Customer',
                'option_my' => 'လက်ရှိဖောက်သည်',
                'option_zh' => '现有客户',
                'action' => ChatFlowOptionAction::GoToStep->value,
                'next_step' => 'Existing Customer',
                'sort_order' => 2,
            ],
            [
                'step' => 'New Customer',
                'option_en' => 'Service Information',
                'option_my' => 'ဝန်ဆောင်မှုအချက်အလက်',
                'option_zh' => '服务信息',
                'action' => ChatFlowOptionAction::GoToUrl->value,
                'url' => 'https://cg-net-cms.vercel.app/services',
                'sort_order' => 1,
            ],
            [
                'step' => 'New Customer',
                'option_en' => 'Package Information',
                'option_my' => 'ပက်ကေ့ချ်အချက်အလက်',
                'option_zh' => '套餐信息',
                'action' => ChatFlowOptionAction::GoToUrl->value,
                'url' => 'https://cg-net-cms.vercel.app/packages',
                'sort_order' => 2,
            ],
            [
                'step' => 'Mobile Service',
                'option_en' => 'Chat with Staff',
                'option_my' => 'ဝန်ထမ်းနှင့် စကားပြောရန်',
                'option_zh' => '与工作人员在线交流',
                'action' => ChatFlowOptionAction::TransferAgent->value,
                'sort_order' => 1,
            ],
            [
                'step' => 'Welcome',
                'option_en' => 'Chat with Staff',
                'option_my' => 'Admin နှင့် စကားပြောရန်',
                'option_zh' => '与管理员交谈',
                'action' => ChatFlowOptionAction::TransferAgent->value,
                'sort_order' => 2,
            ],
        ];
    }
}
