<?php

namespace Tests\Unit;

use App\Services\BroarbandAccount\AccountInfoService;
use App\Services\BroarbandAccount\BroadbandApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountInfoServiceTest extends TestCase
{
    public function test_it_does_not_look_up_accounts_with_an_empty_service_type_or_account_number(): void
    {
        Http::fake();

        $service = app(AccountInfoService::class);

        $this->assertNull($service->findAccount('', '12345678'));
        $this->assertNull($service->findAccount('CG', ''));

        Http::assertNothingSent();
    }

    public function test_it_gets_a_token_before_requesting_account_info(): void
    {
        config([
            'services.cg_api.url' => 'https://external.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'client-1',
            'services.cg_api.client_secret' => 'secret',
        ]);

        Http::fake([
            'external.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'token' => 'account-api-token',
                'expires_in' => 600,
            ]),
            'external.test/WalletApp/ServerCommand/AccountInfoAPI*' => Http::response([
                'ErrCode' => 0,
                'Data' => [
                    'account' => '123456',
                    'name' => 'Example Customer',
                ],
            ]),
        ]);

        $account = app(AccountInfoService::class)->find('FTTH', '123456');

        $this->assertSame('123456', $account['account']);
        Http::assertSent(
            fn ($request) => $request->method() === 'GET' &&
                $request->url() ===
                    'https://external.test/WalletApp/ServerCommand/GetTokenAPI?client_id=client-1&client_secret=secret',
        );
        Http::assertSent(
            fn ($request) => $request->method() === 'GET' &&
                str_starts_with($request->url(), 'https://external.test/WalletApp/ServerCommand/AccountInfoAPI?') &&
                $request->hasHeader('Authorization', 'Bearer account-api-token') &&
                $request['Account'] === '123456' &&
                $request['ServiceType'] === 'FTTH',
        );
        Http::assertSentCount(2);
    }

    public function test_it_rejects_a_token_response_without_a_token(): void
    {
        config([
            'services.cg_api.url' => 'https://external.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'client-1',
            'services.cg_api.client_secret' => 'secret',
        ]);
        Cache::flush();

        Http::fake([
            'external.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response(['ErrCode' => 0]),
            'external.test/WalletApp/ServerCommand/AccountInfoAPI*' => Http::response([]),
        ]);

        try {
            app(AccountInfoService::class)->find('FTTH', '123456');
            $this->fail('Expected the service to reject an invalid token response.');
        } catch (BroadbandApiException $exception) {
            $this->assertSame('broadband_api_token_response_invalid', $exception->errorCode);
        }

        Http::assertSentCount(1);
    }

    public function test_it_returns_a_distinct_error_when_the_broadband_api_cannot_be_reached(): void
    {
        config([
            'services.cg_api.url' => 'https://external.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'client-1',
            'services.cg_api.client_secret' => 'secret',
        ]);
        Cache::flush();

        Http::fake(fn () => throw new ConnectionException('DNS lookup failed.'));

        try {
            app(AccountInfoService::class)->find('FTTH', '123456');
            $this->fail('Expected the service to rethrow the connection failure.');
        } catch (ConnectionException $exception) {
            $this->assertSame('DNS lookup failed.', $exception->getMessage());
        }
    }

    public function test_it_keeps_not_found_for_a_successful_api_response_with_another_account(): void
    {
        config([
            'services.cg_api.url' => 'https://external.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'client-1',
            'services.cg_api.client_secret' => 'secret',
        ]);
        Cache::flush();

        Http::fake([
            'external.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'token' => 'account-api-token',
            ]),
            'external.test/WalletApp/ServerCommand/AccountInfoAPI*' => Http::response([
                'ErrCode' => 0,
                'Data' => ['account' => 'different-account'],
            ]),
        ]);

        $this->assertNull(app(AccountInfoService::class)->find('FTTH', '123456'));
        Http::assertSentCount(2);
    }

    public function test_it_reports_an_upstream_account_api_rejection_as_a_gateway_error(): void
    {
        config([
            'services.cg_api.url' => 'https://external.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'client-1',
            'services.cg_api.client_secret' => 'secret',
        ]);
        Cache::flush();

        Http::fake([
            'external.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'token' => 'account-api-token',
            ]),
            'external.test/WalletApp/ServerCommand/AccountInfoAPI*' => Http::response([
                'ErrCode' => 1,
            ]),
        ]);

        try {
            app(AccountInfoService::class)->find('FTTH', '123456');
            $this->fail('Expected the service to reject the upstream account request.');
        } catch (BroadbandApiException $exception) {
            $this->assertSame('broadband_api_account_request_rejected', $exception->errorCode);
        }

        Http::assertSentCount(2);
    }
}
