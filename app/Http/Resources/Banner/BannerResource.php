<?php

namespace App\Http\Resources\Banner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'image_url_en' => $this->image_url_en,
            'image_url_zh' => $this->image_url_zh,
            'image_url_my' => $this->image_url_my,
        ];
    }
}
