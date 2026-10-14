<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Gallery;
use App\Models\Banner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        echo "CMS seeder started\n";

        $this->seedBanners();

        collect([
            [
                'name_en' => 'Announcement',
                'name_zh' => '公告',
                'name_my' => 'ကြေငြာချက်',
                'slug' => 'announcement',
            ],
            [
                'name_en' => 'Awards',
                'name_zh' => '奖项',
                'name_my' => 'ဆုများ',
                'slug' => 'awards',
            ],
            [
                'name_en' => 'Games',
                'name_zh' => '游戏',
                'name_my' => 'ဂိမ်းများ',
                'slug' => 'games',
            ],
            [
                'name_en' => 'Charity',
                'name_zh' => '慈善',
                'name_my' => 'အလှူအတန်း',
                'slug' => 'charity',
            ],
            [
                'name_en' => 'Career',
                'name_my' => 'အလုပ်အကိုင်အခွင့်အလမ်း',
                'name_zh' => '招聘',
                'slug' => 'career',
            ],
        ])->each(fn(array $category) => Category::create($category));

        $this->call([
            NewsSeeder::class,
            ServiceSeeder::class,
            PromotionSeeder::class,
        ]);

        collect([
            [
                'image_url' => 'seeder_images/gallery/thingyan.jfif',
                'label_en' => 'Thingyan Water Festival Celebration',
                'label_my' => 'သင်္ကြန်ရေသဘင်ပွဲတော် ဆင်နွှဲခြင်း',
                'label_zh' => '泼水节欢庆活动',
            ],
            [
                'image_url' => 'seeder_images/gallery/chinesenewyear.jfif',
                'label_en' => 'Traditional Chinese New Year Lion Dance',
                'label_my' => 'တရုတ်နှစ်သစ်ကူး ခြင်္သေ့အက ဖျော်ဖြေပွဲ',
                'label_zh' => '传统农历新年舞狮表演',
            ],
            [
                'image_url' => 'seeder_images/gallery/newyear.jfif',
                'label_en' => 'New Year 2026 Sparkler Celebration',
                'label_my' => '၂၀၂၆ ခုနှစ် သစ်ဆန်းနှစ်သစ်ကူး ကြိုဆိုပွဲတော်',
                'label_zh' => '2026跨年烟花棒庆祝',
            ],
            [
                'image_url' => 'seeder_images/gallery/staffparty.jfif',
                'label_en' => 'Company Staff Party & Office Event',
                'label_my' => 'ဝန်ထမ်းများ၏ ရုံးတွင်း ပျော်ပွဲရွှင်ပွဲ ပွဲတော်',
                'label_zh' => '公司员工聚会与办公室活动',
            ],
            [
                'image_url' => 'seeder_images/gallery/thadingyut.jfif',
                'label_en' => 'Thadingyut Festival of Lights & Lanterns',
                'label_my' => 'သီတင်းကျွတ် မီးထွန်းပွဲတော်နှင့် မီးပုံးပျံလွှတ်တင်ခြင်း',
                'label_zh' => '点灯节与放天灯活动',
            ],
        ])->each(fn(array $gallery) => Gallery::create($gallery));

        collect([
            ['contact_point' => '📞 09 887288882'],
            ['contact_point' => '📞 09 421823339'],
            ['contact_point' => '📩 contact@chenguangnetwork.com'],
            ['contact_point' => '🏠 130/A Eain Twin Hmu Street,Mae Khong Ward,Tachileik.'],
            ['contact_point' => '🧭 Monday to Sunday: 9:00 AM - 9:00 PM'],
            ['contact_point' => '📅 Serving you 7 days a week'],
        ])->each(fn(array $contact) => Contact::create($contact));
    }

    private function seedBanners(): void
    {
        foreach (range(1, 7) as $order) {
            $image = 'seeder_images/banner/banner' . ($order === 1 ? '' : $order - 1) . '.png';
            Banner::create([
                'image_url_en' => $image,
                'image_url_zh' => $image,
                'image_url_my' => $image,
                'type' => 'web_background',
                'sort_order' => $order,
                'is_active' => true,
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
            ]);
        }

        foreach ([1, 2] as $popup) {
            $image = "seeder_images/banner/web_popup/popup{$popup}.png";
            Banner::create([
                'image_url_en' => $image,
                'image_url_zh' => $image,
                'image_url_my' => $image,
                'type' => 'web_popup',
                'sort_order' => 7 + $popup,
                'is_active' => true,
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
            ]);
        }
    }
}
