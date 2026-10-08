<?php

namespace App\Http\Resources\Settings\GeneralSettings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TermAndConditionResource extends JsonResource
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
            'title' => [
                'en' => $this->title_en,
                'zh' => $this->title_zh,
                'my' => $this->title_my,
            ],
        ];
    }
}
