<?php

namespace App\Http\Controllers\Api\Region;

use App\Http\Controllers\Controller;
use App\Http\Resources\Region\AreaResource;
use App\Http\Resources\Region\RegionResource;
use App\Http\Resources\Region\StateResource;
use App\Models\Area;
use App\Models\Region;
use App\Models\State;

class RegionController extends Controller
{

    public function locations()
    {
        $states = State::query()
            ->with('regions.areas')
            ->get();

        return StateResource::collection($states);
    }
    /**
     * Get all states.
     */
    public function states()
    {
        $states = State::query()
            ->get();

        return StateResource::collection($states);
    }

    /**
     * Get regions associated with a state.
     */
    public function regions(int $stateId)
    {
        $regions = Region::query()
            ->where('state_id', $stateId)
            ->get();

        return RegionResource::collection($regions);
    }

    /**
     * Get areas associated with a region.
     */
    public function areas(int $regionId)
    {
        $areas = Area::query()
            ->where('region_id', $regionId)
            ->get();

        return AreaResource::collection($areas);
    }
}
