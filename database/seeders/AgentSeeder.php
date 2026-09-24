<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agents = [
            [
                'name' => 'Yangon Central Agent',
                'cd' => 11,
                'address' => 'No. 12, Bogyoke Aung San Road, Yangon',
            ],
            [
                'name' => 'Mandalay North Agent',
                'cd' => 21,
                'address' => 'No. 45, 78th Street, Mandalay',
            ],
            [
                'name' => 'Naypyidaw Agent',
                'cd' => 31,
                'address' => 'No. 8, Yarza Thingaha Road, Naypyidaw',
            ],
        ];

        foreach ($agents as $data) {
            Agent::query()->updateOrCreate(
                ['name' => $data['name']],
                ['cd' => $data['cd'], 'address' => $data['address']],
            );
        }
    }
}
