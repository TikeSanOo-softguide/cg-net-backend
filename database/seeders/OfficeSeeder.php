<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "Office seeder started\n";

        $offices = [
            [
                'name_en' => 'Mongla Office',
                'name_mm' => 'မိုင်းလားရုံး',
                'name_zh' => '勐拉办公室',
                'cd' => 88,
                'address_en' => '130/A Eain Twin Hmu Street,Mae Khong Ward,Tachileik.',
                'address_mm' => '130/A Eain Twin Hmu Street,Mae Khong Ward,Tachileik.',
                'address_zh' => '130/A Eain Twin Hmu Street,Mae Khong Ward,Tachileik.',
                'phone_num1' => fake()->numerify('09#########'),
                'phone_num2' => null,
                'phone_num3' => null,
            ],
            [
                'name_en' => 'Tachileik Office',
                'name_mm' => 'တာချီလိတ်ရုံး',
                'name_zh' => '大其力办公室',
                'cd' => 66,
                'address_en' => 'Tachileik',
                'address_mm' => 'တာချီလိတ်',
                'address_zh' => '大其力',
                'phone_num1' => fake()->numerify('09#########'),
                'phone_num2' => fake()->numerify('09#########'),
                'phone_num3' => null,
            ],
            [
                'name_en' => 'Kyaing Tong Office',
                'name_mm' => 'ကျိုင်းတုံရုံး',
                'name_zh' => '景栋办公室',
                'cd' => 99,
                'address_en' => 'Kyaing Tong',
                'address_mm' => 'ကျိုင်းတုံ',
                'address_zh' => '景栋',
                'phone_num1' => fake()->numerify('09#########'),
                'phone_num2' => fake()->numerify('09#########'),
                'phone_num3' => fake()->numerify('09#########'),
            ],
        ];

        foreach ($offices as $data) {
            Office::query()->updateOrCreate(['cd' => $data['cd']], $data);
        }
    }
}
