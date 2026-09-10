<?php

namespace App\Http\Resources\Service;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => [
                'en' => $this->title_en,
                'zh' => $this->title_zh,
                'my' => $this->title_my,
            ],
            'description' => [
                'en' => $this->description_en,
                'zh' => $this->description_zh,
                'my' => $this->description_my,
            ],
            'image_url' => $this->image_url,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
