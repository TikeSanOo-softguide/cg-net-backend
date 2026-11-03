<?php

namespace App\Http\Resources\ChangePlanRequest;

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
            'broadband_account_number' => $this->broadband_account_number,
            'service_type' => $this->service_type,
            'ticket_number' => $this->ticket_number,
            'current_package_detail' => $this->current_package_detail,
            'new_package_detail' => $this->new_package_detail,
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
