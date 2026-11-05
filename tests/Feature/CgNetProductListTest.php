<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CgNetProductListTest extends TestCase
{
    use RefreshDatabase;

    public function test_cg_net_products_come_from_walletapp_product_list(): void
    {
        config([
            'services.cg_api.url' => 'https://provider.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'provider-client',
            'services.cg_api.client_secret' => 'provider-secret',
        ]);
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'token' => 'provider-access-token',
            ]),
            'provider.test/WalletApp/ServerCommand/CGNETProductInfoListAPI' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'result' => [
                    [
                        'productID' => '236',
                        'productName' => 'CG-NET-79元_无限网卡_月',
                        'productPrice' => 79.0,
                        'monthCycle' => 1,
                        'monthCycleUnit' => '月',
                        'productLine' => 'CG-NET',
                        'productSpeed' => '10M',
                        'installationFee' => null,
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/web-app/cg-net-products')
            ->assertOk()
            ->assertJsonPath('ErrCode', 0)
            ->assertJsonPath('Message', 'ok')
            ->assertJsonPath('result.0.productID', '236')
            ->assertJsonPath('result.0.productName', 'CG-NET-79元_无限网卡_月')
            ->assertJsonPath('result.0.productPrice', 79)
            ->assertJsonPath('result.0.monthCycle', 1)
            ->assertJsonPath('result.0.monthCycleUnit', '月')
            ->assertJsonPath('result.0.productLine', 'CG-NET')
            ->assertJsonPath('result.0.productSpeed', '10M')
            ->assertJsonPath('result.0.installationFee', null);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://provider.test/WalletApp/ServerCommand/CGNETProductInfoListAPI');
    }

    public function test_cg_net_products_returns_message_code_when_catalog_unavailable(): void
    {
        config([
            'services.cg_api.url' => 'https://provider.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'provider-client',
            'services.cg_api.client_secret' => 'provider-secret',
        ]);
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'token' => 'provider-access-token',
            ]),
            'provider.test/WalletApp/ServerCommand/CGNETProductInfoListAPI' => Http::response([
                'ErrCode' => 100,
                'Message' => 'provider boom',
                'result' => [],
            ]),
        ]);

        $this->getJson('/api/web-app/cg-net-products')
            ->assertStatus(503)
            ->assertJsonPath('code', 'MSG-PKG-003')
            ->assertJsonMissing(['message' => 'provider boom']);
    }
}
