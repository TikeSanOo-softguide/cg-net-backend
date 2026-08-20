<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TelescopeServiceProvider extends ServiceProvider
{
    /**
     * Telescope is registered only when laravel/telescope is installed
     * (`composer require laravel/telescope --dev` then `php artisan telescope:install`).
     * Packagist was unreachable during scaffolding, so this remains a local-only hook.
     */
    public function register(): void
    {
        //
    }
}
