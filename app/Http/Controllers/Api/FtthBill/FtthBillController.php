<?php

namespace App\Http\Controllers\Api\FtthBill;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FtthBill\PayFtthBillRequest;
use App\Services\FtthBill\FtthBillPaymentService;
use Illuminate\Http\JsonResponse;

class FtthBillController extends Controller
{
    public function __construct(
        protected FtthBillPaymentService $payments,
    ) {}

    public function pay(PayFtthBillRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->payments->pay(
            user: $request->user(),
            accountNumber: (string) $validated['broadband_account_number'],
            idempotencyKey: (string) $validated['idempotency_key'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($result['body'], $result['http_status']);
    }
}
