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
        $snapshot = $this->packageOrder?->snapshot ?? [];

        return [
            'id' => $this->id,
            'package_id' => $this->package_id,
            'details' => [
                'price' => $package?->price ?? $snapshot['product_price'] ?? null,
                'network_name' => [
                    'en' => $package?->network?->name_en ?? $snapshot['product_line'] ?? null,
                    'zh' => $package?->network?->name_zh,
                    'my' => $package?->network?->name_my,
                ],
                'speed' => [
                    'mbps' => $package?->speed?->mbps ?? $snapshot['product_speed'] ?? null,
                ],
                'term' => [
                    'months' => $package?->term?->months ?? $snapshot['term_months'] ?? null,
                    'cycle' => $snapshot['term_cycle'] ?? $snapshot['term_months'] ?? null,
                    'cycle_unit' => $snapshot['term_cycle_unit'] ?? $snapshot['month_cycle_unit'] ?? '月',
                ],
            ],
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
