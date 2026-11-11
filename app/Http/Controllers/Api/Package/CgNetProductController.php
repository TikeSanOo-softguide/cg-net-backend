<?php

namespace App\Http\Controllers\Api\Package;

use App\Enums\MessageCode;
use App\Http\Controllers\Controller;
use App\Services\Package\CgNetProductCatalog;
use App\Support\ApiMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CgNetProductController extends Controller
{
    public function __construct(private CgNetProductCatalog $catalog) {}

    /**
     * GET /web-app/cg-net-products
     *
     * Same WalletApp call as the installation catalog: CGNETProductInfoListAPI.
     */
    public function index(): JsonResponse
    {
        try {
            $products = $this->catalog->products();
        } catch (RuntimeException $exception) {
            Log::warning('CG-Net product catalog unavailable.', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json(
                ApiMessage::attach(MessageCode::PkgCatalogUnavailable),
                503,
            );
        }

        return response()->json([
            'ErrCode' => 0,
            'Message' => 'ok',
            'result' => $products,
        ]);
    }
}
