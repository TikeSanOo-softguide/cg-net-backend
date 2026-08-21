<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StorePromotionRequest;
use App\Http\Requests\Cms\UpdatePromotionRequest;
use App\Models\Promotion;
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
            ['title', 'description'],
            ['title', 'start_date', 'end_date', 'is_active', 'lang', 'created_at'],
            statusColumn: 'is_active',
        );

        return Inertia::render('Cms/Promotions/Index', [
            'items' => $listing['paginator']->through(fn (Promotion $item) => $this->payload($item)),
            'filters' => $listing['filters'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Cms/Promotions/Create');
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');
        $data['image_path'] = StoresPublicImage::store($request->file('image'), 'cms/promotions');

        $promotion = Promotion::query()->create($data);

        activity('cms')->causedBy($request->user())->performedOn($promotion)->event('created')->log('promotion_created');

        return redirect()->route('cms.promotions.index')->with('success', 'cms.created');
    }

    public function edit(Promotion $promotion): Response
    {
        return Inertia::render('Cms/Promotions/Edit', [
            'item' => $this->payload($promotion),
        ]);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_path'] = StoresPublicImage::store($request->file('image'), 'cms/promotions', $promotion->image_path);
        }

        $promotion->update($data);

        activity('cms')->causedBy($request->user())->performedOn($promotion)->event('updated')->log('promotion_updated');

        return redirect()->route('cms.promotions.index')->with('success', 'cms.updated');
    }

    public function destroy(Request $request, Promotion $promotion): RedirectResponse
    {
        StoresPublicImage::delete($promotion->image_path);
        $promotion->delete();

        activity('cms')->causedBy($request->user())->performedOn($promotion)->event('deleted')->log('promotion_deleted');

        return redirect()->route('cms.promotions.index')->with('success', 'cms.deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Promotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'title' => $promotion->title,
            'description' => $promotion->description,
            'start_date' => $promotion->start_date?->toDateString(),
            'end_date' => $promotion->end_date?->toDateString(),
            'is_active' => $promotion->is_active,
            'lang' => $promotion->lang->value,
            'image_url' => StoresPublicImage::url($promotion->image_path),
            'created_at' => $promotion->created_at?->toDateString(),
        ];
    }
}
