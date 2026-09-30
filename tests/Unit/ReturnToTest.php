<?php

namespace Tests\Unit;

use App\Support\ReturnTo;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReturnToTest extends TestCase
{
    public function test_remember_stores_sanitized_current_url(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        $request = Request::create('http://localhost:8080/reports/ledger-health?snapshot=3', 'GET');
        $request->setLaravelSession($this->app['session']->driver());

        ReturnTo::remember($request, 'transactions.index');

        $this->assertSame(
            '/reports/ledger-health?snapshot=3',
            ReturnTo::get('transactions.index'),
        );
    }

    public function test_capture_referer_stores_internal_referer(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        $request = Request::create('http://localhost:8080/billing/transactions?search=FTTH-1', 'GET');
        $request->headers->set('referer', 'http://localhost:8080/reports/ledger-health?snapshot=9');
        $request->setLaravelSession($this->app['session']->driver());

        ReturnTo::captureReferer($request, 'transactions.index');

        $this->assertSame(
            '/reports/ledger-health?snapshot=9',
            ReturnTo::get('transactions.index'),
        );
    }

    public function test_capture_referer_ignores_same_destination_path(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        $request = Request::create('http://localhost:8080/billing/transactions?page=2', 'GET');
        $request->headers->set('referer', 'http://localhost:8080/billing/transactions?search=x');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put(ReturnTo::sessionKey('transactions.index'), '/reports/ledger-health');

        ReturnTo::captureReferer($request, 'transactions.index');

        $this->assertSame('/reports/ledger-health', ReturnTo::get('transactions.index'));
    }

    public function test_sanitize_rejects_external_hosts(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        $this->assertNull(ReturnTo::sanitize('https://evil.example/phish'));
        $this->assertNull(ReturnTo::sanitize('javascript:alert(1)'));
        $this->assertSame('/customers?status=active', ReturnTo::sanitize('/customers?status=active'));
    }

    public function test_prop_uses_fallback_when_missing(): void
    {
        $this->assertSame(
            ['return_to' => '/customers'],
            ReturnTo::prop('customers.show', '/customers'),
        );
    }
}
