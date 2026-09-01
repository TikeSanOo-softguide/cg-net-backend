<?php

namespace App\Http\Controllers\Api\Package;

use App\Http\Controllers\Controller;
use App\Http\Resources\Package\NetworkResource;
use App\Models\Network;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NetworkController extends Controller
{
    /**
     * GET /networks
     */
    public function index(Request $request): JsonResource
    {
        $networks = Network::query()->get();

        return NetworkResource::collection($networks);
    }
}
