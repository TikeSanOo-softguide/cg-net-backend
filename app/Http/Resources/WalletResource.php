<?php

namespace App\Http\Resources;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Wallet|null $resource */
class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Wallet|null $wallet */
        $wallet = $this->resource;

        return [
            'balance' => $wallet->balance ?? 0,
            'version' => $wallet->version ?? 0,
            'status' => $wallet->status?->value,
        ];
    }
}
