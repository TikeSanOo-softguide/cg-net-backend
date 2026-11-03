<?php

namespace Tests\Unit;

use App\Models\Package;
use App\Models\PackageOrder;
use App\Models\User;
use App\Services\PackageActivation\RealPackageActivationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealPackageActivationServiceTest extends TestCase
{
    private function order(): PackageOrder
    {
        $order = new PackageOrder;
        $order->forceFill([
            'order_no' => 'CGN-UNIT-ORDER-1',
            'account' => 'G1234567',
            'activation_password' => '000012',
            'product_id' => 'trusted-product',
            'product_price' => '128.00',
        ]);

        return $order;
    }

    public function test_it_posts_the_saved_order_and_credentials_to_the_cgnet_endpoint(): void
    {
        $this->configureCgNet();
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'token' => 'provider-access-token',
            ]),
            'provider.test/WalletApp/ServerCommand/ActivateCGNETAccountAPI' => Http::response(['ErrCode' => 0, 'Message' => 'ok']),
        ]);

        $order = $this->order();
        $result = app(RealPackageActivationService::class)->activate(new User, new Package, $order);

        $this->assertTrue($result->success);
        $this->assertSame($order->account, $result->username);
        $this->assertSame('000012', $result->password);
        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://provider.test/WalletApp/ServerCommand/ActivateCGNETAccountAPI'
                && $request->hasHeader('Authorization', 'Bearer provider-access-token')
                && $body === [
                    'OrderNo' => 'CGN-UNIT-ORDER-1',
                    'Account' => 'G1234567',
                    'Password' => '000012',
                    'ProductID' => 'trusted-product',
                    'ProductPrice' => 128.0,
                ];
        });
    }

    public function test_only_documented_definitive_rejections_are_classified_as_failures(): void
    {
        $this->configureCgNet();
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'token' => 'provider-access-token',
            ]),
            'provider.test/WalletApp/ServerCommand/ActivateCGNETAccountAPI' => Http::sequence()
                ->push(['ErrCode' => 100, 'Message' => 'bad product'])
                ->push(['ErrCode' => 500, 'Message' => 'bad request'])
                ->push(['ErrCode' => 1, 'Message' => 'already opened'])
                ->push(['ErrCode' => 2, 'Message' => 'account exists'])
                ->push(['ErrCode' => 99, 'Message' => 'unknown']),
        ]);

        $service = app(RealPackageActivationService::class);
        $order = $this->order();

        foreach ([100, 500] as $errorCode) {
            $result = $service->activate(new User, new Package, $order);
            $this->assertFalse($result->success);
            $this->assertTrue($result->definitiveFailure);
            $this->assertSame($errorCode, $result->errorCode);
        }

        foreach ([1, 2, 99] as $errorCode) {
            $result = $service->activate(new User, new Package, $order);
            $this->assertFalse($result->success);
            $this->assertFalse($result->definitiveFailure);
            $this->assertSame($errorCode, $result->errorCode);
        }
    }

    public function test_http_errors_invalid_json_and_connection_errors_remain_ambiguous(): void
    {
        $this->configureCgNet();
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'token' => 'provider-access-token',
            ]),
            'provider.test/WalletApp/ServerCommand/ActivateCGNETAccountAPI' => Http::sequence()
                ->push(['error' => 'upstream'], 503)
                ->push('not-json', 200, ['Content-Type' => 'text/plain']),
        ]);

        $service = app(RealPackageActivationService::class);
        $order = $this->order();

        foreach ([1, 2] as $_) {
            $result = $service->activate(new User, new Package, $order);
            $this->assertFalse($result->success);
            $this->assertFalse($result->definitiveFailure);
        }
    }

    public function test_connection_errors_remain_ambiguous(): void
    {
        $this->configureCgNet();
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'token' => 'provider-access-token',
            ]),
            'provider.test/WalletApp/ServerCommand/ActivateCGNETAccountAPI' => fn () => throw new ConnectionException('timeout'),
        ]);

        $timeout = app(RealPackageActivationService::class)->activate(new User, new Package, $this->order());
        $this->assertFalse($timeout->success);
        $this->assertFalse($timeout->definitiveFailure);
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
}
