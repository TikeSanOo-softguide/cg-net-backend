<?php

namespace Database\Seeders;

use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use Illuminate\Database\Seeder;

class LedgerAccountSeeder extends Seeder
{
    public function run(): void
    {
        $ledger = app(LedgerPoster::class);
        $ledger->ensureSystemAccounts();

        foreach (Wallet::query()->cursor() as $wallet) {
            $ledger->ensureCustomerLiabilityAccount($wallet);
        }
    }
}
