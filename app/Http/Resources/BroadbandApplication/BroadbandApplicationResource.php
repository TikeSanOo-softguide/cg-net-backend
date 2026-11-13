<?php

namespace App\Http\Resources\BroadbandApplication;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BroadbandApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'service_type' => $this->service_type,
            'ticket_number' => $this->ticket_number,
            'package_detail' => $this->package_detail,
            'address' => $this->address,
            'phone' => $this->phone,
            'note' => $this->note,
            'status' => $this->status?->value,
            'admin_id' => $this->admin_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
