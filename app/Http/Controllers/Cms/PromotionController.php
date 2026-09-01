<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StorePromotionRequest;
use App\Http\Requests\Cms\UpdatePromotionRequest;
use App\Models\Promotion;
use App\Support\CmsBulkDelete;
use App\Support\CmsListing;
use App\Support\StoresPublicImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function index(Request $request): Response
    {
        $listing = CmsListing::paginate(
            $request,
            Promotion::query(),
            ['title_en', 'title_my', 'title_zh'],
            ['title_en', 'title_my', 'title_zh', 'start_date', 'end_date', 'created_at'],
            statusColumn: 'is_active',
        );

        return Inertia::render('Cms/promotion/Index', [
            'items' => $listing['paginator']->through(fn(Promotion $item) => $this->payload($item)),
            'filters' => $listing['filters'],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('cms.promotions.index');
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_url'] = StoresPublicImage::store($request->file('image'), 'cms/promotions');
        } else {
            $data['image_url'] = null;
        }

        $promotion = Promotion::query()->create($data);

        activity('cms')->causedBy($request->user())->performedOn($promotion)->event('created')->log('promotion_created');

        return redirect()->route('cms.promotions.index')->with('success', 'cms.promotions.created');
    }

    public function edit(Promotion $promotion): RedirectResponse
    {
        return redirect()->route('cms.promotions.index');
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_url'] = StoresPublicImage::store($request->file('image'), 'cms/promotions', $promotion->image_url);
        }

        $promotion->update($data);

        activity('cms')->causedBy($request->user())->performedOn($promotion)->event('updated')->log('promotion_updated');

        return redirect()->route('cms.promotions.index')->with('success', 'cms.promotions.updated');
    }

    public function destroy(Request $request, Promotion $promotion): RedirectResponse
    {
        StoresPublicImage::delete($promotion->image_url);
        $promotion->delete();

        activity('cms')->causedBy($request->user())->performedOn($promotion)->event('deleted')->log('promotion_deleted');

        return redirect()->route('cms.promotions.index')->with('success', 'cms.promotions.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return CmsBulkDelete::run(
            $request,
            Promotion::query(),
            'cms.promotions.index',
            'promotion_deleted',
            beforeDelete: fn(Promotion $promotion) => StoresPublicImage::delete($promotion->image_url),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Promotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'title_en' => $promotion->title_en,
            'title_my' => $promotion->title_my,
            'title_zh' => $promotion->title_zh,
            'description_en' => $promotion->description_en,
            'description_my' => $promotion->description_my,
            'description_zh' => $promotion->description_zh,
            'start_date' => $promotion->start_date?->toDateString(),
            'end_date' => $promotion->end_date?->toDateString(),
            'is_active' => $promotion->is_active,
            'slug' => $promotion->slug,
            'image_url' => StoresPublicImage::url($promotion->image_url),
            'created_at' => $promotion->created_at?->toDateString(),
        ];
    }
}
