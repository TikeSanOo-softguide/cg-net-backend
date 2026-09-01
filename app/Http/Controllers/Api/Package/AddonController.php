<?php

namespace App\Http\Controllers\Api\Package;

use App\Http\Controllers\Controller;
use App\Http\Resources\Package\AddonResource;
use App\Models\Addon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonController extends Controller
{
    /**
     * GET /addons
     */
    public function index(Request $request): JsonResource
    {
        $addons = Addon::query()->get();

        return AddonResource::collection($addons);
    }
}
