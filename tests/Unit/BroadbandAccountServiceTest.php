<?php

namespace Tests\Unit;

use App\Services\BroarbandAccount\BroadbandAccountService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BroadbandAccountServiceTest extends TestCase
{
    public function test_it_finds_a_matching_account_in_a_flat_list(): void
    {
        config(['services.broadband.url' => 'https://broadband.test']);
        Http::fake([
            'broadband.test/broadband_accounts*' => Http::response([
                [
                    'account_number' => '123456',
                    'customer_name' => 'test',
                    'status' => 'active',
                    'current_customer_package_id' => '14',
                ],
                [
                    'account_number' => '11223344',
                    'customer_name' => 'mimi',
                    'status' => 'active',
                    'current_customer_package_id' => '15',
                ],
            ]),
        ]);

        $account = app(BroadbandAccountService::class)->find('123456', 'test');

        $this->assertSame('123456', $account['account_number']);
        $this->assertSame('test', $account['customer_name']);
    }

    public function test_it_finds_a_matching_account_in_a_data_envelope(): void
    {
        config(['services.broadband.url' => 'https://broadband.test']);
        Http::fake([
            'broadband.test/broadband_accounts*' => Http::response([
                'data' => [
                    [
                        'account_number' => '123456',
                        'customer_name' => 'test',
                        'status' => 'active',
                    ],
                    [
                        'account_number' => '11223344',
                        'customer_name' => 'mimi',
                        'status' => 'active',
                    ],
                ],
            ]),
        ]);

        $account = app(BroadbandAccountService::class)->find('11223344', 'mimi');

        $this->assertSame('11223344', $account['account_number']);
        $this->assertSame('mimi', $account['customer_name']);
    }

    public function test_it_rejects_an_account_number_that_does_not_match_even_when_the_name_does(): void
    {
        config(['services.broadband.url' => 'https://broadband.test']);
        Http::fake([
            'broadband.test/broadband_accounts*' => Http::response([
                [
                    'account_number' => '11223344',
                    'customer_name' => 'mimi',
                    'status' => 'active',
                ],
            ]),
        ]);

        $account = app(BroadbandAccountService::class)->find('123456', 'mimi');

        $this->assertNull($account);
    }

    public function test_it_rejects_a_customer_name_that_does_not_match_even_when_the_account_number_does(): void
    {
        config(['services.broadband.url' => 'https://broadband.test']);
        Http::fake([
            'broadband.test/broadband_accounts*' => Http::response([
                [
                    'account_number' => '123456',
                    'customer_name' => 'not the customer',
                    'status' => 'active',
                ],
            ]),
        ]);

        $account = app(BroadbandAccountService::class)->find('123456', 'test');

        $this->assertNull($account);
    }

    public function test_it_finds_an_account_by_number_in_a_data_envelope(): void
    {
        config(['services.broadband.url' => 'https://broadband.test']);
        Http::fake([
            'broadband.test/broadband_accounts*' => Http::response([
                'data' => [
                    [
                        'account_number' => '11223344',
                        'customer_name' => 'mimi',
                    ],
                ],
            ]),
        ]);

        $account = app(BroadbandAccountService::class)->findByAccountNumber('11223344');

        $this->assertSame('11223344', $account['account_number']);
        $this->assertSame('mimi', $account['customer_name']);
    }
}
