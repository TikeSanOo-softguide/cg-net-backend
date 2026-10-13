<?php

namespace App\Console\Commands;

use App\Enums\TopUpCardStatus;
use App\Models\TopUpCard;
use Illuminate\Console\Command;

class ExpireTopUpCards extends Command
{
    protected $signature = 'top-up-cards:expire';

    protected $description = 'Mark active top-up cards past their expiry date as expired.';

    public function handle(): int
    {
        $expiredCount = TopUpCard::query()
            ->where('status', TopUpCardStatus::Active->value)
            ->whereDate('expires_at', '<', today()->toDateString())
            ->update(['status' => TopUpCardStatus::Expired->value]);

        $this->info("Marked {$expiredCount} top-up card(s) as expired.");

        return self::SUCCESS;
    }
}
