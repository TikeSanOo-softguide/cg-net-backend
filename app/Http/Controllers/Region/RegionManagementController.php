<?php

namespace App\Http\Controllers\Region;

use App\Enums\ActivityLogEvent;

use App\Enums\ActivityLogName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Region\StoreAreaRequest;
use App\Http\Requests\Region\StoreRegionRequest;
use App\Http\Requests\Region\StoreStateRequest;
use App\Http\Requests\Region\UpdateAreaRequest;
use App\Http\Requests\Region\UpdateRegionRequest;
use App\Http\Requests\Region\UpdateStateRequest;
use App\Models\Area;
use App\Models\Region;
use App\Models\State;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegionManagementController extends Controller
{
    /**
     * Display the region management page.
     */
    public function index(Request $request): Response
    {
        $stateSearch = trim($request->string('state_search')->toString());
        $states = State::query()
            ->when($stateSearch !== '', function ($query) use ($stateSearch) {
                $query->where(function ($query) use ($stateSearch) {
                    $query
                        ->whereLike('name_en', '%' . $stateSearch . '%')
                        ->orWhereLike('name_zh', '%' . $stateSearch . '%')
                        ->orWhereLike('name_my', '%' . $stateSearch . '%');
                });
            })
            ->paginate(15, ['*'], 'state_page')
            ->withQueryString();

        $regionSearch = trim($request->string('region_search')->toString());

        $regions = Region::query()
            ->with('state:id,name_en,name_zh,name_my')
            ->when($regionSearch !== '', function ($query) use ($regionSearch) {
                $query->where(function ($query) use ($regionSearch) {
                    $query
                        // Region name
                        ->whereLike('name_en', '%' . $regionSearch . '%')
                        ->orWhereLike('name_zh', '%' . $regionSearch . '%')
                        ->orWhereLike('name_my', '%' . $regionSearch . '%')

                        // State name
                        ->orWhereHas('state', function ($query) use ($regionSearch) {
                            $query
                                ->whereLike('name_en', '%' . $regionSearch . '%')
                                ->orWhereLike('name_zh', '%' . $regionSearch . '%')
                                ->orWhereLike('name_my', '%' . $regionSearch . '%');
                        });
                });
            })
            ->paginate(15, ['*'], 'region_page')
            ->withQueryString();

        $areaSearch = trim($request->string('area_search')->toString());
        $areas = Area::query()
            ->with(['region:id,name_en,name_zh,name_my,state_id', 'region.state:id,name_en,name_zh,name_my'])
            ->when($areaSearch !== '', function ($query) use ($areaSearch) {
                $query->where(function ($query) use ($areaSearch) {
                    $query
                        ->whereLike('name_en', '%' . $areaSearch . '%')
                        ->orWhereLike('name_zh', '%' . $areaSearch . '%')
                        ->orWhereLike('name_my', '%' . $areaSearch . '%')

                        ->orWhereHas('region', function ($query) use ($areaSearch) {
                            $query
                                ->whereLike('name_en', '%' . $areaSearch . '%')
                                ->orWhereLike('name_zh', '%' . $areaSearch . '%')
                                ->orWhereLike('name_my', '%' . $areaSearch . '%')

                                ->orWhereHas('state', function ($query) use ($areaSearch) {
                                    $query
                                        ->whereLike('name_en', '%' . $areaSearch . '%')
                                        ->orWhereLike('name_zh', '%' . $areaSearch . '%')
                                        ->orWhereLike('name_my', '%' . $areaSearch . '%');
                                });
                        });
                });
            })
            ->paginate(15, ['*'], 'area_page')
            ->withQueryString();

        return Inertia::render('Region/Index', [
            'states' => $states->through(
                fn(State $state) => [
                    'id' => $state->id,
                    'name_en' => $state->name_en,
                    'name_zh' => $state->name_zh,
                    'name_my' => $state->name_my,
                    'latitude' => $state->latitude,
                    'longitude' => $state->longitude,
                    'created_at' => $state->created_at?->toDateString(),
                ],
            ),
            'stateFilters' => [
                'search' => $stateSearch,
            ],

            'regions' => $regions->through(
                fn(Region $region) => [
                    'id' => $region->id,
                    'name_en' => $region->name_en,
                    'name_zh' => $region->name_zh,
                    'name_my' => $region->name_my,
                    'state_id' => $region->state_id,
                    'latitude' => $region->latitude,
                    'longitude' => $region->longitude,
                    'state_name_en' => $region->state?->name_en,
                    'state_name_zh' => $region->state?->name_zh,
                    'state_name_my' => $region->state?->name_my,
                    'created_at' => $region->created_at?->toDateString(),
                ],
            ),
            'regionFilters' => [
                'search' => $regionSearch,
            ],

            'areas' => $areas->through(
                fn(Area $area) => [
                    'id' => $area->id,
                    'name_en' => $area->name_en,
                    'name_zh' => $area->name_zh,
                    'name_my' => $area->name_my,
                    'region_id' => $area->region_id,
                    'latitude' => $area->latitude,
                    'longitude' => $area->longitude,
                    'state_id' => $area->region?->state_id,
                    'region_name_en' => $area->region?->name_en,
                    'region_name_zh' => $area->region?->name_zh,
                    'region_name_my' => $area->region?->name_my,
                    'state_name_en' => $area->region?->state?->name_en,
                    'state_name_zh' => $area->region?->state?->name_zh,
                    'state_name_my' => $area->region?->state?->name_my,
                    'created_at' => $area->created_at?->toDateString(),
                ],
            ),
            'areaFilters' => [
                'search' => $areaSearch,
            ],
        ]);
    }

    /**
     * Store a new state.
     */
    public function storeState(StoreStateRequest $request): RedirectResponse
    {
        $state = State::create($request->validated());

        activity(ActivityLogName::State->value)->causedBy($request->user())->performedOn($state)->event(ActivityLogEvent::Created->value)->log('state_created');

        return redirect()->route('regions.index')->with('success', 'regions.state_created');
    }

    /**
     * Update an existing state.
     */
    public function updateState(UpdateStateRequest $request, State $state): RedirectResponse
    {
        $state->update($request->validated());

        activity(ActivityLogName::State->value)->causedBy($request->user())->performedOn($state)->event(ActivityLogEvent::Updated->value)->log('state_updated');

        return redirect()->route('regions.index')->with('success', 'regions.state_updated');
    }

    /**
     * Delete a state.
     */
    public function destroyState(Request $request, State $state): RedirectResponse
    {
        if ($state->regions()->exists()) {
            return back()->withErrors(['delete' => 'regions.state_delete_failed']);
        }

        $state->delete();

        activity(ActivityLogName::State->value)->causedBy($request->user())->performedOn($state)->event(ActivityLogEvent::Deleted->value)->log('state_deleted');

        return redirect()->route('regions.index')->with('success', 'regions.state_deleted');
    }

    public function bulkDestroyStates(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:states,id'],
        ])['ids'];

        $deletableIds = State::query()->whereIn('id', $ids)->whereDoesntHave('regions')->pluck('id');

        $deletedCount = State::query()->whereIn('id', $deletableIds)->delete();

        if ($deletedCount === 0) {
            return back()->with('error', __('common.bulk_delete_failed'));
        }

        activity(ActivityLogName::State->value)
            ->causedBy($request->user())
            ->event(ActivityLogEvent::BulkDeleted->value)
            ->withProperties(['ids' => $deletableIds])
            ->log('states_bulk_deleted');

        return redirect()
            ->route('regions.index')
            ->with('success', 'common.bulk_deleted')
            ->with('deleted_count', $deletedCount);
    }

    /**
     * Store a new region.
     */
    public function storeRegion(StoreRegionRequest $request): RedirectResponse
    {
        $region = Region::create($request->validated());

        activity(ActivityLogName::Region->value)->causedBy($request->user())->performedOn($region)->event(ActivityLogEvent::Created->value)->log('region_created');

        return redirect()->route('regions.index')->with('success', 'regions.region_created');
    }

    /**
     * Update an existing region.
     */
    public function updateRegion(UpdateRegionRequest $request, Region $region): RedirectResponse
    {
        $region->update($request->validated());

        activity(ActivityLogName::Region->value)->causedBy($request->user())->performedOn($region)->event(ActivityLogEvent::Updated->value)->log('region_updated');

        return redirect()->route('regions.index')->with('success', 'regions.region_updated');
    }

    /**
     * Delete a region.
     */
    public function destroyRegion(Request $request, Region $region): RedirectResponse
    {
        if ($region->areas()->exists()) {
            return back()->withErrors(['delete' => 'regions.region_delete_failed']);
        }

        $region->delete();

        activity(ActivityLogName::Region->value)->causedBy($request->user())->performedOn($region)->event(ActivityLogEvent::Deleted->value)->log('region_deleted');

        return redirect()->route('regions.index')->with('success', 'regions.region_deleted');
    }

    /**
     * Bulk delete regions.
     */
    public function bulkDestroyRegions(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:regions,id'],
        ])['ids'];

        $deletableIds = Region::query()->whereIn('id', $ids)->whereDoesntHave('areas')->pluck('id');

        $deletedCount = Region::query()->whereIn('id', $deletableIds)->delete();

        if ($deletedCount === 0) {
            return back()->with('error', __('common.bulk_delete_failed'));
        }

        activity(ActivityLogName::Region->value)
            ->causedBy($request->user())
            ->event(ActivityLogEvent::BulkDeleted->value)
            ->withProperties(['ids' => $deletableIds])
            ->log('regions_bulk_deleted');

        return redirect()
            ->route('regions.index')
            ->with('success', 'common.bulk_deleted')
            ->with('deleted_count', $deletedCount);
    }

    /**
     * Store a new area.
     */
    public function storeArea(StoreAreaRequest $request): RedirectResponse
    {
        $area = Area::create($request->validated());

        activity(ActivityLogName::Area->value)->causedBy($request->user())->performedOn($area)->event(ActivityLogEvent::Created->value)->log('area_created');

        return redirect()->route('regions.index')->with('success', 'regions.area_created');
    }

    /**
     * Update an existing area.
     */
    public function updateArea(UpdateAreaRequest $request, Area $area): RedirectResponse
    {
        $area->update($request->validated());

        activity(ActivityLogName::Area->value)->causedBy($request->user())->performedOn($area)->event(ActivityLogEvent::Updated->value)->log('area_updated');

        return redirect()->route('regions.index')->with('success', 'regions.area_updated');
    }

    /**
     * Delete an area.
     */
    public function destroyArea(Request $request, Area $area): RedirectResponse
    {
        $area->delete();

        activity(ActivityLogName::Area->value)->causedBy($request->user())->performedOn($area)->event(ActivityLogEvent::Deleted->value)->log('area_deleted');

        return redirect()->route('regions.index')->with('success', 'regions.area_deleted');
    }

    /**
     * Bulk delete areas.
     */
    public function bulkDestroyAreas(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:areas,id'],
        ])['ids'];

        $deleted = Area::query()->whereIn('id', $ids)->delete();

        if ($deleted === 0) {
            return back()->with('error', __('common.bulk_delete_failed'));
        }

        activity(ActivityLogName::Area->value)
            ->causedBy($request->user())
            ->event(ActivityLogEvent::BulkDeleted->value)
            ->withProperties(['ids' => $ids])
            ->log('areas_bulk_deleted');

        return redirect()
            ->route('regions.index')
            ->with('success', 'common.bulk_deleted')
            ->with('deleted_count', $deleted);
    }
}
