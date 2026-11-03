<?php

namespace App\Http\Controllers\Package;

use App\Enums\ActivityLogEvent;

use App\Enums\ActivityLogName;
use App\Http\Controllers\Controller;
use App\Models\Network;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NetworkController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:50', Rule::unique('networks', 'name_en')->withoutTrashed()],
            'name_zh' => ['required', 'string', 'max:50', Rule::unique('networks', 'name_zh')->withoutTrashed()],
            'name_my' => ['required', 'string', 'max:50', Rule::unique('networks', 'name_my')->withoutTrashed()],
        ]);

        $network = Network::query()->create($data);

        activity(ActivityLogName::Network->value)
            ->causedBy($request->user())
            ->performedOn($network)
            ->event(ActivityLogEvent::Created->value)
            ->log('network_created');

        return redirect()->route('packages.index')->with('success', 'packages.networks.created');
    }

    public function update(Request $request, Network $network): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => [
                'required',
                'string',
                'max:50',
                Rule::unique('networks', 'name_en')->ignore($network->id)->withoutTrashed(),
            ],
            'name_zh' => [
                'required',
                'string',
                'max:50',
                Rule::unique('networks', 'name_zh')->ignore($network->id)->withoutTrashed(),
            ],
            'name_my' => [
                'required',
                'string',
                'max:50',
                Rule::unique('networks', 'name_my')->ignore($network->id)->withoutTrashed(),
            ],
        ]);

        $network->update($data);

        activity(ActivityLogName::Network->value)
            ->causedBy($request->user())
            ->performedOn($network)
            ->event(ActivityLogEvent::Updated->value)
            ->log('network_updated');

        return redirect()->route('packages.index')->with('success', 'packages.networks.updated');
    }

    public function destroy(Request $request, Network $network): RedirectResponse
    {
        if ($network->packages()->exists()) {
            return back()->withErrors([
                'delete' => __('packages.networks.cannot_delete_has_package'),
            ]);
        }
        $network->delete();

        activity(ActivityLogName::Network->value)
            ->causedBy($request->user())
            ->performedOn($network)
            ->event(ActivityLogEvent::Deleted->value)
            ->log('network_deleted');

        return redirect()->route('packages.index')->with('success', 'packages.networks.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:networks,id'],
        ])['ids'];

        $deletableIds = Network::query()->whereIn('id', $ids)->whereDoesntHave('packages')->pluck('id');
        $deletedCount = Network::query()->whereIn('id', $deletableIds)->delete();

        if ($deletedCount === 0) {
            return back()->with('error', __('common.bulk_delete_failed'));
        }

        activity(ActivityLogName::Network->value)
            ->causedBy($request->user())
            ->event(ActivityLogEvent::BulkDeleted->value)
            ->withProperties(['ids' => $deletableIds])
            ->log('networks_bulk_deleted');

        return redirect()->route('packages.index')->with('success', 'packages.networks.bulk_deleted');
    }
}
