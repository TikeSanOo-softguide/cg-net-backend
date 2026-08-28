<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;

class BannerController extends Controller
{
    public function show()
    {
        $banners = Banner::where('is_active', 1)->where(function ($query) {
            $query->whereNull('start_date')
                ->orWhere('start_date', '<=', now());
        })
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })->get();

        return BannerResource::collection($banners);
    }
}
