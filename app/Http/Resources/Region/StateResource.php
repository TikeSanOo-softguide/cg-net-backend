<?php

namespace App\Http\Resources\Region;

use App\Http\Resources\Region\RegionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => [
                'en' => $this->name_en,
                'my' => $this->name_my,
                'zh' => $this->name_zh,
            ],
            'regions' => RegionResource::collection(
                $this->whenLoaded('regions')
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
