<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreBannerRequest;
use App\Http\Requests\Cms\UpdateBannerRequest;
use App\Models\Banner;
use App\Support\CmsBulkDelete;
use App\Support\CmsListing;
use App\Support\StoresPublicImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class BannerController extends Controller
{
    public function index(Request $request): Response
    {
        $listing = CmsListing::paginate(
            $request,
            Banner::query(),
            [],
            ['sort_order', 'is_active', 'start_date', 'end_date', 'created_at'],
            defaultSort: 'sort_order',
            statusColumn: 'is_active',
        );

        return Inertia::render('Cms/Banners/Index', [
            'items' => $listing['paginator']->through(fn (Banner $item) => $this->payload($item)),
            'filters' => $listing['filters'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Cms/Banners/Create');
    }

    public function store(StoreBannerRequest $request): RedirectResponse
    {
        $banner = Banner::query()->create($this->attributes($request));

        activity('cms')->causedBy($request->user())->performedOn($banner)->event('created')->log('banner_created');

        return redirect()->route('cms.banners.index')->with('success', 'cms.created');
    }

    public function edit(Banner $banner): Response
    {
        return Inertia::render('Cms/Banners/Edit', [
            'item' => $this->payload($banner),
        ]);
    }

    public function update(UpdateBannerRequest $request, Banner $banner): RedirectResponse
    {
        $banner->update($this->attributes($request, $banner));

        activity('cms')->causedBy($request->user())->performedOn($banner)->event('updated')->log('banner_updated');

        return redirect()->route('cms.banners.index')->with('success', 'cms.updated');
    }

    public function destroy(Request $request, Banner $banner): RedirectResponse
    {
        $this->deleteImages($banner);
        $banner->delete();

        activity('cms')->causedBy($request->user())->performedOn($banner)->event('deleted')->log('banner_deleted');

        return redirect()->route('cms.banners.index')->with('success', 'cms.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return CmsBulkDelete::run(
            $request,
            Banner::query(),
            'cms.banners.index',
            'banner_deleted',
            beforeDelete: fn (Banner $banner) => $this->deleteImages($banner),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreBannerRequest|UpdateBannerRequest $request, ?Banner $banner = null): array
    {
        $validated = $request->validated();
        $data = [
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ];

        foreach (['en', 'zh', 'my'] as $locale) {
            $file = $request->file('image_'.$locale);

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $column = 'image_url_'.$locale;
            $data[$column] = StoresPublicImage::store($file, 'cms/banners', $banner?->{$column});
        }

        return $data;
    }

    private function deleteImages(Banner $banner): void
    {
        StoresPublicImage::delete($banner->image_url_en);
        StoresPublicImage::delete($banner->image_url_zh);
        StoresPublicImage::delete($banner->image_url_my);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Banner $banner): array
    {
        return [
            'id' => $banner->id,
            'image_url_en' => StoresPublicImage::url($banner->image_url_en),
            'image_url_zh' => StoresPublicImage::url($banner->image_url_zh),
            'image_url_my' => StoresPublicImage::url($banner->image_url_my),
            'sort_order' => $banner->sort_order,
            'is_active' => $banner->is_active,
            'start_date' => $banner->start_date?->toDateString(),
            'end_date' => $banner->end_date?->toDateString(),
            'created_at' => $banner->created_at?->toDateString(),
        ];
    }
}
