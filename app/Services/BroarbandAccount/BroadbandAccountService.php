<?php

namespace App\Services\BroarbandAccount;

use Illuminate\Support\Facades\Http;

class BroadbandAccountService
{
    public function find(string $accountNumber, string $customerName): ?array
    {
        $response = Http::get(config('services.broadband.url') . '/broadband_accounts', [
            'account_number' => $accountNumber,
            'customer_name' => $customerName,
        ]);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        return $data[0] ?? null;
    }

    public function findByAccountNumber(string $accountNumber): ?array
    {
        $response = Http::get(config('services.broadband.url') . '/broadband_accounts', [
            'account_number' => $accountNumber,
        ]);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        return $data[0] ?? null;
    }
}
