<?php

namespace App\Http\Resources\Customer;

use App\Models\CustomerPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property CustomerPackage $resource */
class CustomerPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $package = $this->package;

        return [
            'id' => $this->id,
            'details' => [
                'network_name' => [
                    'en' => $package?->network?->name_en,
                    'zh' => $package?->network?->name_zh,
                    'my' => $package?->network?->name_my,
                ],
                'speed' => [
                    'mbps' => $package?->speed?->mbps,
                ],
                'term' => [
                    'months' => $package?->term?->months,
                ],
            ],
            'username' => $this->username,
            'password' => $this->password,
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
