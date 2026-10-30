<?php

namespace App\Http\Resources\Customer;

use App\Http\Resources\BroadbandAccount\BroadbandAccountResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\WalletResource;
use App\Models\CustomerPackage;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BroarbandAccount\AccountInfoService;
use App\Services\BroarbandAccount\BroadbandApiException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{user: User, wallet: Wallet|null, packages: Collection<int, CustomerPackage>} $resource
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
        $accountNumber = $user->broadband_account_number;
        $serviceType = null;
        $account = null;

        if (is_string($accountNumber) && str_starts_with($accountNumber, 'FTTH')) {
            $serviceType = 'FTTH';
        } elseif (is_string($accountNumber) && str_starts_with($accountNumber, 'CG')) {
            $serviceType = 'CG';
        }

        if ($serviceType !== null) {
            $accountNumber = substr($accountNumber, strlen($serviceType));
            try {
                $account = app(AccountInfoService::class)->findAccount($user->broadband_account_number);
            } catch (BroadbandApiException | ConnectionException) {
                $account = null;
            }
        }

        return [
            'user' => UserResource::make($user),
            'account' => $account !== null ? BroadbandAccountResource::make($account) : null,
            'broadbandAccount' => [
                'account_number' => $accountNumber,
                'service_type' => $serviceType,
            ],
            'wallet' => $wallet ? WalletResource::make($wallet) : null,
            'packages' => $packages ? CustomerPackageResource::collection($packages) : null,
        ];
    }
}
