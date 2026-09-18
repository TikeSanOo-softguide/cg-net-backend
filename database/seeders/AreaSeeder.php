<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed State
        $stateName = 'ရှမ်းပြည်နယ်';
        $stateNames = [
            'name_en' => 'Shan State',
            'name_zh' => '掸邦',
            'name_my' => $stateName,
        ];

        DB::table('states')->updateOrInsert(
            ['name_my' => $stateName],
            [
                ...$stateNames,
                'latitude' => 21.500000,
                'longitude' => 98.000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $shanState = DB::table('states')->where('name_en', $stateNames['name_en'])->first();

        // 2. Seed Regions + Areas
        $regionsData = [
            'မိုင်းလား' => [
                'ဝမ်မိုင်တိုင်းရွာ',
                'ဟိုမိန်းရွာ',
                'ဝမ်မိုင်ဟိုခိုရွာ',
                'ဝမ်ပုန်းရွာ',
                'ဝမ်တောင်းရွာ',
                'ဝမ်လျှမ့်ရွာ',
                'ဝမ်ကပ်ရွာ',
                'ဝမ်လျှို့ရွာစျေးအတွင်း',
                'အာခေးအောက်ရွာ',
                'ဝမ်ဆန်ရွာ',
                'မိုင်းမရွာ',
                'ဝမ်ကောက်ရွာ',
                'ဝမ်ပေါင်ရွာ',
                'ဝမ်လောင်ရွာ',
                'မိုင်းပွန်းရွာ',
                'လင်းအိုင်ရွာ',
                'မိုက်ကော်လုံရွာ',
                'ပန်မန်းရွာ',
                'ပန်ဟော်ရွာ',
                'ပါခါးရွာ',
                'မိုင်းလားမြို့',
                'မိုင်းလားစျေး(မိုင်းလားမြို့)',
                'လျံဒွန့်(မိုင်းလားမြို့)',
                'ဝမ်ကျင်းဖရွာ',
                'ဝမ်ပင်းရွာ',
                'ဝမ်ဟွေရွာ',
                'ဝမ်နွဲ့ရွာ',
            ],
            'ကျင်းခန်း' => [
                'ဝမ်ဟုတ်ရွာ',
                'ဝမ်ခမ်းရွာ',
                'ဝမ်တာရွာ',
                'ဝမ်ယာရွာ',
                'ဝိန်းလုံရွာ',
                'ဝိန်းတိုင်ရွာ',
                'ဝမ်းနားလန်ရွာ',
                'ဝမ်ဟာရွာ',
                'ဝမ်ကပ်ရွာ',
                'ဝမ်လားလုံရွာ',
                'ကျိုင်းခမ်းရွာ',
                'ဝမ်ဟိုနားရွာ',
            ],
            'မိုင်းစော' => [
                'နာဖီးအောက်ရွာ',
                'သန်လန်ရွာ',
                'နားဒဲအောက်ရွာ',
                'ဒီရှီးရွာ',
                'နာငါရွာ',
                'ဝမ်ဖားကျန့်ရွာ(နာဘာနွဲ့)',
                'ရှီးလယ်ရွာ',
                'လဲရှီရွာ',
                'ဗြဲနှေးရွာ',
                'ဝမ်ဆားရွာ',
                'မိုင်းဟဲရွာ',
                'နန့်လင်းရွာ',
                'ဝမ်ဆိုင်းရွာ',
                'ဝမ်ဟိုနားရွာ',
                'မိုင်းဆော',
                'ဘားချဲရွာ',
                'ဘားလယ်ရွာ',
                'မိုင်းနန်းရွာ',
                'မိုင်းအွန်ရွာ',
            ],
        ];

        $regionCoordinates = [
            'မိုင်းလား' => ['latitude' => 21.667678, 'longitude' => 100.039877],
            'ကျင်းခန်း' => ['latitude' => 21.406733, 'longitude' => 100.434334],
            'မိုင်းစော' => ['latitude' => 21.573170, 'longitude' => 100.846286],
        ];

        $regionNames = [
            'မိုင်းလား' => ['name_en' => 'Mongla', 'name_zh' => '勐拉', 'name_my' => 'မိုင်းလား'],
            'ကျင်းခန်း' => ['name_en' => 'Jingkang', 'name_zh' => '景康', 'name_my' => 'ကျင်းခန်း'],
            'မိုင်းစော' => ['name_en' => 'Mongsou', 'name_zh' => '勐扫', 'name_my' => 'မိုင်းစော'],
        ];

        $areaNames = [
            'မိုင်းလား' => [
                ['Wan Mile Tile Village', '勐马乡 · 万迈泰村'],
                ['Ho Main', '勐马乡 · 查满村'],
                ['Wan Mile Ho Kho Village', '勐马乡 · 万敏哈科村'],
                ['Wan Pone', '勐马乡 · 万朋村'],
                ['Wan Dao Village', '勐马乡 · 万笃村'],
                ['Wan Lian Village', '通海乡 · 南排下寨'],
                ['Wan Kat', '勐马乡 · 万嘎村'],
                ['Wan Wsho Market', '勐马乡 · 万围村'],
                ['Arr Khar Out Village', '勐马乡 · 阿古欧村'],
                ['Wan Sam Village', '勐马乡 · 万散村'],
                ['Mong Ma', '勐马乡'],
                ['Wan Kaut Village', '勐马乡 · 万括村'],
                ['Wan Paung Village', '勐马乡 · 万包村'],
                ['Wan Long Village', '勐马乡 · 万朗村'],
                ['Mong Pomn Village', '勐拉乡 · 拜满村'],
                ['Lin Ai Village', '勐临乡 · 仁爱村'],
                ['Mike Kaw Long Village', '勐马乡 · 迈考隆村'],
                ['Ban Mann Village', '勐拉乡 · 拜满村'],
                ['Ban Haw Village', '勐拉乡 · 邓掌村'],
                ['Bakha Village', '勐拉乡 · 巴卡村'],
                ['Mongla Town', '勐拉乡 · 勐拉'],
                ['Mongla Market', '勐拉乡 · 勐拉'],
                ['Lian Don', '勐拉乡 · 联栋'],
                ['Wan Jin Fa Village', '勐拉乡 · 万金发村'],
                ['Wan Pinn Village', '彬槟乡 · 万宾村'],
                ['Wan Hway Village', '彬槟乡 · 万回村'],
                ['Wan Nway Village', '彬槟乡 · 万南村'],
            ],
            'ကျင်းခန်း' => [
                ['Wan Hoke Village', '温龙乡 · 万梵村'],
                ['Wan Khan Village', '温龙乡 · 万湖村'],
                ['Wan Tar Village', '温龙乡 · 万达村'],
                ['Wan Yar Village', '温龙乡 · 万亚村'],
                ['Wain Lomg Village', '万达乡 · 万迈村'],
                ['Wain Tile Village', '温龙乡 · 万迫村'],
                ['Wann Narr Lan Village', '温龙乡 · 万纳朗村'],
                ['Wan Har Village', '怒崩乡 · 万咱村'],
                ['Wan Kat Village', '怒崩乡 · 万嘎村'],
                ['Wan Lar Lomg Village', '温龙乡 · 拉麻村'],
                ['Jingkang Village', '万梨聘乡 · 景康'],
                ['Wan Ho Nar Village', '景康乡 · 万霍纳村'],
            ],
            'မိုင်းစော' => [
                ['Nar Phee Out', '迪斯乡 · 南油下寨'],
                ['Than Lan', '迪斯乡 · 习连村'],
                ['Na Dee Out', '迪斯乡 · 南赖下寨'],
                ['Di Shee', '迪斯乡 · 迪斯村'],
                ['Na Ngar', '迪斯乡 · 南安村'],
                ['Wan Phar Kyant (Nar Bar Nwei)', '迪斯乡 · 万帕章村'],
                ['Shee Leal', '迪斯乡 · 习连村'],
                ['Lae Shee', '迪斯乡 · 联席村'],
                ['Byae Nnae', '迪斯乡 · 别捏村'],
                ['Wan Sarr', '勐索乡 · 万沙村'],
                ['Mong Hel', '勐索乡 · 孟赫村'],
                ['Nan Lin', '勐索乡 · 南岭村'],
                ['Wan Sile', '勐索乡 · 万塞村'],
                ['Wan Ho Nar', '勐索乡 · 力管纳村'],
                ['Mongsou', '勐索乡 · 勐索村'],
                ['Bar Chael', '勐索乡 · 巴切村'],
                ['Bar Leal', '勐索乡 · 巴联村'],
                ['Mong Nan', '勐索乡 · 孟楠村'],
                ['Mong Omm', '勐索乡 · 勐基村'],
            ],
        ];

        $areaCoordinates = [
            'မိုင်းလား' => [
                [21.584775, 99.870711],
                [21.586233, 99.871350],
                [21.587788, 99.872619],
                [21.584451, 99.878210],
                [21.593150, 99.876066],
                [21.604208, 99.891141],
                [21.609226, 99.895912],
                [21.612560, 99.910125],
                [21.602297, 99.925814],
                [21.630463, 99.926800],
                [21.632380, 99.926538],
                [21.634632, 99.928539],
                [21.641992, 99.936779],
                [21.638083, 99.956771],
                [21.603095, 100.013907],
                [21.624214, 100.031946],
                [21.655065, 99.958746],
                [21.646937, 99.987156],
                [21.648991, 99.992580],
                [21.658945, 99.985073],
                [21.675576, 100.024542],
                [21.670777, 100.017911],
                [21.667146, 100.017476],
                [21.672592, 100.012825],
                [21.677669, 100.055568],
                [21.684409, 100.073418],
                [21.694358, 100.103567],
            ],
            'ကျင်းခန်း' => [
                [21.379399, 100.340828],
                [21.392027, 100.355065],
                [21.396261, 100.372429],
                [21.400896, 100.371228],
                [21.395222, 100.382548],
                [21.400656, 100.388723],
                [21.404391, 100.398623],
                [21.409206, 100.394020],
                [21.409865, 100.396637],
                [21.403693, 100.415671],
                [21.413821, 100.436350],
                [21.419195, 100.459934],
            ],
            'မိုင်းစော' => [
                [21.469050, 100.731708],
                [21.469165, 100.736457],
                [21.484261, 100.749921],
                [21.502350, 100.771661],
                [21.503787, 100.776669],
                [21.512890, 100.783687],
                [21.530077, 100.808293],
                [21.532572, 100.809365],
                [21.542312, 100.824193],
                [21.544767, 100.820354],
                [21.557920, 100.828813],
                [21.564504, 100.828002],
                [21.569413, 100.832209],
                [21.560673, 100.843483],
                [21.571349, 100.845541],
                [21.585178, 100.848946],
                [21.588011, 100.852738],
                [21.614188, 100.885535],
                [21.645304, 100.911410],
            ],
        ];

        foreach ($regionsData as $regionName => $areas) {
            $coordinates = $regionCoordinates[$regionName];

            // Seed Region
            DB::table('regions')->updateOrInsert(
                [
                    'name_my' => $regionName,
                    'state_id' => $shanState->id,
                ],
                [
                    ...$regionNames[$regionName],
                    'state_id' => $shanState->id,
                    'latitude' => $coordinates['latitude'],
                    'longitude' => $coordinates['longitude'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $region = DB::table('regions')
                ->where('name_my', $regionName)
                ->where('state_id', $shanState->id)
                ->first();

            // Seed Areas
            foreach ($areas as $index => $areaName) {
                [$areaLatitude, $areaLongitude] = $areaCoordinates[$regionName][$index];
                [$areaNameEn, $areaNameZh] = $areaNames[$regionName][$index];

                DB::table('areas')->updateOrInsert(
                    [
                        'name_my' => $areaName,
                        'region_id' => $region->id,
                    ],
                    [
                        'name_en' => $areaNameEn,
                        'name_zh' => $areaNameZh,
                        'name_my' => $areaName,
                        'region_id' => $region->id,
                        'latitude' => $areaLatitude,
                        'longitude' => $areaLongitude,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }
}
