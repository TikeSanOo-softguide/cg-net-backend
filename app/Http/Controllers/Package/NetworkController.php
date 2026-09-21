<?php

namespace App\Http\Controllers\Package;

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

        Network::query()->create($data);

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

        return redirect()->route('packages.index')->with('success', 'packages.networks.updated');
    }

    public function destroy(Network $network): RedirectResponse
    {
        if ($network->packages()->exists()) {
            return back()->withErrors([
                'delete' => __('packages.networks.cannot_delete_has_package'),
            ]);
        }
        $network->delete();

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
            return back()->withErrors(['delete' => __('common.bulk_delete_failed')]);
        }

        $deleted = Network::query()->whereIn('id', $ids)->delete();
        return $deleted === 0
            ? back()->withErrors(['delete' => 'common.bulk_delete_failed'])
            : redirect()->route('packages.index')->with('success', 'packages.networks.bulk_deleted');
    }
}
