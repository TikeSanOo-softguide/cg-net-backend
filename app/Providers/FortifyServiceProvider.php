<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Enums\AdminStatus;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Responses\LoginResponse;
use App\Models\Admin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('Auth/ForgotPassword'));
        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('Auth/ResetPassword', [
            'username' => $request->input('username', $request->query('email')),
            'token' => $request->route('token'),
        ]));
        Fortify::twoFactorChallengeView(fn () => Inertia::render('Auth/TwoFactorChallenge'));

        Fortify::authenticateUsing(function (Request $request) {
            $admin = Admin::query()->where('username', $request->input(Fortify::username()))->first();

            if (! $admin || ! Hash::check((string) $request->password, $admin->getAuthPassword())) {
                return null;
            }

            if ($admin->status !== AdminStatus::Active) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('auth.inactive'),
                ]);
            }

            return $admin;
        });

        $this->registerLoginRoutes();

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }

    /**
     * Register login routes after Fortify so the custom LoginController takes precedence.
     */
    private function registerLoginRoutes(): void
    {
        Route::middleware(config('fortify.middleware', ['web']))->group(function (): void {
            Route::get('/login', [LoginController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('login');

            Route::post('/login', [LoginController::class, 'store'])
                ->middleware(array_filter([
                    'guest:'.config('fortify.guard'),
                    config('fortify.limiters.login') ? 'throttle:'.config('fortify.limiters.login') : null,
                ]))
                ->name('login.store');
        });
    }
}
