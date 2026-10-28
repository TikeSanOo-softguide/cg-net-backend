<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreNewsRequest;
use App\Http\Requests\Cms\UpdateNewsRequest;
use App\Models\Category;
use App\Models\News;
use App\Support\CmsBulkDelete;
use App\Support\CmsListing;
use App\Support\StoresPublicImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    public function index(Request $request): Response
    {
        $listing = CmsListing::paginate(
            $request,
            News::query()->with('category:id,name_en'),
            ['title_en', 'title_zh', 'title_my', 'slug'],
            ['title_en', 'status', 'created_at'],
            statusColumn: 'status',
        );

        return Inertia::render('Cms/News/Index', [
            'items' => $listing['paginator']->through(fn (News $item) => $this->payload($item)),
            'filters' => $listing['filters'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Cms/News/Create', $this->formOptions());
    }

    public function store(StoreNewsRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_url'] = StoresPublicImage::store($request->file('image'), 'cms/news');
        }

        $news = News::query()->create($data);

        activity('cms')->causedBy($request->user())->performedOn($news)->event('created')->log('news_created');

        return redirect()->route('cms.news.index')->with('success', 'cms.created');
    }

    public function edit(News $news): Response
    {
        return Inertia::render('Cms/News/Edit', [
            ...$this->formOptions(),
            'item' => $this->payload($news),
        ]);
    }

    public function update(UpdateNewsRequest $request, News $news): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_url'] = StoresPublicImage::store($request->file('image'), 'cms/news', $news->image_url);
        }

        $news->update($data);

        activity('cms')->causedBy($request->user())->performedOn($news)->event('updated')->log('news_updated');

        return redirect()->route('cms.news.index')->with('success', 'cms.updated');
    }

    public function destroy(Request $request, News $news): RedirectResponse
    {
        StoresPublicImage::delete($news->image_url);
        $news->delete();

        activity('cms')->causedBy($request->user())->performedOn($news)->event('deleted')->log('news_deleted');

        return redirect()->route('cms.news.index')->with('success', 'cms.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return CmsBulkDelete::run(
            $request,
            News::query(),
            'cms.news.index',
            'news_deleted',
            beforeDelete: function (News $news): void {
                StoresPublicImage::delete($news->image_url);
            },
        );
    }

    /**
     * @return array{categories: list<array{id: int, name_en: string}>}
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->orderBy('name_en')->get(['id', 'name_en'])->map(fn (Category $category) => [
                'id' => $category->id,
                'name_en' => $category->name_en,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(News $news): array
    {
        return [
            'id' => $news->id,
            'category_id' => $news->category_id,
            'category_name' => $news->category?->name_en,
            'title_en' => $news->title_en,
            'title_zh' => $news->title_zh,
            'title_my' => $news->title_my,
            'slug' => $news->slug,
            'description_en' => $news->description_en,
            'description_zh' => $news->description_zh,
            'description_my' => $news->description_my,
            'status' => $news->status->value,
            'image_url' => StoresPublicImage::url($news->image_url),
            'created_at' => $news->created_at?->toDateString(),
        ];
    }
}
