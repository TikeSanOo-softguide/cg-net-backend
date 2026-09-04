<?php

namespace App\Http\Resources\FailureReport;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FailureReportResource extends JsonResource
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
            'user_id' => $this->user_id,
            'broadband_account_id' => $this->broadband_account_id,
            'failure_type' => $this->failure_type,
            'description' => $this->description,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'status' => $this->status?->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'photos' => FailurePhotoResource::collection($this->whenLoaded('photos')),
        ];
    }
}
