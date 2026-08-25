<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreGalleryRequest;
use App\Http\Requests\Cms\UpdateGalleryRequest;
use App\Models\Gallery;
use App\Support\CmsBulkDelete;
use App\Support\CmsListing;
use App\Support\StoresPublicImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function index(Request $request): Response
    {
        $listing = CmsListing::paginate(
            $request,
            Gallery::query(),
            ['label'],
            ['label', 'created_at'],
        );

        return Inertia::render('Cms/gallery/Index', [
            'items' => $listing['paginator']->through(fn (Gallery $item) => $this->payload($item)),
            'filters' => $listing['filters'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Cms/gallery/Create');
    }

    public function store(StoreGalleryRequest $request): RedirectResponse
    {
        $gallery = Gallery::query()->create([
            'label' => $request->validated('label'),
            'image_path' => StoresPublicImage::store($request->file('image'), 'cms/gallery'),
        ]);

        activity('cms')->causedBy($request->user())->performedOn($gallery)->event('created')->log('gallery_created');

        return redirect()->route('cms.gallery.index')->with('success', 'cms.created');
    }

    public function edit(Gallery $gallery): Response
    {
        return Inertia::render('Cms/gallery/Edit', [
            'item' => $this->payload($gallery),
        ]);
    }

    public function update(UpdateGalleryRequest $request, Gallery $gallery): RedirectResponse
    {
        $data = [
            'label' => $request->validated('label'),
        ];

        if ($request->hasFile('image')) {
            $data['image_path'] = StoresPublicImage::store($request->file('image'), 'cms/gallery', $gallery->image_path);
        }

        $gallery->update($data);

        activity('cms')->causedBy($request->user())->performedOn($gallery)->event('updated')->log('gallery_updated');

        return redirect()->route('cms.gallery.index')->with('success', 'cms.updated');
    }

    public function destroy(Request $request, Gallery $gallery): RedirectResponse
    {
        StoresPublicImage::delete($gallery->image_path);
        $gallery->delete();

        activity('cms')->causedBy($request->user())->performedOn($gallery)->event('deleted')->log('gallery_deleted');

        return redirect()->route('cms.gallery.index')->with('success', 'cms.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return CmsBulkDelete::run(
            $request,
            Gallery::query(),
            'cms.gallery.index',
            'gallery_deleted',
            beforeDelete: fn (Gallery $gallery) => StoresPublicImage::delete($gallery->image_path),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Gallery $gallery): array
    {
        return [
            'id' => $gallery->id,
            'label' => $gallery->label,
            'image_url' => StoresPublicImage::url($gallery->image_path),
            'created_at' => $gallery->created_at?->toDateString(),
        ];
    }
}
