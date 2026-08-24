<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\NotificationCustom;
use App\Support\AppPermissions;
use App\Support\JsonTranslations;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'permissions' => $user instanceof Admin
                    ? $user->getAllPermissions()->pluck('name')->values()->all()
                    : [],
                'roles' => $user instanceof Admin
                    ? $user->getRoleNames()->values()->all()
                    : [],
                'is_super_admin' => $user instanceof Admin && $user->hasRole(AppPermissions::SuperAdmin),
            ],
            'locale' => $locale,
            'translations' => $this->translationsFor($locale),
            'unreadNotifications' => $user
                ? NotificationCustom::query()->where('is_read', false)->count()
                : 0,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'count' => $request->session()->get('deleted_count'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function translationsFor(string $locale): array
    {
        return JsonTranslations::load($locale);
    }
}
