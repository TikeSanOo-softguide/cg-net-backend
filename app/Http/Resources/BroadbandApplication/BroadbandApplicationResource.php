<?php

namespace App\Http\Resources\BroadbandApplication;

use App\Http\Resources\Package\PackageResource;
use App\Http\Resources\Region\AreaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BroadbandApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'package_id' => $this->package_id,
            'area_id' => $this->area_id,
            'package' => PackageResource::make($this->whenLoaded('package')),
            'area' => AreaResource::make($this->whenLoaded('area')),
            'id_type' => $this->id_type,
            'id_name' => $this->id_name,
            'id_number' => $this->id_number,
            'address' => $this->address,
            'phone' => $this->phone,
            'note' => $this->note,
            'status' => $this->status?->value,
            'admin_id' => $this->admin_id,
            'photos' => BroadbandApplicationPhotoResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
