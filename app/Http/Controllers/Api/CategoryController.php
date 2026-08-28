<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CategoryResource;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $categories = Category::select('id', 'slug', 'name_en', 'name_zh', 'name_my')->get();

        return CategoryResource::collection($categories);
    }

    public function show(string $slug): JsonResource
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        return CategoryResource::make($category);
    }
}