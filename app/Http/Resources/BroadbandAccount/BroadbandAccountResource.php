<?php

namespace App\Http\Resources\BroadbandAccount;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BroadbandAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'account' => data_get($this->resource, 'account'),
            'name' => data_get($this->resource, 'name'),
            'mobile' => data_get($this->resource, 'mobile'),
            'address' => data_get($this->resource, 'address'),
            'area' => data_get($this->resource, 'area'),
            'productName' => data_get($this->resource, 'productName'),
            'productLine' => data_get($this->resource, 'productLine'),
            'productSpeed' => data_get($this->resource, 'productSpeed'),
            'productPrice' => data_get($this->resource, 'productPrice'),
            'starttime' => data_get($this->resource, 'starttime'),
            'endtime' => data_get($this->resource, 'endtime') ?? data_get($this->resource, 'finishTime'),
            'status' => data_get($this->resource, 'status'),
            'onlineStatus' => data_get($this->resource, 'onlineStatus'),
        ];
    }
}
