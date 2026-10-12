<?php

namespace App\Http\Resources\Customer;

use App\Http\Resources\UserResource;
use App\Http\Resources\WalletResource;
use App\Models\CustomerPackage;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{user: User, broadband_account?: array<string, mixed>|null, wallet: Wallet|null, packages: Collection<int, CustomerPackage>} $resource
 */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource['user'];
        /** @var Wallet|null $wallet */
        $wallet = $this->resource['wallet'];
        /** @var Collection<int, CustomerPackage> $packages */
        $packages = $this->resource['packages'];
        $broadbandAccount = $this->resource['broadband_account'] ?? null;
        return [
            'user' => UserResource::make($user),
            'broadbandAccount' => [
                'account_number' => $user->broadband_account_number,
                'customer_name' => is_array($broadbandAccount) ? $broadbandAccount['customer_name'] ?? null : null,
            ],
            'wallet' => $wallet ? WalletResource::make($wallet) : null,
            'packages' => $packages ? CustomerPackageResource::collection($packages) : null,
        ];
    }
}
