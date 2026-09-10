<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreServiceRequest;
use App\Http\Requests\Cms\UpdateServiceRequest;
use App\Models\Service;
use App\Support\CmsBulkDelete;
use App\Support\CmsListing;
use App\Support\StoresPublicImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $listing = CmsListing::paginate(
            $request,
            Service::query(),
            ['title_en', 'title_zh', 'title_my', 'slug'],
            ['title_en', 'title_zh', 'title_my', 'status', 'created_at'],
            statusColumn: 'status',
        );

        return Inertia::render('Cms/service/Index', [
            'items' => $listing['paginator']->through(fn(Service $item) => $this->payload($item)),
            'filters' => $listing['filters'],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('cms.services.index');
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_url'] = StoresPublicImage::store($request->file('image'), 'cms/services');
        } else {
            $data['image_url'] = null;
        }

        $service = Service::query()->create([
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

        activity('cms')->causedBy($request->user())->performedOn($service)->event('created')->log('service_created');

        return redirect()->route('cms.services.index')->with('success', 'cms.service.created');
    }

    public function edit(Service $service): RedirectResponse
    {
        return redirect()->route('cms.services.index');
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image_url'] = StoresPublicImage::store($request->file('image'), 'cms/services', $service->image_url);
        } elseif (array_key_exists('image_url', $data) && !isset($data['image_url'])) {
            $data['image_url'] = null;
            StoresPublicImage::delete($service->image_url);
        } else {
            $data['image_url'] = $service->image_url;
        }

        $service->update([
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

        activity('cms')->causedBy($request->user())->performedOn($service)->event('updated')->log('service_updated');

        return redirect()->route('cms.services.index')->with('success', 'cms.service.updated');
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        StoresPublicImage::delete($service->image_url);
        $service->delete();

        activity('cms')->causedBy($request->user())->performedOn($service)->event('deleted')->log('service_deleted');

        return redirect()->route('cms.services.index')->with('success', 'cms.service.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return CmsBulkDelete::run(
            $request,
            Service::query(),
            'cms.services.index',
            'service_deleted',
            beforeDelete: fn(Service $service) => StoresPublicImage::delete($service->image_url),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Service $service): array
    {
        return [
            'id' => $service->id,
            'title_en' => $service->title_en,
            'title_zh' => $service->title_zh,
            'title_my' => $service->title_my,
            'description_en' => $service->description_en,
            'description_zh' => $service->description_zh,
            'description_my' => $service->description_my,
            'slug' => $service->slug,
            'status' => $service->status->value,
            'image_url' => StoresPublicImage::url($service->image_url),
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at,
        ];
    }
}
