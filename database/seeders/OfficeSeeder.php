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
                'name' => 'Yangon Central Office',
                'cd' => 11,
                'address' => 'No. 12, Bogyoke Aung San Road, Yangon',
            ],
            [
                'name' => 'Mandalay North Office',
                'cd' => 21,
                'address' => 'No. 45, 78th Street, Mandalay',
            ],
            [
                'name' => 'Naypyidaw Office',
                'cd' => 31,
                'address' => 'No. 8, Yarza Thingaha Road, Naypyidaw',
            ],
        ];

        foreach ($offices as $data) {
            Office::query()->updateOrCreate(
                ['name' => $data['name']],
                ['cd' => $data['cd'], 'address' => $data['address']],
            );
        }
    }
}
