<?php

namespace App\Http\Resources\BroadbandApplication;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BroadbandApplicationPhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installation_application_id' => $this->installation_application_id,
            'image_url' => $this->image_url,
        ];
    }
}
