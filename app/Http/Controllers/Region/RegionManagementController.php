<?php

namespace App\Http\Controllers\Region;

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
use App\Support\CmsListing;
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
        $states = CmsListing::paginate(
            $request,
            State::query(),
            ['name_en', 'name_zh', 'name_my'],
            ['name_en', 'name_zh', 'name_my', 'created_at'],
        );
        $regions = CmsListing::paginate(
            $request,
            Region::query()->with(
                'state:id,name_en,name_zh,name_my'
            ),
            ['name_en', 'name_zh', 'name_my', 'state_id'],
            ['name_en', 'name_zh', 'name_my', 'state_id', 'created_at'],
        );
        $areas = CmsListing::paginate(
            $request,
            Area::query()->with([
                'region:id,name_en,name_zh,name_my,state_id',
                'region.state:id,name_en,name_zh,name_my',
            ]),
            ['name_en', 'name_zh', 'name_my', 'region_id'],
            ['name_en', 'name_zh', 'name_my', 'region_id', 'created_at'],
        );

        return Inertia::render('Region/Index', [
            'states' => $states['paginator']->through(
                fn(State $state) => [
                    'id' => $state->id,
                    'name_en' => $state->name_en,
                    'name_zh' => $state->name_zh,
                    'name_my' => $state->name_my,
                    'created_at' => $state->created_at?->toDateString(),
                ]
            ),
            'stateFilters' => $states['filters'],

            'regions' => $regions['paginator']->through(
                fn(Region $region) => [
                    'id' => $region->id,
                    'name_en' => $region->name_en,
                    'name_zh' => $region->name_zh,
                    'name_my' => $region->name_my,

                    'state_id' => $region->state_id,

                    'state_name_en' => $region->state?->name_en,
                    'state_name_zh' => $region->state?->name_zh,
                    'state_name_my' => $region->state?->name_my,

                    'created_at' => $region->created_at?->toDateString(),
                ]
            ),
            'regionFilters' => $regions['filters'],

            'areas' => $areas['paginator']->through(
                fn(Area $area) => [
                    'id' => $area->id,

                    'name_en' => $area->name_en,
                    'name_zh' => $area->name_zh,
                    'name_my' => $area->name_my,

                    'region_id' => $area->region_id,
                    'state_id' => $area->region?->state_id,

                    'region_name_en' => $area->region?->name_en,
                    'region_name_zh' => $area->region?->name_zh,
                    'region_name_my' => $area->region?->name_my,

                    'state_name_en' => $area->region?->state?->name_en,
                    'state_name_zh' => $area->region?->state?->name_zh,
                    'state_name_my' => $area->region?->state?->name_my,

                    'created_at' => $area->created_at?->toDateString(),
                ]
            ),
            'areaFilters' => $areas['filters'],
        ]);
    }

    /**
     * Store a new state.
     */
    public function storeState(
        StoreStateRequest $request
    ): RedirectResponse {
        State::create(
            $request->validated()
        );

        return redirect()->route('regions.index')->with('success', 'regions.state_created');
    }

    /**
     * Update an existing state.
     */
    public function updateState(
        UpdateStateRequest $request,
        State $state
    ): RedirectResponse {
        $state->update(
            $request->validated()
        );

        return redirect()->route('regions.index')->with('success', 'regions.state_updated');
    }

    /**
     * Delete a state.
     */
    public function destroyState(
        State $state
    ): RedirectResponse {
        /*
         * Prevent deleting a State if it still has Regions.
         */
        if ($state->regions()->exists()) {
            return back()->withErrors([
                'delete' => 'This state cannot be deleted because it has regions.',
            ]);
        }

        $state->delete();

        return redirect()->route('regions.index')->with('success', 'regions.state_deleted');
    }

    /**
     * Bulk delete states.
     */
    public function bulkDestroyStates(
        Request $request
    ): RedirectResponse {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => [
                'integer',
                'exists:states,id',
            ],
        ])['ids'];

        $deletableIds = State::query()
            ->whereIn('id', $ids)
            ->whereDoesntHave('regions')
            ->pluck('id');

        State::whereIn('id', $deletableIds)->delete();

        return redirect()->route('regions.index')->with('success', 'regions.state_deleted');
    }


    /**
     * Store a new region.
     */
    public function storeRegion(
        StoreRegionRequest $request
    ): RedirectResponse {
        Region::create(
            $request->validated()
        );

        return redirect()->route('regions.index')->with('success', 'regions.region_created');
    }

    /**
     * Update an existing region.
     */
    public function updateRegion(
        UpdateRegionRequest $request,
        Region $region
    ): RedirectResponse {
        $region->update(
            $request->validated()
        );

        return redirect()->route('regions.index')->with('success', 'regions.region_updated');
    }

    /**
     * Delete a region.
     */
    public function destroyRegion(
        Region $region
    ): RedirectResponse {
        /*
         * Prevent deleting a Region if it still has Areas.
         */
        if ($region->areas()->exists()) {
            return back()->withErrors([
                'delete' => 'This region cannot be deleted because it has areas.',
            ]);
        }

        $region->delete();

        return redirect()->route('regions.index')->with('success', 'regions.region_deleted');
    }

    /**
     * Bulk delete regions.
     */
    public function bulkDestroyRegions(
        Request $request
    ): RedirectResponse {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => [
                'integer',
                'exists:regions,id',
            ],
        ])['ids'];

        /*
         * Don't delete regions that have areas.
         */
        $deletableIds = Region::query()
            ->whereIn('id', $ids)
            ->whereDoesntHave('areas')
            ->pluck('id');

        Region::whereIn('id', $deletableIds)->delete();

        return redirect()->route('regions.index')->with('success', 'regions.region_deleted');
    }


    /**
     * Store a new area.
     */
    public function storeArea(
        StoreAreaRequest $request
    ): RedirectResponse {
        Area::create(
            $request->validated()
        );

        return redirect()->route('regions.index')->with('success', 'regions.area_created');
    }

    /**
     * Update an existing area.
     */
    public function updateArea(
        UpdateAreaRequest $request,
        Area $area
    ): RedirectResponse {
        $area->update(
            $request->validated()
        );

        return redirect()->route('regions.index')->with(
            'success',
            'regions.area_updated'
        );
    }

    /**
     * Delete an area.
     */
    public function destroyArea(
        Area $area
    ): RedirectResponse {
        $area->delete();

        return redirect()->route('regions.index')->with(
            'success',
            'regions.area_deleted'
        );
    }

    /**
     * Bulk delete areas.
     */
    public function bulkDestroyAreas(
        Request $request
    ): RedirectResponse {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => [
                'integer',
                'exists:areas,id',
            ],
        ])['ids'];

        Area::whereIn('id', $ids)->delete();

        return redirect()->route('regions.index')->with(
            'success',
            'regions.area_deleted'
        );
    }
}
