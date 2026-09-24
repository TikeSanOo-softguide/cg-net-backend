<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppVersion\AppVersionResource;
use App\Models\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppVersionController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $platform = $request->input('platform');

        $versions = AppVersion::query()
            ->where('status', 'active')
            ->when($platform, fn($query) => $query->whereIn('platform', [$platform, 'all']))
            ->latest()
            ->get();

        return AppVersionResource::collection($versions);
    }
}
