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
        $news = News::with('category')->where('status', 'published')->latest()->paginate(6);

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
