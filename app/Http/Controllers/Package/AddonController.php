<?php

namespace App\Http\Controllers\Package;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Support\StoresPublicImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class AddonController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_zh' => ['required', 'string', 'max:255'],
            'name_my' => ['required', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'image', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $addon = Addon::query()->create(
            $this->attributes(
                $data,
                $request->file('image_url'),
            )
        );

        activity('addon')
            ->causedBy($request->user())
            ->performedOn($addon)
            ->event('created')
            ->log('addon_created');

        return redirect()
            ->route('packages.index')
            ->with('success', 'packages.reference_created');
    }

    public function update(Request $request, Addon $addon): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => ['sometimes', 'string', 'max:255'],
            'name_zh' => ['sometimes', 'string', 'max:255'],
            'name_my' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'image', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('image_url')) {
            $data['image_url'] = StoresPublicImage::store(
                $request->file('image_url'),
                'cms/addons',
                $addon->image_url
            );
        } elseif (
            array_key_exists('image_url', $data) &&
            !isset($data['image_url'])
        ) {
            $data['image_url'] = null;

            StoresPublicImage::delete($addon->image_url);
        } else {
            $data['image_url'] = $addon->image_url;
        }

        $addon->update($data);

        activity('addon')
            ->causedBy($request->user())
            ->performedOn($addon)
            ->event('updated')
            ->log('addon_updated');

        return redirect()
            ->route('packages.index')
            ->with('success', 'packages.addons.updated');
    }

    public function destroy(
        Request $request,
        Addon $addon
    ): RedirectResponse {
        $addon->delete();

        return redirect()
            ->route('packages.index')
            ->with('success', 'packages.reference_deleted');
    }

    private function attributes(
        array $validated,
        ?UploadedFile $image_url = null,
        ?string $previousPath = null,
    ): array {
        $data = [];

        if (array_key_exists('name_en', $validated)) {
            $data['name_en'] = $validated['name_en'];
        }

        if (array_key_exists('name_zh', $validated)) {
            $data['name_zh'] = $validated['name_zh'];
        }

        if (array_key_exists('name_my', $validated)) {
            $data['name_my'] = $validated['name_my'];
        }

        if (array_key_exists('price', $validated)) {
            $data['price'] = $validated['price'];
        }

        if (array_key_exists('is_active', $validated)) {
            $data['is_active'] = $validated['is_active'];
        }

        if ($image_url) {
            $data['image_url'] = StoresPublicImage::store(
                $image_url,
                'cms/addons',
                $previousPath,
            );
        } elseif (
            $previousPath !== null &&
            array_key_exists('image_url', $validated) &&
            $validated['image_url'] === null
        ) {
            $data['image_url'] = null;

            StoresPublicImage::delete($previousPath);
        } elseif ($previousPath !== null) {
            $data['image_url'] = $previousPath;
        }

        return $data;
    }

    private function payload(Addon $addon): array
    {
        return [
            'id' => $addon->id,
            'name_en' => $addon->name_en,
            'name_zh' => $addon->name_zh,
            'name_my' => $addon->name_my,
            'price' => $addon->price,
            'image_url' => StoresPublicImage::url(
                $addon->image_url
            ),
            'is_active' => $addon->is_active,
            'created_at' => $addon->created_at?->toDateString(),
        ];
    }
}
