<?php

namespace App\Http\Resources\Region;

use App\Http\Resources\Region\AreaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => [
                'en' => $this->name_en,
                'my' => $this->name_my,
                'zh' => $this->name_zh,
            ],
            'areas' => AreaResource::collection(
                $this->whenLoaded('areas')
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
