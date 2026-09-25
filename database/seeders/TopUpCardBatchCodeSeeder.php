<?php

namespace Database\Seeders;

use App\Models\TopUpCardBatchCode;
use Illuminate\Database\Seeder;

class TopUpCardBatchCodeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (
            [
                ['amount' => 50, 'batch_code' => '1101'],
                ['amount' => 100, 'batch_code' => '1201'],
                ['amount' => 250, 'batch_code' => '1501'],
                ['amount' => 500, 'batch_code' => '1111'],
            ]
            as $batchCode
        ) {
            TopUpCardBatchCode::query()->updateOrCreate(
                ['amount' => $batchCode['amount']],
                ['batch_code' => $batchCode['batch_code']],
            );
        }
    }
}
