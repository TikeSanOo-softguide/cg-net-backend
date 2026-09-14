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
            'မိုင်းလား' => ['latitude' => 21.666667, 'longitude' => 100.000000],
            'ကျင်းခန်း' => ['latitude' => 21.550000, 'longitude' => 100.150000],
            'မိုင်းစော' => ['latitude' => 21.572760, 'longitude' => 100.846440],
        ];

        $regionNames = [
            'မိုင်းလား' => ['name_en' => 'Mong La', 'name_zh' => '勐拉', 'name_my' => 'မိုင်းလား'],
            'ကျင်းခန်း' => ['name_en' => 'Keng Kham', 'name_zh' => '景康', 'name_my' => 'ကျင်းခန်း'],
            'မိုင်းစော' => ['name_en' => 'Mong Hsu', 'name_zh' => '勐扫', 'name_my' => 'မိုင်းစော'],
        ];

        $areaNames = [
            'မိုင်းလား' => [
                ['Wan Mai Taing', '万迈丁'],
                ['Ho Mein', '霍敏'],
                ['Wan Mai Ho Kho', '万迈霍科'],
                ['Wan Pun', '万奔'],
                ['Wan Taung', '万东'],
                ['Wan Hlyam', '万良'],
                ['Wan Kap', '万甲'],
                ['Wan Hlyo Market', '万略市场'],
                ['A Khe Auk', '阿克奥'],
                ['Wan San', '万山'],
                ['Mong Ma', '勐玛'],
                ['Wan Kauk', '万高'],
                ['Wan Paung', '万邦'],
                ['Wan Laung', '万隆'],
                ['Mong Pun', '勐奔'],
                ['Lin Ai', '林艾'],
                ['Mai Kaw Lung', '迈高隆'],
                ['Pan Man', '班曼'],
                ['Pan Haw', '班豪'],
                ['Pa Kha', '帕卡'],
                ['Mong La Town', '勐拉镇'],
                ['Mong La Market', '勐拉市场'],
                ['Lyan Dwon', '良端'],
                ['Wan Kyin Fa', '万景坡'],
                ['Wan Ping', '万平'],
                ['Wan Hwe', '万惠'],
                ['Wan Nwe', '万内'],
            ],
            'ကျင်းခန်း' => [
                ['Wan Hote', '万厚'],
                ['Wan Kham', '万康'],
                ['Wan Ta', '万达'],
                ['Wan Ya', '万雅'],
                ['Weng Long', '温隆'],
                ['Weng Tai', '温泰'],
                ['Wanna Lan', '瓦纳兰'],
                ['Wan Ha', '万哈'],
                ['Wan Kap', '万甲'],
                ['Wan La Long', '万拉隆'],
                ['Kyaing Kham', '景康'],
                ['Wan Ho Nar', '万霍纳'],
            ],
            'မိုင်းစော' => [
                ['Na Phi Auk', '纳菲奥'],
                ['Than Lan', '丹兰'],
                ['Na De Auk', '纳德奥'],
                ['Di Shi', '迪希'],
                ['Na Nga', '纳伽'],
                ['Wan Pha Kyant (Na Bar Nwe)', '万帕坚（纳巴内）'],
                ['Shi Le', '希莱'],
                ['Le Shi', '莱希'],
                ['Bye Nye', '别内'],
                ['Wan Sar', '万萨'],
                ['Mong Hae', '勐海'],
                ['Nang Lin', '南林'],
                ['Wan Sai', '万赛'],
                ['Wan Ho Nar', '万霍纳'],
                ['Mong Hsaw', '勐扫'],
                ['Bar Chae', '巴切'],
                ['Bar Le', '巴莱'],
                ['Mong Nang', '勐囊'],
                ['Mong On', '勐温'],
            ],
        ];

        $areaCoordinates = [
            'မိုင်းလား' => [
                [21.650000, 99.883333],
                [21.688889, 99.908333],
                [21.679167, 99.887500],
                [21.637500, 99.845833],
                [21.663889, 99.883333],
                [21.561111, 99.987500],
                [21.720833, 99.905556],
                [21.669444, 99.861111],
                [21.622222, 99.804167],
                [21.675000, 99.879167],
                [21.645833, 99.833333],
                [21.653333, 99.831944],
                [21.701389, 99.869444],
                [21.698611, 99.893056],
                [21.570833, 99.962500],
                [21.519444, 99.972222],
                [21.711111, 99.913889],
                [21.668056, 100.036667],
                [21.656944, 100.061111],
                [21.680556, 100.076389],
                [21.694722, 100.031389],
                [21.693056, 100.034722],
                [21.716667, 100.054167],
                [21.705556, 100.019444],
                [21.463889, 100.094444],
                [21.437500, 100.105556],
                [21.476389, 100.069444],
            ],
            'ကျင်းခန်း' => [
                [21.411111, 99.922222],
                [21.387500, 99.943056],
                [21.441667, 99.886111],
                [21.418056, 99.913889],
                [21.698611, 99.893056],
                [21.380556, 99.954167],
                [21.402778, 99.934722],
                [21.326389, 99.811111],
                [21.720833, 99.905556],
                [21.365278, 99.975000],
                [21.512500, 99.770000],
                [21.522222, 99.759722],
            ],
            'မိုင်းစော' => [
                [21.755556, 99.704167],
                [21.786111, 99.743056],
                [21.745833, 99.686111],
                [21.775000, 99.718056],
                [21.804167, 99.713889],
                [21.765278, 99.755556],
                [21.790278, 99.737500],
                [21.818056, 99.694444],
                [21.736667, 99.675000],
                [21.877778, 99.806944],
                [21.904167, 99.830556],
                [21.852778, 99.784722],
                [21.891667, 99.854167],
                [21.918056, 99.812500],
                [21.886111, 99.838889],
                [21.840278, 99.770000],
                [21.866667, 99.826389],
                [21.938889, 99.877778],
                [21.929167, 99.851389],
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
