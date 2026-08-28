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
            News::query()->with('category:id,name_en,name_zh,name_my'),
            ['title_en', 'title_zh', 'title_my', 'slug'],
            ['title_en', 'title_zh', 'title_my', 'status', 'created_at'],
            statusColumn: 'status',
        );

        return Inertia::render('Cms/news/Index', [
            'items' => $listing['paginator']->through(fn(News $item) => $this->payload($item)),
            'filters' => $listing['filters'],
            ...$this->formOptions(),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('cms.news.index');
    }

    public function store(StoreNewsRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_url'] = StoresPublicImage::store($request->file('image'), 'cms/news');
        } else {
            $data['image_url'] = null;
        }

        $news = News::query()->create([
            'category_id' => $data['category_id'],
            'title_en' => $data['title_en'],
            'title_zh' => $data['title_zh'],
            'title_my' => $data['title_my'],
            'description_en' => $data['description_en'],
            'description_zh' => $data['description_zh'],
            'description_my' => $data['description_my'],
            'image_url' => $data['image_url'],
            'status' => $data['status'],
            'slug' => $data['slug'],
        ]);

        activity('cms')->causedBy($request->user())->performedOn($news)->event('created')->log('news_created');

        return redirect()->route('cms.news.index')->with('success', 'cms.news.created');
    }

    public function edit(News $news): RedirectResponse
    {
        return redirect()->route('cms.news.index');
    }

    public function update(UpdateNewsRequest $request, News $news): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_url'] = StoresPublicImage::store($request->file('image'), 'cms/news', $news->image_url);
        } elseif (array_key_exists('image_url', $data) && !isset($data['image_url'])) {
            $data['image_url'] = null;
            StoresPublicImage::delete($news->image_url);
        } else {
            $data['image_url'] = $news->image_url;
        }

        $news->update([
            'category_id' => $data['category_id'],
            'title_en' => $data['title_en'],
            'title_zh' => $data['title_zh'],
            'title_my' => $data['title_my'],
            'description_en' => $data['description_en'],
            'description_zh' => $data['description_zh'],
            'description_my' => $data['description_my'],
            'image_url' => $data['image_url'],
            'status' => $data['status'],
            'slug' => $data['slug'],
        ]);

        activity('cms')->causedBy($request->user())->performedOn($news)->event('updated')->log('news_updated');

        return redirect()->route('cms.news.index')->with('success', 'cms.news.updated');
    }

    public function destroy(Request $request, News $news): RedirectResponse
    {
        StoresPublicImage::delete($news->image_path);
        $news->delete();

        activity('cms')->causedBy($request->user())->performedOn($news)->event('deleted')->log('news_deleted');

        return redirect()->route('cms.news.index')->with('success', 'cms.news.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return CmsBulkDelete::run(
            $request,
            News::query(),
            'cms.news.index',
            'news_deleted',
            beforeDelete: function (News $news): void {
                StoresPublicImage::delete($news->image_path);
            },
        );
    }

    /**
     * @return array{categories: list<array{id: int, name: string}>}
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::query()
                ->orderBy('name_en')
                ->get(['id', 'name_en'])
                ->map(
                    fn(Category $category) => [
                        'id' => $category->id,
                        'name' => $category->name_en,
                    ],
                )
                ->all(),
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
            'category_name_en' => $news->category?->name_en,
            'category_name_zh' => $news->category?->name_zh,
            'category_name_my' => $news->category?->name_my,
            'title_en' => $news->title_en,
            'title_zh' => $news->title_zh,
            'title_my' => $news->title_my,
            'description_en' => $news->description_en,
            'description_zh' => $news->description_zh,
            'description_my' => $news->description_my,
            'slug' => $news->slug,
            'status' => $news->status->value,
            'image_url' => StoresPublicImage::url($news->image_url),
            'created_at' => $news->created_at?->toDateString(),
        ];
    }
}
