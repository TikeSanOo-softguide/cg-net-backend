<?php

namespace Tests\Unit;

use App\Services\Transaction\TransactionService;
use Illuminate\Http\Request;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    public function test_unfiltered_transaction_page_defaults_to_today(): void
    {
        $filters = app(TransactionService::class)->filters(Request::create('/billing/transactions', 'GET'));

        $this->assertSame(today()->toDateString(), $filters['from']);
        $this->assertSame(today()->toDateString(), $filters['to']);
    }

    public function test_transaction_deep_links_do_not_get_a_default_date_range(): void
    {
        $request = Request::create('/billing/transactions?search=FTTH-1&open_transaction=FTTH-1', 'GET');
        $filters = app(TransactionService::class)->filters($request);

        $this->assertSame('', $filters['from']);
        $this->assertSame('', $filters['to']);
    }

    public function test_explicitly_empty_dates_remain_unbounded(): void
    {
        $request = Request::create('/billing/transactions?from=&to=', 'GET');
        $filters = app(TransactionService::class)->filters($request);

        $this->assertSame('', $filters['from']);
        $this->assertSame('', $filters['to']);
    }
}
