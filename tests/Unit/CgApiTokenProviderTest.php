<?php

namespace Tests\Unit;

use App\Services\BroarbandAccount\AccountInfoService;
use App\Services\CgNetApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CgApiTokenProviderTest extends TestCase
{
    public function test_account_lookup_and_package_commands_share_one_cached_token(): void
    {
        config([
            'services.cg_api.url' => 'https://provider.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'provider-client',
            'services.cg_api.client_secret' => 'provider-secret',
            'services.cg_api.timeout' => 12,
            'services.cg_api.connect_timeout' => 3,
        ]);
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'token' => 'shared-provider-token',
                'expires_in' => 3600,
            ]),
            'provider.test/WalletApp/ServerCommand/CGNETProductInfoListAPI' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'result' => [],
            ]),
            'provider.test/WalletApp/ServerCommand/AccountInfoAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'Data' => ['account' => '123456', 'name' => 'Customer'],
            ]),
        ]);

        app(CgNetApiClient::class)->get('CGNETProductInfoListAPI');
        $account = app(AccountInfoService::class)->find('123456', 'CG');

        $this->assertSame('123456', $account['account']);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => str_ends_with(
            (string) parse_url($request->url(), PHP_URL_PATH),
            '/CGNETProductInfoListAPI',
        ) && $request->hasHeader('Authorization', 'Bearer shared-provider-token'));
        Http::assertSent(fn (Request $request): bool => str_ends_with(
            (string) parse_url($request->url(), PHP_URL_PATH),
            '/AccountInfoAPI',
        ) && $request->hasHeader('Authorization', 'Bearer shared-provider-token'));
        Http::assertSent(fn (Request $request): bool => str_ends_with(
            (string) parse_url($request->url(), PHP_URL_PATH),
            '/GetTokenAPI',
        ));
    }
}
