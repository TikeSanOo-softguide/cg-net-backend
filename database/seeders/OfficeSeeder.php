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
                'name' => 'Mongla Office',
                'cd' => 88,
                'address' => '130/A Eain Twin Hmu Street,Mae Khong Ward,Tachileik.',
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
