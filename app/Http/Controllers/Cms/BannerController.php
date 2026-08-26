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
            ['is_active', 'start_date'],
            ['sort_order', 'is_active', 'start_date', 'end_date', 'created_at'],
            defaultSort: 'sort_order',
            statusColumn: 'is_active',
        );

        return Inertia::render('Cms/banner/Index', [
            'items' => $listing['paginator']->through(fn(Banner $item) => $this->payload($item)),
            'filters' => $listing['filters'],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('cms.banners.index');
    }

    public function store(StoreBannerRequest $request): RedirectResponse
    {
        $banner = Banner::query()->create($this->attributes(
            $request->validated(),
            $request->file('image_url_en'),
            $request->file('image_url_zh'),
            $request->file('image_url_my'),
        ));

        activity('cms')->causedBy($request->user())->performedOn($banner)->event('created')->log('banner_created');

        return redirect()->route('cms.banners.index')->with('success', 'cms.created');
    }

    public function edit(Banner $banner): RedirectResponse
    {
        return redirect()->route('cms.banners.index');
    }

    public function update(UpdateBannerRequest $request, Banner $banner): RedirectResponse
    {
        $banner->update($this->attributes(
            $request->validated(),
            $request->file('image_url_en'),
            $request->file('image_url_zh'),
            $request->file('image_url_my'),
            $banner->image_url_en,
            $banner->image_url_zh,
            $banner->image_url_my
        ));

        activity('cms')->causedBy($request->user())->performedOn($banner)->event('updated')->log('banner_updated');

        return redirect()->route('cms.banners.index')->with('success', 'cms.updated');
    }

    public function destroy(Request $request, Banner $banner): RedirectResponse
    {
        StoresPublicImage::delete($banner->image_url_en);
        StoresPublicImage::delete($banner->image_url_zh);
        StoresPublicImage::delete($banner->image_url_my);
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
            beforeDelete: fn(Banner $banner) => StoresPublicImage::delete($banner->image_path),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(
        array $validated,
        ?UploadedFile $imageEn = null,
        ?UploadedFile $imageZh = null,
        ?UploadedFile $imageMy = null,
        ?string $previousPathEn = null,
        ?string $previousPathZh = null,
        ?string $previousPathMy = null,
    ): array {
        $data = [
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ];

        if ($imageEn) {
            $data['image_url_en'] = StoresPublicImage::store(
                $imageEn,
                'cms/banners',
                $previousPathEn
            );
        }

        if ($imageZh) {
            $data['image_url_zh'] = StoresPublicImage::store(
                $imageZh,
                'cms/banners',
                $previousPathZh
            );
        }

        if ($imageMy) {
            $data['image_url_my'] = StoresPublicImage::store(
                $imageMy,
                'cms/banners',
                $previousPathMy
            );
        }


        return $data;
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
            'start_date' => $banner->start_date,
            'end_date' => $banner->end_date,
            'created_at' => $banner->created_at?->toDateString(),
        ];
    }
}
