<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreTagRequest;
use App\Http\Requests\Cms\UpdateTagRequest;
use App\Models\Tag;
use App\Support\CmsBulkDelete;
use App\Support\CmsListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request): Response
    {
        $listing = CmsListing::paginate(
            $request,
            Tag::query(),
            ['name', 'slug'],
            ['name', 'slug', 'lang', 'created_at'],
        );

        return Inertia::render('Cms/Tags/Index', [
            'items' => $listing['paginator']->through(fn (Tag $item) => $this->payload($item)),
            'filters' => $listing['filters'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Cms/Tags/Create');
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        $tag = Tag::query()->create($request->validated());

        activity('cms')->causedBy($request->user())->performedOn($tag)->event('created')->log('tag_created');

        return redirect()->route('cms.tags.index')->with('success', 'cms.created');
    }

    public function edit(Tag $tag): Response
    {
        return Inertia::render('Cms/Tags/Edit', [
            'item' => $this->payload($tag),
        ]);
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        activity('cms')->causedBy($request->user())->performedOn($tag)->event('updated')->log('tag_updated');

        return redirect()->route('cms.tags.index')->with('success', 'cms.updated');
    }

    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        $tag->delete();

        activity('cms')->causedBy($request->user())->performedOn($tag)->event('deleted')->log('tag_deleted');

        return redirect()->route('cms.tags.index')->with('success', 'cms.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return CmsBulkDelete::run($request, Tag::query(), 'cms.tags.index', 'tag_deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'lang' => $tag->lang->value,
            'created_at' => $tag->created_at?->toDateString(),
        ];
    }
}
