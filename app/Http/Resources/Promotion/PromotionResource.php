<?php

namespace App\Http\Resources\Promotion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
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
            'slug' => $this->slug,
            'title' => [
                'en' => $this->title_en,
                'my' => $this->title_my,
                'zh' => $this->title_zh,
            ],
            'description' => [
                'en' => $this->description_en,
                'my' => $this->description_my,
                'zh' => $this->description_zh,
            ],
            'startDate' => $this->start_date
                ? $this->start_date->startOfDay()->toISOString()
                : null,
            'endDate' => $this->end_date
                ? $this->end_date->endOfDay()->toISOString()
                : null,
            'isActive' => (bool) $this->is_active,
            'imageUrl' => $this->image_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
