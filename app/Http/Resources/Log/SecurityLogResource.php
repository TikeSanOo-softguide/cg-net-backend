<?php

namespace App\Http\Resources\Log;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecurityLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $actor = $this->actor;

        return [
            'id' => $this->id,
            'actor' => $actor
                ? [
                    'id' => $actor->getKey(),
                    'type' => class_basename($this->actor_type),
                    'name' => $actor->username ?? ($actor->name ?? ($actor->phone ?? 'Unknown')),
                ]
                : null,
            'event' => $this->event,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at,
        ];
    }
}
