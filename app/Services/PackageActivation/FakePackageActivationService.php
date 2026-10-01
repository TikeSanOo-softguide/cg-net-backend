<?php

namespace App\Services\PackageActivation;

use App\Models\Package;
use App\Models\PackageOrder;
use App\Models\User;
use App\Services\PackageActivation\PackageActivationResult;
use RuntimeException;

class FakePackageActivationService implements PackageActivationServiceInterface
{
    public int $calls = 0;

    public bool $failNextActivation = false;

    public bool $throwNextActivation = false;

    public bool $returnEmptyCredentials = false;

    public function reset(): void
    {
        $this->calls = 0;
        $this->failNextActivation = false;
        $this->throwNextActivation = false;
        $this->returnEmptyCredentials = false;
    }

    public function activate(User $user, Package $package, PackageOrder $packageOrder): PackageActivationResult
    {
        $this->calls++;

        if ($this->throwNextActivation) {
            $this->throwNextActivation = false;
            throw new RuntimeException('Activation provider is temporarily unavailable.');
        }

        if ($this->failNextActivation) {
            $this->failNextActivation = false;

            return PackageActivationResult::failed('Activation rejected by the fake provisioning service.');
        }

        if ($this->returnEmptyCredentials) {
            $this->returnEmptyCredentials = false;

            return new PackageActivationResult(success: true);
        }

        return PackageActivationResult::success('test-user', 'test-password');
    }
}
