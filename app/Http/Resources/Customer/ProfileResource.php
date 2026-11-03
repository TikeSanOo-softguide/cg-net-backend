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
        $serviceType = $user->service_type;
        $accountNumber = $user->broadband_account_number;
        $account = null;

        if ($serviceType !== null && $accountNumber !== null && $accountNumber !== '') {
            try {
                $account = app(AccountInfoService::class)->findAccount($serviceType, $accountNumber);
            } catch (BroadbandApiException | ConnectionException $exception) {
                report($exception);
            }
        }

        return [
            'user' => UserResource::make($user),
            'account' => $account !== null ? BroadbandAccountResource::make($account) : null,
            'broadbandAccount' =>
                $accountNumber !== null
                    ? [
                        'account_number' => $accountNumber,
                        'service_type' => $serviceType,
                    ]
                    : null,
            'wallet' => $wallet ? WalletResource::make($wallet) : null,
            'packages' => $packages ? CustomerPackageResource::collection($packages) : null,
        ];
    }
}
