<?php

namespace App\Http\Resources\FailureReport;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FailurePhotoResource extends JsonResource
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
            'failure_report_id' => $this->failure_report_id,
            'image_url' => $this->image_url,
            'label' => $this->label
        ];
    }
}
