<?php

namespace App\Http\Controllers\Api\News;

use App\Http\Controllers\Controller;
use App\Http\Resources\News\NewsResource;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $perPage = $request->integer('per_page', 6);

        $query = News::with('category')->where('status', 'published');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")
                    ->orWhere('title_my', 'like', "%{$search}%")
                    ->orWhere('title_zh', 'like', "%{$search}%")
                    ->orWhere('description_en', 'like', "%{$search}%")
                    ->orWhere('description_my', 'like', "%{$search}%")
                    ->orWhere('description_zh', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $category = $request->input('category');

            $query->whereHas('category', function ($q) use ($category) {
                $q->where('slug', $category);
            });
        }

        $news = $query->latest()->paginate($perPage);

        return NewsResource::collection($news);
    }

    public function feed(Request $request): JsonResource
    {
        $news = News::with('category')->where('status', 'published')->latest()->cursorPaginate(20);

        return NewsResource::collection($news);
    }

    public function show(string $slug): JsonResource
    {
        $news = News::with('category')->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return NewsResource::make($news);
    }
}
