<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreCategoryRequest;
use App\Http\Requests\Cms\UpdateCategoryRequest;
use App\Models\Category;
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
            ['name', 'slug'],
            ['name', 'slug', 'lang', 'created_at'],
        );

        return Inertia::render('Cms/Categories/Index', [
            'items' => $listing['paginator']->through(fn (Category $item) => $this->payload($item)),
            'filters' => $listing['filters'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Cms/Categories/Create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = Category::query()->create($request->validated());

        activity('cms')->causedBy($request->user())->performedOn($category)->event('created')->log('category_created');

        return redirect()->route('cms.categories.index')->with('success', 'cms.created');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Cms/Categories/Edit', [
            'item' => $this->payload($category),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        activity('cms')->causedBy($request->user())->performedOn($category)->event('updated')->log('category_updated');

        return redirect()->route('cms.categories.index')->with('success', 'cms.updated');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        if ($category->news()->exists()) {
            return back()->withErrors(['delete' => __('cms.category_in_use')]);
        }

        $category->delete();

        activity('cms')->causedBy($request->user())->performedOn($category)->event('deleted')->log('category_deleted');

        return redirect()->route('cms.categories.index')->with('success', 'cms.deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'lang' => $category->lang->value,
            'news_count' => $category->news_count ?? $category->news()->count(),
            'created_at' => $category->created_at?->toDateString(),
        ];
    }
}
