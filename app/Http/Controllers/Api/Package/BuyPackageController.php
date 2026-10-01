<?php

namespace App\Http\Controllers\Api\Package;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Package\BuyPackageRequest;
use App\Services\Package\BuyPackageService;
use Illuminate\Http\JsonResponse;

class BuyPackageController extends Controller
{
    public function __construct(
        protected BuyPackageService $buyPackageService,
    ) {}

    public function buy(BuyPackageRequest $request): JsonResponse
    {
        $result = $this->buyPackageService->buy(
            user: $request->user(),
            packageId: (int) $request->validated('package_id'),
            idempotencyKey: (string) $request->validated('idempotency_key'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($result['body'], $result['http_status']);
    }
}
