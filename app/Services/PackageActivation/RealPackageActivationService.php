<?php

namespace App\Services\PackageActivation;

use App\Models\Package;
use App\Models\PackageOrder;
use App\Models\User;
use App\Services\CgNetApiClient;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

class RealPackageActivationService implements PackageActivationServiceInterface
{
    public function __construct(private CgNetApiClient $api) {}

    public function activate(User $user, Package $package, PackageOrder $packageOrder): PackageActivationResult
    {
        $account = (string) $packageOrder->account;
        $password = (string) $packageOrder->activation_password;
        $productId = (string) $packageOrder->product_id;
        $productPrice = $packageOrder->product_price;

        if (
            preg_match('/^G\d{7}$/D', $account) !== 1
            || preg_match('/^\d{6}$/D', $password) !== 1
            || $productId === ''
            || ! is_numeric($productPrice)
            || (float) $productPrice <= 0
        ) {
            return PackageActivationResult::failed('The package activation request is invalid.');
        }

        try {
            $response = $this->api->post('ActivateCGNETAccountAPI', [
                'OrderNo' => (string) $packageOrder->order_no,
                'Account' => $account,
                'Password' => $password,
                'ProductID' => $productId,
                'ProductPrice' => (float) $productPrice,
            ]);
        } catch (ConnectionException) {
            return PackageActivationResult::pending('The provider did not confirm the activation.');
        } catch (RuntimeException $exception) {
            report($exception);

            return PackageActivationResult::failed('Package activation is not configured.');
        }

        if (! $response->successful()) {
            return PackageActivationResult::pending('The provider did not confirm the activation.');
        }

        $payload = $response->json();

        if (
            ! is_array($payload)
            || ! array_key_exists('ErrCode', $payload)
            || ! is_int($payload['ErrCode'])
            || ! is_string($payload['Message'] ?? null)
        ) {
            return PackageActivationResult::pending('The provider returned an invalid activation response.');
        }

        return match ($payload['ErrCode']) {
            0 => PackageActivationResult::success($account, $password),
            100 => PackageActivationResult::failed('The provider rejected the product ID.', 100),
            500 => PackageActivationResult::failed('The provider rejected the activation request.', 500),
            1 => PackageActivationResult::pending('The provider reports that this order may already be open.', 1),
            2 => PackageActivationResult::pending('The provider reports that this account already exists.', 2),
            default => PackageActivationResult::pending('The provider returned an unrecognized activation result.', $payload['ErrCode']),
        };
    }
}
