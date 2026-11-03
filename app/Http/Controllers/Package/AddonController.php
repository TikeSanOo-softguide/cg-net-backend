<?php

namespace App\Http\Controllers\Package;

use App\Enums\ActivityLogEvent;

use App\Enums\ActivityLogName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\StoreAddonsRequest;
use App\Http\Requests\Package\UpdateAddonsRequest;
use App\Models\Addon;
use App\Support\StoresPublicImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class AddonController extends Controller
{
    public function store(StoreAddonsRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image_url');

        if ($request->hasFile('image_url')) {
            $data['image_url'] = StoresPublicImage::store($request->file('image_url'), 'cms/addons');
        } else {
            $data['image_url'] = null;
        }

        $addon = Addon::query()->create([
            'name_en' => $data['name_en'],
            'name_zh' => $data['name_zh'],
            'name_my' => $data['name_my'],
            'price' => $data['price'],
            'image_url' => $data['image_url'],
        ]);
        activity(ActivityLogName::Addon->value)->causedBy($request->user())->performedOn($addon)->event(ActivityLogEvent::Created->value)->log('addon_created');

        return redirect()->route('packages.index')->with('success', 'packages.addons.created');
    }

    public function update(UpdateAddonsRequest $request, Addon $addon): RedirectResponse
    {
        $data = $request->safe()->except('image_url');

        if ($request->hasFile('image_url')) {
            $data['image_url'] = StoresPublicImage::store($request->file('image_url'), 'cms/addons', $addon->image_url);
        } elseif ($request->exists('image_url') && blank($request->input('image_url'))) {
            $data['image_url'] = null;
            StoresPublicImage::delete($addon->image_url);
        } else {
            $data['image_url'] = $addon->image_url;
        }

        $addon->update([
            'name_en' => $data['name_en'],
            'name_zh' => $data['name_zh'],
            'name_my' => $data['name_my'],
            'price' => $data['price'],
            'image_url' => $data['image_url'],
        ]);

        activity(ActivityLogName::Addon->value)->causedBy($request->user())->performedOn($addon)->event(ActivityLogEvent::Updated->value)->log('addon_updated');

        return redirect()->route('packages.index')->with('success', 'packages.addons.updated');
    }

    public function destroy(Request $request, Addon $addon): RedirectResponse
    {
        $addon->delete();

        activity(ActivityLogName::Addon->value)
            ->causedBy($request->user())
            ->performedOn($addon)
            ->event(ActivityLogEvent::Deleted->value)
            ->log('addon_deleted');

        return redirect()->route('packages.index')->with('success', 'packages.addons.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:addons,id'],
        ])['ids'];
        $deleted = Addon::query()->whereIn('id', $ids)->delete();

        if ($deleted === 0) {
            return back()->withErrors(['delete' => 'common.bulk_delete_failed']);
        }

        activity(ActivityLogName::Addon->value)
            ->causedBy($request->user())
            ->event(ActivityLogEvent::BulkDeleted->value)
            ->withProperties(['ids' => $ids])
            ->log('addons_bulk_deleted');

        return redirect()->route('packages.index')->with('success', 'packages.addons.bulk_deleted');
    }
}
