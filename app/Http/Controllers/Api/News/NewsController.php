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
        $lang = $request->input('lang', 'en');
        $supportedLanguages = ['en', 'my', 'zh'];

        if (!in_array($lang, $supportedLanguages, true)) {
            $lang = 'en';
        }

        $titleColumn = "title_{$lang}";
        $descriptionColumn = "description_{$lang}";
        $categoryColumn = "name_{$lang}";

        $query = News::with('category')->where('status', 'published');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($q) use ($search, $titleColumn, $descriptionColumn, $categoryColumn) {
                $q->where($titleColumn, 'like', "%{$search}%")
                    ->orWhere($descriptionColumn, 'like', "%{$search}%")
                    ->orWhereHas('category', function ($categoryQuery) use ($search, $categoryColumn) {
                        $categoryQuery->where($categoryColumn, 'like', "%{$search}%");
                    });
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
