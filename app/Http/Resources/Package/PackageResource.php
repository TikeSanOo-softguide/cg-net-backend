<?php

namespace App\Http\Resources\Package;

use App\Http\Resources\Package\NetworkResource;
use App\Http\Resources\Package\SpeedResource;
use App\Http\Resources\Package\TermResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'network' => NetworkResource::make($this->whenLoaded('network')),
            'speed' => SpeedResource::make($this->whenLoaded('speed')),
            'term' => TermResource::make($this->whenLoaded('term')),
            'price' => $this->price,
            'installation_fee' => $this->installation_fee,
            'image_url' => $this->image_url,
            'includes_free_iptv' => $this->includes_free_iptv,
            'is_active' => $this->is_active,
            'recommended' => $this->recommended,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
