<?php

namespace Database\Factories;

use App\Enums\NotificationTemplateType;
use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => NotificationTemplateType::BillAlert,
            'title_en' => 'FTTH bill due soon',
            'title_my' => 'FTTH ဘေလ်ပေးချေရန် နီးကပ်လာပါပြီ',
            'title_zh' => 'FTTH 账单即将到期',
            'description_en' => 'Your bill for account {account_number} is due on {due_date}. Tap to view details.',
            'description_my' => 'အကောင့် {account_number} အတွက် ဘေလ်ကို {due_date} တွင် ပေးဆောင်ရပါမည်။ အသေးစိတ်ကြည့်ရန် နှိပ်ပါ။',
            'description_zh' => '账户 {account_number} 的账单将于 {due_date} 到期。点击查看详情。',
        ];
    }
}
