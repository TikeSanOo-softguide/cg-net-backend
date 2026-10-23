<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CgApiTokenProvider
{
    public function getAccessToken(string $baseUrl): string
    {
        $clientId = trim((string) config('services.cg_api.client_id'));
        $clientSecret = trim((string) config('services.cg_api.client_secret'));

        if ($clientId === '' || $clientSecret === '') {
            throw new CgApiTokenException('credentials_not_configured', 'CG API credentials are not configured.');
        }

        $cacheKey = 'cg_api.access_token.'.hash('sha256', rtrim($baseUrl, '/')."\0".$clientId."\0".$clientSecret);
        $cachedToken = Cache::get($cacheKey);

        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $response = $this->pendingRequest()->get(
            rtrim($baseUrl, '/').'/GetTokenAPI',
            ['client_id' => $clientId, 'client_secret' => $clientSecret],
        );

        if (! $response->successful()) {
            throw new CgApiTokenException('token_request_failed', 'CG API token request failed.');
        }

        $payload = $response->json();

        if (
            ! is_array($payload)
            || ! array_key_exists('ErrCode', $payload)
            || ! is_numeric($payload['ErrCode'])
            || (int) $payload['ErrCode'] !== 0
        ) {
            throw new CgApiTokenException('token_request_rejected', 'CG API rejected the token request.');
        }

        if (! is_string($payload['token'] ?? null) || trim($payload['token']) === '') {
            throw new CgApiTokenException('token_response_invalid', 'CG API did not return a valid access token.');
        }

        $expiresIn = is_numeric($payload['expires_in'] ?? null) ? (int) $payload['expires_in'] : 300;
        Cache::put($cacheKey, $payload['token'], now()->addSeconds(max(1, $expiresIn - 60)));

        return $payload['token'];
    }

    private function pendingRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(max(1, (int) config('services.cg_api.connect_timeout', 5)))
            ->timeout(max(1, (int) config('services.cg_api.timeout', 15)));
    }
}
