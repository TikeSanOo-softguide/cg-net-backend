<?php

namespace App\Http\Resources\ChangePlanRequest;

use App\Http\Resources\Package\PackageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChangePlanRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'broadband_account_id' => $this->broadband_account_id,
            'current_package_id' => $this->current_package_id,
            'new_package_id' => $this->new_package_id,
            'current_package' => PackageResource::make($this->whenLoaded('currentPackage')),
            'new_package' => PackageResource::make($this->whenLoaded('newPackage')),
            'preferred_date' => $this->preferred_date?->toDateString(),
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'note' => $this->note,
            'status' => $this->status?->value,
            'admin_id' => $this->admin_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
