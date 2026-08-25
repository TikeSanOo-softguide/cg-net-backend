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
            ['title', 'slug'],
            ['title', 'status', 'created_at'],
            statusColumn: 'status',
        );

        return Inertia::render('Cms/news/Index', [
            'items' => $listing['paginator']->through(fn (News $item) => $this->payload($item)),
            'filters' => $listing['filters'],
            ...$this->formOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Cms/news/Create', $this->formOptions());
    }

    public function store(StoreNewsRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_path'] = StoresPublicImage::store($request->file('image'), 'cms/news');
        }

        $news = News::query()->create($data);

        activity('cms')->causedBy($request->user())->performedOn($news)->event('created')->log('news_created');

        return redirect()->route('cms.news.index')->with('success', 'cms.created');
    }

    public function edit(News $news): Response
    {
        return Inertia::render('Cms/news/Edit', [
            ...$this->formOptions(),
            'item' => $this->payload($news),
        ]);
    }

    public function update(UpdateNewsRequest $request, News $news): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_path'] = StoresPublicImage::store($request->file('image'), 'cms/news', $news->image_path);
        }

        $news->update($data);

        activity('cms')->causedBy($request->user())->performedOn($news)->event('updated')->log('news_updated');

        return redirect()->route('cms.news.index')->with('success', 'cms.updated');
    }

    public function destroy(Request $request, News $news): RedirectResponse
    {
        StoresPublicImage::delete($news->image_path);
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
            'categories' => Category::query()->orderBy('name_en')->get(['id', 'name_en'])->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name_en,
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
            'category_name' => $news->category?->name,
            'title' => $news->title,
            'slug' => $news->slug,
            'content' => $news->content,
            'status' => $news->status->value,
            'image_url' => StoresPublicImage::url($news->image_path),
            'created_at' => $news->created_at?->toDateString(),
        ];
    }
}
