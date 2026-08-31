<?php

namespace App\Http\Controllers\Api\Package;

use App\Http\Controllers\Controller;
use App\Http\Resources\Package\PackageResource;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageController extends Controller
{
    /**
     * GET /packages
     */
    public function index(Request $request): JsonResource
    {
        $packages = Package::query()
            ->with(['network', 'speed', 'term'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return PackageResource::collection($packages);
    }

    /**
     * GET /packages/recommended
     */
    public function recommended(Request $request): JsonResource
    {
        $packages = Package::query()
            ->with(['network', 'speed', 'term'])
            ->where('is_active', true)
            ->where('recommended', true)
            ->orderBy('sort_order')
            ->get();

        return PackageResource::collection($packages);
    }

    /**
     * GET /packages/{id}
     */
    public function show(int $id): JsonResource
    {
        $package = Package::with(['network', 'speed', 'term'])
            ->where('is_active', true)
            ->findOrFail($id);

        return PackageResource::make($package);
    }
}
