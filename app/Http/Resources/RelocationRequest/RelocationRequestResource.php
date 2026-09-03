<?php

namespace App\Http\Resources\RelocationRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RelocationRequestResource extends JsonResource
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
            'current_address' => $this->current_address,
            'new_address' => $this->new_address,
            'preferred_date' => $this->preferred_date?->toDateString(),
            'phone' => $this->phone,
            'details' => $this->details,
            'status' => $this->status?->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
