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
            'title_en' => 'Internet account expiring soon',
            'title_my' => 'Internet အကောင့် သက်တမ်းကုန်တော့မည်',
            'title_zh' => '互联网账户即将到期',
            'description_en' => "Account {account_number} expires at {endtime}.\nRecharge to stay connected.",
            'description_my' =>
                "အကောင့် {account_number} သည် {endtime} တွင် သက်တမ်းကုန်ဆုံးပါတော့မည်။\nဆက်လက်အသုံးပြုရန် သက်တမ်းတိုးပါ။",
            'description_zh' => "账户 {account_number} 将于 {endtime} 到期。\n请及时充值以保持连接。",
        ];
    }
}
