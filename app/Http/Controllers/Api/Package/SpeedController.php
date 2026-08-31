<?php

namespace App\Http\Controllers\Api\Package;

use App\Http\Controllers\Controller;
use App\Http\Resources\Package\SpeedResource;
use App\Models\Speed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpeedController extends Controller
{
    /**
     * GET /speeds
     */
    public function index(Request $request): JsonResource
    {
        $speeds = Speed::query()->orderBy('mbps')->get();

        return SpeedResource::collection($speeds);
    }
}
