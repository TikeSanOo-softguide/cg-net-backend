<?php

namespace Tests\Unit;

use App\Http\Controllers\Dev\FakeBillingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FakeBillingControllerTest extends TestCase
{
    public function test_fake_billing_server_advances_pending_bill_month_after_successful_payment(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->startOfDay());

        $account = 'FAKE-MONTH-' . uniqid();
        $paymentRef = 'FAKE-PAYMENT-' . uniqid();
        Cache::forget('fake-billing:next-bill-month:' . $account);
        Cache::forget('fake-billing:advanced:' . $paymentRef);
        $controller = new FakeBillingController();

        $firstSlip = $controller->billDetails(Request::create('/bill-details', 'GET', [
            'account_number' => $account,
        ]))->getData(true);
        $this->assertSame('2026-10', $firstSlip['bill_month']);

        $payment = $controller->extendPlan(Request::create('/extend-plan', 'POST', [
            'account_number' => $account,
            'payment_ref' => $paymentRef,
        ]))->getData(true);
        $this->assertTrue($payment['success']);

        $controller->extendPlan(Request::create('/extend-plan', 'POST', [
            'account_number' => $account,
            'payment_ref' => $paymentRef,
        ]));

        $nextSlip = $controller->billDetails(Request::create('/bill-details', 'GET', [
            'account_number' => $account,
        ]))->getData(true);
        $this->assertSame('2026-11', $nextSlip['bill_month']);
        $this->assertCount(1, $nextSlip['paid_slips']);
        $this->assertSame('2026-10', $nextSlip['paid_slips'][0]['bill_month']);
        $this->assertSame($paymentRef, $nextSlip['paid_slips'][0]['payment_ref']);
        $this->assertSame($payment['billing_ref'], $nextSlip['paid_slips'][0]['billing_ref']);

        Cache::forget('fake-billing:next-bill-month:' . $account);
        Cache::forget('fake-billing:paid-slips:' . $account);
        Cache::forget('fake-billing:advanced:' . $paymentRef);
        Cache::forget('fake-billing:billing-ref:' . $paymentRef);
    }
}
