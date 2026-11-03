<?php

namespace App\Http\Controllers\Package;

use App\Enums\ActivityLogEvent;

use App\Enums\ActivityLogName;
use App\Http\Controllers\Controller;
use App\Models\Speed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SpeedController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mbps' => [
                'required',
                'integer',
                'min:1',
                'max:999999999',
                Rule::unique('speeds', 'mbps')->withoutTrashed(),
            ],
        ]);

        $speed = Speed::query()->create($data);

        activity(ActivityLogName::Speed->value)->causedBy($request->user())->performedOn($speed)->event(ActivityLogEvent::Created->value)->log('speed_created');

        return redirect()->route('packages.index')->with('success', 'packages.speeds.created');
    }

    public function update(Request $request, Speed $speed): RedirectResponse
    {
        $data = $request->validate([
            'mbps' => [
                'required',
                'integer',
                'min:1',
                'max:999999999',
                Rule::unique('speeds', 'mbps')->ignore($speed->id)->withoutTrashed(),
            ],
        ]);

        $speed->update($data);

        activity(ActivityLogName::Speed->value)->causedBy($request->user())->performedOn($speed)->event(ActivityLogEvent::Updated->value)->log('speed_updated');

        return redirect()->route('packages.index')->with('success', 'packages.speeds.updated');
    }

    public function destroy(Request $request, Speed $speed): RedirectResponse
    {
        if ($speed->packages()->exists()) {
            return back()->withErrors([
                'delete' => __('packages.speeds.cannot_delete_has_package'),
            ]);
        }
        $speed->delete();

        activity(ActivityLogName::Speed->value)->causedBy($request->user())->performedOn($speed)->event(ActivityLogEvent::Deleted->value)->log('speed_deleted');

        return redirect()->route('packages.index')->with('success', 'packages.speeds.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:speeds,id'],
        ])['ids'];

        $deletableIds = Speed::query()->whereIn('id', $ids)->whereDoesntHave('packages')->pluck('id');
        $deletedCount = Speed::query()->whereIn('id', $deletableIds)->delete();

        if ($deletedCount === 0) {
            return back()->with('error', __('common.bulk_delete_failed'));
        }

        activity(ActivityLogName::Speed->value)
            ->causedBy($request->user())
            ->event(ActivityLogEvent::BulkDeleted->value)
            ->withProperties(['ids' => $deletableIds])
            ->log('speeds_bulk_deleted');

        return redirect()->route('packages.index')->with('success', 'packages.speeds.bulk_deleted');
    }
}
