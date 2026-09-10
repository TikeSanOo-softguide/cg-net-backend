<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Http\Resources\Service\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $services = Service::query()->where('status', 'published')->get();

        return ServiceResource::collection($services);
    }
}
