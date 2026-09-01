<?php

namespace App\Http\Controllers\Api\Promotion;

use App\Http\Controllers\Controller;
use App\Http\Resources\Promotion\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index(Request $request)
    {
        $today = today();
        $promotions = Promotion::query()
            ->where('is_active', true)
            ->where(function ($query) use ($today) {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('title_en', 'like', "%{$search}%")
                        ->orWhere('title_my', 'like', "%{$search}%")
                        ->orWhere('title_zh', 'like', "%{$search}%")
                        ->orWhere('description_en', 'like', "%{$search}%")
                        ->orWhere('description_my', 'like', "%{$search}%")
                        ->orWhere('description_zh', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('start_date')
            ->latest()
            ->paginate(6);

        return PromotionResource::collection($promotions);
    }

    public function show(string $slug)
    {
        $today = today();

        $promotion = Promotion::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where(function ($query) use ($today) {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->firstOrFail();

        return new PromotionResource($promotion);
    }
}
