<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CgNetApiClient
{
    public function __construct(private CgApiTokenProvider $tokens) {}

    public function get(string $command, array $query = []): Response
    {
        return $this->request('GET', $command, $query);
    }

    public function post(string $command, array $data = []): Response
    {
        return $this->request('POST', $command, $data);
    }

    private function request(string $method, string $command, array $data): Response
    {
        $baseUrl = $this->baseUrl();

        return $this->pendingRequest()
            ->withToken($this->tokens->getAccessToken($baseUrl))
            ->send(
                $method,
                $baseUrl.'/'.$command,
                $method === 'GET' ? ['query' => $data] : ['json' => $data],
            );
    }

    private function baseUrl(): string
    {
        $baseUrl = trim((string) config('services.cg_api.url'));
        $parts = parse_url($baseUrl);

        if (
            $baseUrl === ''
            || filter_var($baseUrl, FILTER_VALIDATE_URL) === false
            || ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! str_ends_with(rtrim((string) ($parts['path'] ?? ''), '/'), '/WalletApp/ServerCommand')
        ) {
            throw new RuntimeException('CG API URL must be configured as an HTTPS ServerCommand URL.');
        }

        return rtrim($baseUrl, '/');
    }

    private function pendingRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(max(1, (int) config('services.cg_api.connect_timeout', 5)))
            ->timeout(max(1, (int) config('services.cg_api.timeout', 15)));
    }
}
