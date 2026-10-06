<?php

namespace Tests\Unit;

use App\Services\PackageActivation\FakePackageActivationService;
use App\Services\PackageActivation\PackageActivationServiceInterface;
use App\Services\PackageActivation\RealPackageActivationService;
use RuntimeException;
use Tests\TestCase;

class PackageActivationBindingTest extends TestCase
{
    public function test_local_fake_driver_uses_the_fake_activator(): void
    {
        $this->assertActivator(FakePackageActivationService::class, 'local', 'fake');
    }

    public function test_local_unconfigured_driver_uses_the_real_activator(): void
    {
        $this->assertActivator(RealPackageActivationService::class, 'local', 'unconfigured');
    }

    public function test_local_real_driver_uses_the_real_activator(): void
    {
        $this->assertActivator(RealPackageActivationService::class, 'local', 'real');
    }

    public function test_production_fake_driver_is_rejected(): void
    {
        $this->assertFakeDriverIsRejected('production');
    }

    public function test_testing_fake_driver_is_rejected(): void
    {
        $this->assertFakeDriverIsRejected('testing');
    }

    private function assertActivator(string $expected, string $environment, string $driver): void
    {
        $this->app['env'] = $environment;
        config(['services.package_activation.driver' => $driver]);
        $this->app->forgetInstance(PackageActivationServiceInterface::class);

        $this->assertInstanceOf($expected, $this->app->make(PackageActivationServiceInterface::class));
    }

    private function assertFakeDriverIsRejected(string $environment): void
    {
        $this->app['env'] = $environment;
        config(['services.package_activation.driver' => 'fake']);
        $this->app->forgetInstance(PackageActivationServiceInterface::class);

        try {
            $this->app->make(PackageActivationServiceInterface::class);
            $this->fail('Fake package activation was resolved outside the local environment.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Fake package activation is only allowed when APP_ENV=local.',
                $exception->getMessage(),
            );
        }

        $this->assertFalse($this->app->resolved(PackageActivationServiceInterface::class));
    }
}
