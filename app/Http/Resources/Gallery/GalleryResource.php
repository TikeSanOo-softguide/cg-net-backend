<?php

namespace App\Http\Resources\Gallery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,

            'label' => [
                'en' => $this->label_en,
                'my' => $this->label_my,
                'zh' => $this->label_zh,
            ],

            'imageUrl' => $this->image_url,
        ];
    }
}
