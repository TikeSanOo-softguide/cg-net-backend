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
        $perPage = $request->input('per_page', 6);
        $services = Service::query()->where('status', 'published')->paginate($perPage);

        return ServiceResource::collection($services);
    }
}
