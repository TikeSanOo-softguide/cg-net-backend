<?php

namespace App\Http\Controllers\Api\Redeem;

use App\Enums\TopUpCardStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Redeem\SerialNoCheckRequest;
use App\Http\Requests\Api\Redeem\TopUpAccountRequest;
use App\Models\TopUpCard;
use App\Models\User;
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

        $message = match ($status) {
            TopUpCardStatus::Active => 'This top-up card is valid.',
            TopUpCardStatus::Used => 'This top-up card has already been used.',
            TopUpCardStatus::Expired => 'This top-up card has expired.',
            TopUpCardStatus::Blocked => 'This top-up card has been blocked.',
            default => 'This top-up card is invalid.',
        };

        return response()->json(['message' => $message], $isValid ? 200 : 400);
    }

    public function topUpAccount(TopUpAccountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $result = $this->redemption->redeem(
            user: $user,
            phone: (string) $data['phone'],
            pin: (string) $data['pin'],
            idempotencyKey: (string) $data['idempotency_key'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($result['body'], $result['http_status'], $result['headers']);
    }
}