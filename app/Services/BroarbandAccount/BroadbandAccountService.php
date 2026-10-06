<?php

namespace App\Services\BroarbandAccount;

use Illuminate\Support\Facades\Http;

class BroadbandAccountService
{
    public function find(string $accountNumber, string $customerName): ?array
    {
        $url = $this->endpointUrl();

        if ($url === null) {
            return null;
        }

        $response = Http::get($url, [
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
        $url = $this->endpointUrl();

        if ($url === null) {
            return null;
        }

        $response = Http::get($url, ['account_number' => $accountNumber]);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        return $data[0] ?? null;
    }

    private function endpointUrl(): ?string
    {
        $baseUrl = trim((string) config('services.broadband.url'));
        $parts = parse_url($baseUrl);

        if (
            !is_array($parts) ||
            !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) ||
            empty($parts['host'])
        ) {
            return null;
        }

        return rtrim($baseUrl, '/') . '/broadband_accounts';
    }
}
