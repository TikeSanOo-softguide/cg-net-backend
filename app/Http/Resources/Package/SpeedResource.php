<?php

namespace App\Http\Resources\Package;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpeedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mbps' => $this->mbps,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
