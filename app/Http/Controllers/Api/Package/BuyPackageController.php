<?php

namespace App\Http\Controllers\Api\Package;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Package\BuyPackageRequest;
use App\Models\User;
use App\Services\Package\BuyPackageService;
use App\Support\CustomerPassword;
use Illuminate\Http\JsonResponse;

class BuyPackageController extends Controller
{
    public function __construct(protected BuyPackageService $buyPackageService) {}

    public function buy(BuyPackageRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        CustomerPassword::verify($user, (string) $request->validated('password'));

        $result = $this->buyPackageService->buy(
            user: $user,
            productId: (string) $request->validated('product_id'),
            idempotencyKey: (string) $request->validated('idempotency_key'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($result['body'], $result['http_status']);
    }
}
