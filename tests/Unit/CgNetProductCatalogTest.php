<?php

namespace Tests\Unit;

use App\Services\Package\CgNetProductCatalog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CgNetProductCatalogTest extends TestCase
{
    public function test_it_fetches_and_normalizes_products_from_the_documented_catalog_endpoint(): void
    {
        $this->configureCgNet();
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => $this->tokenResponse(),
            'provider.test/WalletApp/ServerCommand/CGNETProductInfoListAPI' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'result' => [[
                    'productID' => '236',
                    'productName' => 'CG-NET-79 yuan monthly',
                    'productPrice' => 79,
                    'monthCycle' => 1,
                    'monthCycleUnit' => '月',
                    'productLine' => 'CG-NET',
                    'productSpeed' => '10M',
                    'installationFee' => null,
                ], [
                    'productID' => '248',
                    'productName' => 'CG-NET 7 days',
                    'productPrice' => 25,
                    'monthCycle' => 7,
                    'monthCycleUnit' => '天',
                    'productLine' => 'CG-NET',
                    'productSpeed' => '10M',
                    'installationFee' => null,
                ]],
            ]),
        ]);

        $products = app(CgNetProductCatalog::class)->products();

        $this->assertSame('236', $products[0]['productID']);
        $this->assertSame('248', $products[1]['productID']);
        $this->assertSame('天', $products[1]['monthCycleUnit']);
        $this->assertSame(79.0, $products[0]['productPrice']);
        $this->assertSame(1, $products[0]['monthCycle']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://provider.test/WalletApp/ServerCommand/CGNETProductInfoListAPI'
            && $request->hasHeader('Authorization', 'Bearer provider-access-token'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://provider.test/WalletApp/ServerCommand/GetTokenAPI?client_id=provider-client&client_secret=provider-secret');
    }

    public function test_it_returns_a_product_by_its_provider_id(): void
    {
        $this->configureCgNet();
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => $this->tokenResponse(),
            'provider.test/WalletApp/ServerCommand/CGNETProductInfoListAPI' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'result' => [[
                    'productID' => '218',
                    'productName' => 'Home 100M',
                    'productPrice' => 128,
                    'monthCycle' => 6,
                    'monthCycleUnit' => '月',
                    'productLine' => 'fiber',
                    'productSpeed' => '100M',
                    'installationFee' => null,
                ]],
            ]),
        ]);

        $product = app(CgNetProductCatalog::class)->find('218');

        $this->assertSame('218', $product['productID']);
        $this->assertNull(app(CgNetProductCatalog::class)->find('missing'));
        Http::assertSentCount(3);
    }

    public function test_it_fetches_ftth_and_cg_router_installation_catalogs(): void
    {
        $this->configureCgNet();
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => $this->tokenResponse(),
            'provider.test/WalletApp/ServerCommand/FTTHProductInfoListAPI' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'result' => [[
                    'productID' => '249',
                    'productName' => '办公移动网_100M_月',
                    'productPrice' => 2000.0,
                    'monthCycle' => 1,
                    'monthCycleUnit' => '月',
                    'productLine' => '移动网_办公',
                    'productSpeed' => '100M',
                    'installationFee' => '500',
                ]],
            ]),
            'provider.test/WalletApp/ServerCommand/CGRouterProductInfoListAPI' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'result' => [[
                    'productID' => '310',
                    'productName' => 'CG Router 50M',
                    'productPrice' => 1500.0,
                    'monthCycle' => 1,
                    'monthCycleUnit' => '月',
                    'productLine' => 'CG-Router',
                    'productSpeed' => '50M',
                    'installationFee' => 0,
                ]],
            ]),
        ]);

        $catalog = app(CgNetProductCatalog::class);
        $ftth = $catalog->productsForServiceType('FTTH');
        $cg = $catalog->findForServiceType('CG', '310');

        $this->assertSame('249', $ftth[0]['productID']);
        $this->assertSame('办公移动网_100M_月', $ftth[0]['productName']);
        $this->assertSame(500.0, $ftth[0]['installationFee']);
        $this->assertSame('310', $cg['productID']);
        $this->assertSame('FTTHProductInfoListAPI', $catalog->commandForServiceType('FTTH'));
        $this->assertSame('CGRouterProductInfoListAPI', $catalog->commandForServiceType('CG'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://provider.test/WalletApp/ServerCommand/FTTHProductInfoListAPI');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://provider.test/WalletApp/ServerCommand/CGRouterProductInfoListAPI');
    }

    public function test_it_rejects_invalid_or_unsuccessful_catalog_responses(): void
    {
        $this->configureCgNet();
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => $this->tokenResponse(),
            'provider.test/WalletApp/ServerCommand/CGNETProductInfoListAPI' => Http::sequence()
                ->push(['ErrCode' => 100, 'Message' => 'unavailable', 'result' => []])
                ->push(['ErrCode' => 0, 'Message' => 'ok', 'result' => [['productID' => 'bad']]]),
        ]);

        $catalog = app(CgNetProductCatalog::class);

        foreach ([1, 2] as $_) {
            try {
                $catalog->products();
                $this->fail('Expected the invalid catalog response to be rejected.');
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }
    }

    private function configureCgNet(): void
    {
        config([
            'services.cg_api.url' => 'https://provider.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'provider-client',
            'services.cg_api.client_secret' => 'provider-secret',
            'services.cg_api.timeout' => 12,
            'services.cg_api.connect_timeout' => 3,
        ]);
    }

    private function tokenResponse()
    {
        return Http::response([
            'ErrCode' => 0,
            'Message' => 'ok',
            'token' => 'provider-access-token',
        ]);
    }
}
