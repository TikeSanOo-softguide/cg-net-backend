<?php

namespace App\Http\Controllers\Api\Redeem;

use App\Enums\TopUpCardStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Redeem\SerialNoCheckRequest;
use App\Http\Requests\Api\Redeem\TopUpAccountRequest;
use App\Models\TopUpCard;
use App\Services\TopUpCard\TopUpCardRedemptionService;
use Illuminate\Http\JsonResponse;

class RedeemController extends Controller
{
    public function __construct(private readonly TopUpCardRedemptionService $redemption) {}

    public function checkSerialNo(SerialNoCheckRequest $request): JsonResponse
    {
        $status = TopUpCard::query()
            ->where('serial_no', $request->validated('serial_no'))
            ->value('status');

        $isValid = $status === TopUpCardStatus::Active;

        // Avoid leaking precise lifecycle state to authenticated clients.
        return response()->json(
            ['message' => $isValid ? 'This top-up card is valid.' : 'This top-up card is invalid.'],
            $isValid ? 200 : 400,
        );
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