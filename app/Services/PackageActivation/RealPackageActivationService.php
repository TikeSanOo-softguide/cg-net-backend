<?php

namespace App\Services\PackageActivation;

use App\Models\Package;
use App\Models\PackageOrder;
use App\Models\User;

class RealPackageActivationService implements PackageActivationServiceInterface
{
    public function activate(User $user, Package $package, PackageOrder $packageOrder): PackageActivationResult
    {
        return PackageActivationResult::failed('Package activation is not configured yet.');
    }
}
