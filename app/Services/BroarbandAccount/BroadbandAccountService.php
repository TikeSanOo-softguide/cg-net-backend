<?php

namespace App\Services\BroarbandAccount;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BroadbandAccountService
{
    public function find(string $accountNumber, string $customerName): ?array
    {
        $url = $this->endpointUrl();

        if ($url === null) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get($url, [
                'account_number' => $accountNumber,
                'customer_name' => $customerName,
            ]);

            if ($response->failed()) {
                return null;
            }

            $data = $response->json();
            $accounts = is_array($data) ? ($data['data'] ?? $data) : null;

            if (! is_array($accounts)) {
                return null;
            }

            foreach ($accounts as $account) {
                if (
                    is_array($account) &&
                    ($account['account_number'] ?? null) === $accountNumber &&
                    ($account['customer_name'] ?? null) === $customerName
                ) {
                    return $account;
                }
            }

            return null;
        } catch (ConnectionException $e) {
            Log::error('Broadband API connection failed: '.$e->getMessage());

            return null;
        }
    }

    public function findByAccountNumber(string $accountNumber): ?array
    {
        $url = $this->endpointUrl();

        if ($url === null) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get($url, ['account_number' => $accountNumber]);

            if ($response->failed()) {
                return null;
            }

            $data = $response->json();
            $accounts = is_array($data) ? ($data['data'] ?? $data) : null;

            if (! is_array($accounts)) {
                return null;
            }

            foreach ($accounts as $account) {
                if (is_array($account) && ($account['account_number'] ?? null) === $accountNumber) {
                    return $account;
                }
            }

            return null;
        } catch (ConnectionException $e) {
            Log::error('Broadband API connection failed: '.$e->getMessage());

            return null;
        }
    }

    private function endpointUrl(): ?string
    {
        $baseUrl = trim((string) config('services.broadband.url'));
        $parts = parse_url($baseUrl);

        if (
            ! is_array($parts) ||
            ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) ||
            empty($parts['host'])
        ) {
            return null;
        }

        return rtrim($baseUrl, '/').'/broadband_accounts';
    }
}
