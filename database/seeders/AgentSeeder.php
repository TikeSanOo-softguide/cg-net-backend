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
                'address' => 'No. 12, Bogyoke Aung San Road, Yangon',
            ],
            [
                'name' => 'Mandalay North Agent',
                'address' => 'No. 45, 78th Street, Mandalay',
            ],
            [
                'name' => 'Naypyidaw Agent',
                'address' => 'No. 8, Yarza Thingaha Road, Naypyidaw',
            ],
        ];

        foreach ($agents as $data) {
            Agent::query()->updateOrCreate(
                ['name' => $data['name']],
                ['address' => $data['address']],
            );
        }
    }
}
