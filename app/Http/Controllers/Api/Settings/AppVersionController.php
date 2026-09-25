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
            ->where('platform', '!=', 'all')
            ->when($platform, fn($query) => $query->where('platform', $platform))
            ->get()
            ->groupBy('platform')
            ->map(function ($group) {
                return $group
                    ->sort(function ($a, $b) {
                        $verA = ltrim($a->version, 'vV');
                        $verB = ltrim($b->version, 'vV');

                        return version_compare($verB, $verA);
                    })
                    ->first();
            })
            ->values();

        return AppVersionResource::collection($versions);
    }
}
