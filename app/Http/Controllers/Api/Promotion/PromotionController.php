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
        $lang = $request->input('lang', 'en');
        $supportedLanguages = ['en', 'my', 'zh'];

        if (!in_array($lang, $supportedLanguages, true)) {
            $lang = 'en';
        }

        $titleColumn = "title_{$lang}";

        $promotions = Promotion::query()
            ->where('is_active', true)
            ->where(function ($query) use ($today) {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->when($request->filled('search'), function ($query) use ($request, $titleColumn) {
                $search = trim($request->input('search'));
                $query->where(function ($q) use ($search, $titleColumn) {
                    $q->where($titleColumn, 'like', "%{$search}%");
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
