<?php

namespace App\Services\BroarbandAccount;

use App\Services\CgApiTokenException;
use App\Services\CgNetApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AccountInfoService
{
    public function __construct(private CgNetApiClient $api) {}

    public function find(string $serviceType, string $account): ?array
    {
        try {
            $response = $this->api->get('AccountInfoAPI', [
                'Account' => $account,
                'ServiceType' => $serviceType,
            ]);
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
        } catch (ConnectionException $exception) {
            Log::error('Broadband account API connection failed.', [
                'error' => $exception->getMessage(),
                'service_type' => $serviceType,
            ]);

            throw $exception;
        } catch (RuntimeException $exception) {
            Log::error('Broadband account API request failed.', [
                'error' => $exception->getMessage(),
                'service_type' => $serviceType,
            ]);

            throw new BroadbandApiException(
                'broadband_api_account_request_failed',
                'Broadband account API request failed.',
            );
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

        if (!is_array($data)) {
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

        if (!is_array($accountData)) {
            Log::warning('Broadband API account data is missing or invalid.', [
                'service_type' => $serviceType,
                'data_type' => gettype($accountData),
            ]);

            return null;
        }

        $returnedAccount = $accountData['account'] ?? null;

        if (!is_string($returnedAccount) || trim($returnedAccount) !== $account) {
            Log::warning('Broadband API account number mismatch.', [
                'requested_account' => $account,
                'returned_account' => $returnedAccount,
                'returned_keys' => array_keys($accountData),
                'service_type' => $serviceType,
            ]);

            return null;
        }

        return $accountData;
    }

    public function findAccount(string $serviceType, string $broadbandAccount): ?array
    {
        if ($serviceType === '' || $broadbandAccount === '') {
            return null;
        }

        return $this->find($serviceType, $broadbandAccount);
    }
}
