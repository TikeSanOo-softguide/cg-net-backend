<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreCategoryRequest;
use App\Http\Requests\Cms\UpdateCategoryRequest;
use App\Models\Category;
use App\Support\CmsBulkDelete;
use App\Support\CmsListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $listing = CmsListing::paginate(
            $request,
            Category::query()->withCount('news'),
            ['name_en', 'name_zh', 'name_my', 'slug'],
            ['name_en', 'name_zh', 'name_my', 'slug', 'created_at'],
        );

        return Inertia::render('Cms/category/Index', [
            'items' => $listing['paginator']->through(fn(Category $item) => $this->payload($item)),
            'filters' => $listing['filters'],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('cms.categories.index');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $category = Category::query()->create([
            'name_en' => $data['name_en'],
            'name_zh' => $data['name_zh'],
            'name_my' => $data['name_my'],
            'slug' => $data['slug'],
        ]);

        activity('cms')->causedBy($request->user())->performedOn($category)->event('created')->log('category_created');

        return redirect()->route('cms.categories.index')->with('success', 'cms.category.created');
    }

    public function edit(Category $category): RedirectResponse
    {
        return redirect()->route('cms.categories.index');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();
        $category->update([
            'name_en' => $data['name_en'],
            'name_zh' => $data['name_zh'],
            'name_my' => $data['name_my'],
            'slug' => $data['slug'],
        ]);

        activity('cms')->causedBy($request->user())->performedOn($category)->event('updated')->log('category_updated');

        return redirect()->route('cms.categories.index')->with('success', 'cms.category.updated');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        if ($category->news()->exists()) {
            return back()->withErrors(['delete' => 'cms.category.in_use']);
        }

        $category->delete();

        activity('cms')->causedBy($request->user())->performedOn($category)->event('deleted')->log('category_deleted');

        return redirect()->route('cms.categories.index')->with('success', 'cms.category.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return CmsBulkDelete::run(
            $request,
            Category::query(),
            'cms.categories.index',
            'category_deleted',
            deletionError: fn(Category $category) => $category->news()->exists() ? 'cms.category.in_use' : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name_en' => $category->name_en,
            'name_zh' => $category->name_zh,
            'name_my' => $category->name_my,
            'slug' => $category->slug,
            'news_count' => $category->news_count ?? $category->news()->count(),
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ];
    }
}
