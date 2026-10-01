<?php

namespace App\Services\PackageActivation;

use App\Models\Package;
use App\Models\PackageOrder;
use App\Models\User;

interface PackageActivationServiceInterface
{
    public function activate(User $user, Package $package, PackageOrder $packageOrder): PackageActivationResult;
}
