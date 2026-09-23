<?php

namespace Database\Seeders;

use App\Enums\TopUpCardStatus;
use App\Models\Batch;
use App\Models\TopUpCard;
use App\Models\User;
use Illuminate\Database\Seeder;

class TopUpCardSeeder extends Seeder
{
    public function run(): void
    {
        $lastUser = User::query()->latest('id')->first();

        if (!$lastUser) {
            return;
        }

        echo "Top-up card seeder started\n";

        $batch = Batch::factory()->create([
            'quantity' => 55,
        ]);

        TopUpCard::factory()
            ->count(15)
            ->create([
                'status' => TopUpCardStatus::Pending,
                'batch_id' => $batch->id,
            ]);

        TopUpCard::factory()
            ->count(10)
            ->redeemed($lastUser)
            ->create([
                'batch_id' => $batch->id,
            ]);

        TopUpCard::factory()
            ->count(10)
            ->create([
                'status' => TopUpCardStatus::Active,
                'batch_id' => $batch->id,
                'redeemed_at' => null,
                'redeemed_by' => null,
            ]);

        TopUpCard::factory()
            ->count(10)
            ->create([
                'status' => TopUpCardStatus::Expired,
                'batch_id' => $batch->id,
                'redeemed_at' => null,
                'redeemed_by' => null,
                'expires_at' => now()->subDays(5),
            ]);

        TopUpCard::factory()
            ->count(10)
            ->create([
                'status' => TopUpCardStatus::Blocked,
                'batch_id' => $batch->id,
                'redeemed_at' => null,
                'redeemed_by' => null,
            ]);
    }
}
