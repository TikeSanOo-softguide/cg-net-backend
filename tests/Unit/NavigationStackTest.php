<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Support\NavigationStack;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class NavigationStackTest extends TestCase
{
    public function test_opening_a_screen_pushes_it_and_back_pops_it(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        $transactions = $this->request('GET', 'http://localhost:8080/billing/transactions', [
            'X-Navigation' => NavigationStack::RESET,
        ]);
        $this->assertNull(NavigationStack::advance($transactions));

        $customer = $this->request('GET', 'http://localhost:8080/customers/5', [
            'referer' => 'http://localhost:8080/billing/transactions',
        ]);
        $this->assertSame('/billing/transactions', NavigationStack::advance($customer));

        $back = $this->request('GET', 'http://localhost:8080/billing/transactions', [
            'referer' => 'http://localhost:8080/customers/5',
            'X-Navigation' => NavigationStack::POP,
        ]);
        $this->assertNull(NavigationStack::advance($back));
        $this->assertSame(['/billing/transactions'], NavigationStack::all($back));
    }

    public function test_menu_entry_forgets_the_trail(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        NavigationStack::advance($this->request('GET', 'http://localhost:8080/reports', [
            'X-Navigation' => NavigationStack::RESET,
        ]));
        NavigationStack::advance($this->request('GET', 'http://localhost:8080/reports/ledger-health?snapshot=4', [
            'referer' => 'http://localhost:8080/reports',
        ]));

        $menu = $this->request('GET', 'http://localhost:8080/customers', [
            'referer' => 'http://localhost:8080/reports/ledger-health?snapshot=4',
            'X-Navigation' => NavigationStack::RESET,
        ]);

        $this->assertNull(NavigationStack::advance($menu));
        $this->assertSame(['/customers'], NavigationStack::all($menu));
    }

    public function test_same_screen_query_replaces_the_top_instead_of_pushing(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        NavigationStack::advance($this->request('GET', 'http://localhost:8080/customers?search=demo', [
            'X-Navigation' => NavigationStack::RESET,
        ]));

        $filtered = $this->request('GET', 'http://localhost:8080/customers?search=other', [
            'referer' => 'http://localhost:8080/customers?search=demo',
        ]);

        $this->assertNull(NavigationStack::advance($filtered));
        $this->assertSame(['/customers?search=other'], NavigationStack::all($filtered));
    }

    public function test_prefetch_does_not_change_the_trail(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        $first = $this->request('GET', 'http://localhost:8080/customers', [
            'X-Navigation' => NavigationStack::RESET,
        ]);
        NavigationStack::advance($first);

        $prefetch = $this->request('GET', 'http://localhost:8080/customers/5', [
            'referer' => 'http://localhost:8080/customers',
            'Purpose' => 'prefetch',
        ]);
        NavigationStack::advance($prefetch);

        $this->assertSame(['/customers'], NavigationStack::all($prefetch));
    }

    public function test_sanitize_rejects_external_hosts(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        $this->assertNull(NavigationStack::sanitize('https://evil.example/phish'));
        $this->assertNull(NavigationStack::sanitize('javascript:alert(1)'));
        $this->assertSame('/customers?status=active', NavigationStack::sanitize('/customers?status=active'));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(string $method, string $url, array $headers = []): Request
    {
        $request = Request::create($url, $method);
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => new Admin);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $route = new Route('GET', '/{any?}', fn () => null);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }
}
