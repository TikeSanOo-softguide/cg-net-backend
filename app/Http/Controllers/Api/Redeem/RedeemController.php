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
        $card = TopUpCard::query()->where('serial_no', $request->validated('serial_no'))->first();

        $result = match (true) {
            $card === null => 'invalid_card',
            $card->status === TopUpCardStatus::Used || $card->redeemed_at !== null => 'already_used',
            $card->status === TopUpCardStatus::Active && $card->expires_at >= now()->toDateString() => 'valid',
            default => 'invalid_card',
        };

        return response()->json(
            [
                'result' => $result,
                'message' => match ($result) {
                    'valid' => 'This top-up card is valid.',
                    'already_used' => 'This top-up card has already been used.',
                    default => 'Invalid top-up card.',
                },
            ],
            $result === 'valid' ? 200 : 400,
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
