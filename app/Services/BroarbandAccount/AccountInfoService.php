<?php

namespace App\Services\BroarbandAccount;

use App\Services\CgApiTokenException;
use App\Services\CgApiTokenProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AccountInfoService
{
    public function __construct(private CgApiTokenProvider $tokens) {}

    public function find(string $account, string $serviceType): ?array
    {
        $baseUrl = $this->baseUrl();

        try {
            try {
                $token = $this->tokens->getAccessToken($baseUrl);
            } catch (CgApiTokenException $exception) {
                Log::error('Broadband API token request failed.', [
                    'error_code' => $exception->errorCode,
                ]);

                $errorCode = match ($exception->errorCode) {
                    'credentials_not_configured' => 'broadband_api_credentials_not_configured',
                    'token_request_rejected' => 'broadband_api_token_request_rejected',
                    'token_response_invalid' => 'broadband_api_token_response_invalid',
                    default => 'broadband_api_token_request_failed',
                };

                throw new BroadbandApiException($errorCode, 'Broadband API token request failed.');
            }

            try {
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->get($baseUrl.'/AccountInfoAPI', [
                        'Account' => $account,
                        'ServiceType' => $serviceType,
                    ]);
            } catch (ConnectionException $e) {
                Log::error('Broadband account API connection failed.', [
                    'error' => $e->getMessage(),
                    'service_type' => $serviceType,
                ]);

                throw $e;
            }

            if ($response->failed()) {
                Log::error('Broadband API request failed.', [
                    'status' => $response->status(),
                ]);

                throw new BroadbandApiException(
                    'broadband_api_account_request_failed',
                    'Broadband account API request failed.',
                );
            }

            $data = $response->json();

            if (! is_array($data)) {
                Log::error('Broadband API returned an invalid account response.');

                throw new BroadbandApiException(
                    'broadband_api_account_response_invalid',
                    'Broadband account API returned an invalid response.',
                );
            }

            if (($data['ErrCode'] ?? null) != 0) {
                Log::error('Broadband API rejected the account request.', [
                    'error_code' => $data['ErrCode'] ?? null,
                ]);

                throw new BroadbandApiException(
                    'broadband_api_account_request_rejected',
                    'Broadband account API rejected the account request.',
                );
            }

            $accountData = $data['Data'] ?? null;

            if (! is_array($accountData)) {
                Log::warning('Broadband API account data is missing or invalid.', [
                    'service_type' => $serviceType,
                    'data_type' => gettype($accountData),
                ]);

                return null;
            }

            $returnedAccount = $accountData['account'] ?? null;

            if (! is_string($returnedAccount) || trim($returnedAccount) !== $account) {
                Log::warning('Broadband API account number mismatch.', [
                    'requested_account' => $account,
                    'returned_account' => $returnedAccount,
                    'returned_keys' => array_keys($accountData),
                    'service_type' => $serviceType,
                ]);

                return null;
            }

            return $accountData;
        } catch (ConnectionException $e) {
            Log::error('Broadband API connection failed: '.$e->getMessage());

            throw $e;
        }
    }

    public function findAccount(string $broadbandAccount): ?array
    {
        $broadbandAccount = trim($broadbandAccount);

        if ($broadbandAccount === '') {
            return null;
        }

        if (str_starts_with($broadbandAccount, 'FTTH')) {
            $serviceType = 'FTTH';
        } elseif (str_starts_with($broadbandAccount, 'CG')) {
            $serviceType = 'CG';
        } else {
            Log::warning('Invalid broadband account number prefix.');

            return null;
        }
        $account = substr($broadbandAccount, strlen($serviceType));

        return $this->find($account, $serviceType);
    }

    private function baseUrl(): string
    {
        $baseUrl = trim((string) config('services.cg_api.url'));

        $parts = parse_url($baseUrl);

        if (
            ! is_array($parts) ||
            ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) ||
            empty($parts['host'])
        ) {
            Log::error('Broadband API URL is not configured or invalid.');

            throw new BroadbandApiException(
                'broadband_api_url_invalid',
                'Broadband API URL is not configured or invalid.',
            );
        }

        return rtrim($baseUrl, '/');
    }
}
