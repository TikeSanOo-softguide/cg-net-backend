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

        DB::table('states')->updateOrInsert(
            ['name' => $stateName],
            [
                'name' => $stateName,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $shanState = DB::table('states')->where('name', $stateName)->first();

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

        foreach ($regionsData as $regionName => $areas) {
            // Seed Region
            DB::table('regions')->updateOrInsert(
                [
                    'name' => $regionName,
                    'state_id' => $shanState->id,
                ],
                [
                    'name' => $regionName,
                    'state_id' => $shanState->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $region = DB::table('regions')->where('name', $regionName)->where('state_id', $shanState->id)->first();

            // Seed Areas
            foreach ($areas as $areaName) {
                DB::table('areas')->updateOrInsert(
                    [
                        'name' => $areaName,
                        'region_id' => $region->id,
                    ],
                    [
                        'name' => $areaName,
                        'region_id' => $region->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }
}
