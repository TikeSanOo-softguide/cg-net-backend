<?php

namespace Database\Seeders;

use App\Enums\NotificationTemplateType;
use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        echo "Notification template seeder started\n";

        foreach ([
            [
                'type' => NotificationTemplateType::BillAlert,
                'title_en' => 'FTTH bill due soon',
                'title_my' => 'FTTH ဘေလ်ပေးချေရန် နီးကပ်လာပါပြီ',
                'title_zh' => 'FTTH 账单即将到期',
                'description_en' => 'Your bill for account {account_number} is due on {due_date}. Tap to view details.',
                'description_my' => 'အကောင့် {account_number} အတွက် ဘေလ်ကို {due_date} တွင် ပေးဆောင်ရပါမည်။ အသေးစိတ်ကြည့်ရန် နှိပ်ပါ။',
                'description_zh' => '账户 {account_number} 的账单将于 {due_date} 到期。点击查看详情。',
            ],
            [
                'type' => NotificationTemplateType::FtthBillPaymentProcessing,
                'title_en' => 'Bill payment processing',
                'title_my' => 'ငွေပေးချေမှုကို လုပ်ဆောင်နေပါသည်',
                'title_zh' => '账单支付处理中',
                'description_en' => 'Your {amount} payment for account {account_number} is being processed.',
                'description_my' => 'အကောင့် {account_number} အတွက် {amount} ငွေပေးချေမှုကို လုပ်ဆောင်နေပါသည်။',
                'description_zh' => '账户 {account_number} 的 {amount} 付款正在处理中。',
            ],
            [
                'type' => NotificationTemplateType::FtthBillPaymentCompleted,
                'title_en' => 'Bill payment successful',
                'title_my' => 'ဘေလ်ပေးချေမှု အောင်မြင်ပါသည်',
                'title_zh' => '账单支付成功',
                'description_en' => 'Your {amount} payment for account {account_number} was successful.',
                'description_my' => 'အကောင့် {account_number} အတွက် {amount} ဘေလ်ပေးချေမှု အောင်မြင်ပါသည်။',
                'description_zh' => '账户 {account_number} 的 {amount} 账单支付成功。',
            ],
            [
                'type' => NotificationTemplateType::FtthBillPaymentRefunded,
                'title_en' => 'Bill payment failed',
                'title_my' => 'ဘေလ်ပေးချေမှု မအောင်မြင်ပါ',
                'title_zh' => '账单支付失败',
                'description_en' => 'Your {amount} payment for account {account_number} could not be completed. The amount has been refunded to your wallet.',
                'description_my' => 'အကောင့် {account_number} အတွက် {amount} ဘေလ်ပေးချေမှု မအောင်မြင်ပါ။ ငွေပမာဏကို သင့်ပိုက်ဆံအိတ်သို့ ပြန်အမ်းပြီးပါပြီ။',
                'description_zh' => '账户 {account_number} 的 {amount} 付款未能完成，款项已退还至您的钱包。',
            ],
        ] as $template) {
            $type = $template['type'];
            unset($template['type']);

            NotificationTemplate::query()->firstOrCreate(
                ['type' => $type->value],
                $template,
            );
        }
    }
}
