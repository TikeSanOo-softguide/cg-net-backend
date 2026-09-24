<?php

namespace App\Http\Resources\AppVersion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppVersionResource extends JsonResource
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
            'platform' => $this->platform,
            'version' => $this->version,
            'minimum_version' => $this->minimum_version,
            'download_url' => $this->download_url,
            'release_notes' => [
                'en' => $this->release_notes_en,
                'zh' => $this->release_notes_zh,
                'mm' => $this->release_notes_my,
            ],
            'force_update' => $this->force_update,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
