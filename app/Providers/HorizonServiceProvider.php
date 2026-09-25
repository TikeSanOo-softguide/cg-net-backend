<?php

namespace App\Providers;

use App\Models\Admin;
use App\Support\AppPermissions;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Laravel\Horizon\Horizon;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(function ($request): bool {
            return Gate::check('viewHorizon', [$request->user()]);
        });
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function (Admin $admin): bool {
            return $admin->hasRole(AppPermissions::SuperAdmin);
        });
    }
}
