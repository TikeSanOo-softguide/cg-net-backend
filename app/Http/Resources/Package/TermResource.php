<?php

namespace App\Http\Resources\Package;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TermResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'months' => $this->months,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
