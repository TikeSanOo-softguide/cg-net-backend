<?php

namespace App\Http\Controllers\Api\Redeem;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Redeem\SerialNoCheckRequest;
use App\Http\Requests\Api\Redeem\TopUpAccountRequest;
use App\Services\TopUpCard\TopUpCardRedemptionService;
use Illuminate\Http\JsonResponse;

class RedeemController extends Controller
{
    public function __construct(private readonly TopUpCardRedemptionService $redemption) {}

    public function checkSerialNo(SerialNoCheckRequest $request): JsonResponse
    {
        $result = $this->redemption->checkSerialNo(
            user: $request->user(),
            serialNo: (string) $request->validated('serial_no'),
            ipAddress: $request->ip(),
        );

        return response()->json($result['body'], $result['http_status'], $result['headers']);
    }

    public function topUpAccount(TopUpAccountRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->redemption->redeem(
            user: $request->user(),
            phone: (string) $validated['phone'],
            pin: (string) $validated['pin'],
            idempotencyKey: (string) $validated['idempotency_key'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($result['body'], $result['http_status'], $result['headers']);
    }
}