<?php

namespace Database\Seeders;

use App\Enums\TopUpCardStatus;
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

        TopUpCard::factory()->count(15)->redeemed($lastUser)->create();

        TopUpCard::factory()
            ->count(10)
            ->create([
                'status' => TopUpCardStatus::Active,
                'redeemed_at' => null,
                'redeemed_by' => null,
            ]);

        TopUpCard::factory()
            ->count(10)
            ->create([
                'status' => TopUpCardStatus::Expired,
                'redeemed_at' => null,
                'redeemed_by' => null,
                'expires_at' => now()->subDays(5),
            ]);

        TopUpCard::factory()
            ->count(10)
            ->create([
                'status' => TopUpCardStatus::Blocked,
                'redeemed_at' => null,
                'redeemed_by' => null,
            ]);
    }
}
