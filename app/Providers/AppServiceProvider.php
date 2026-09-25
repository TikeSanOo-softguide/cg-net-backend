<?php

namespace App\Providers;

use App\Models\Admin;
use App\Services\Auth\Otp\MockOtpProvider;
use App\Services\Auth\Otp\OtpProviderInterface;
use App\Services\Auth\Otp\SmsPohOtpProvider;
use App\Support\AppPermissions;
use App\Support\JsonTranslations;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->useLangPath(base_path('lang'));

        $this->app->singleton(OtpProviderInterface::class, function (): OtpProviderInterface {
            return match (config('otp.provider')) {
                'mock' => new MockOtpProvider(),
                'smspoh' => new SmsPohOtpProvider(),
                default => throw new \InvalidArgumentException('Unsupported OTP provider.'),
            };
        });

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (JsonTranslations::LOCALES as $locale) {
            Lang::addLines(JsonTranslations::flatten(JsonTranslations::load($locale)), $locale);
        }

        $this->registerLikeMacros();
        $this->configureRateLimiting();

        Gate::before(function ($user, string $ability): ?bool {
            if ($user instanceof Admin && $user->hasRole(AppPermissions::SuperAdmin)) {
                return true;
            }

            return null;
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('top-up-card-generation', function (Request $request) {
            $maxAttempts = max(1, (int) config('top_up_cards.rate_limit_per_minute', 5));

            return Limit::perMinute($maxAttempts)->by(
                (string) ($request->user()?->getAuthIdentifier() ?: $request->ip()),
            );
        });
    }

    private function registerLikeMacros(): void
    {
        $macro = function (string $method): \Closure {
            return function (string $column, string $value) use ($method) {
                /** @var EloquentBuilder|QueryBuilder $this */
                return $this->{$method}($column, Like::operator(), $value);
            };
        };

        EloquentBuilder::macro('whereLike', $macro('where'));
        EloquentBuilder::macro('orWhereLike', $macro('orWhere'));
        QueryBuilder::macro('whereLike', $macro('where'));
        QueryBuilder::macro('orWhereLike', $macro('orWhere'));
    }
}
