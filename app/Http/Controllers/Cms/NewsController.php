<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreNewsRequest;
use App\Http\Requests\Cms\UpdateNewsRequest;
use App\Models\Category;
use App\Models\News;
use App\Models\Tag;
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
            News::query()->with(['category:id,name', 'tags:id,name']),
            ['title', 'slug'],
            ['title', 'status', 'lang', 'created_at'],
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
        $data = $request->safe()->except(['image', 'tag_ids']);

        if ($request->hasFile('image')) {
            $data['image_path'] = StoresPublicImage::store($request->file('image'), 'cms/news');
        }

        $news = News::query()->create($data);
        $news->tags()->sync($request->validated('tag_ids') ?? []);

        activity('cms')->causedBy($request->user())->performedOn($news)->event('created')->log('news_created');

        return redirect()->route('cms.news.index')->with('success', 'cms.created');
    }

    public function edit(News $news): Response
    {
        $news->load('tags:id');

        return Inertia::render('Cms/News/Edit', [
            ...$this->formOptions(),
            'item' => $this->payload($news),
        ]);
    }

    public function update(UpdateNewsRequest $request, News $news): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'tag_ids']);

        if ($request->hasFile('image')) {
            $data['image_path'] = StoresPublicImage::store($request->file('image'), 'cms/news', $news->image_path);
        }

        $news->update($data);
        $news->tags()->sync($request->validated('tag_ids') ?? []);

        activity('cms')->causedBy($request->user())->performedOn($news)->event('updated')->log('news_updated');

        return redirect()->route('cms.news.index')->with('success', 'cms.updated');
    }

    public function destroy(Request $request, News $news): RedirectResponse
    {
        StoresPublicImage::delete($news->image_path);
        $news->tags()->detach();
        $news->delete();

        activity('cms')->causedBy($request->user())->performedOn($news)->event('deleted')->log('news_deleted');

        return redirect()->route('cms.news.index')->with('success', 'cms.deleted');
    }

    /**
     * @return array{categories: list<array{id: int, name: string}>, tags: list<array{id: int, name: string}>}
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name'])->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])->all(),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name'])->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
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
            'lang' => $news->lang->value,
            'image_url' => StoresPublicImage::url($news->image_path),
            'tag_ids' => $news->relationLoaded('tags') ? $news->tags->pluck('id')->all() : [],
            'tag_names' => $news->relationLoaded('tags') ? $news->tags->pluck('name')->all() : [],
            'created_at' => $news->created_at?->toDateString(),
        ];
    }
}
