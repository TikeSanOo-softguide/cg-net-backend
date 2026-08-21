<?php

namespace App\Http\Middleware;

use App\Models\NotificationCustom;
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
            ],
            'locale' => $locale,
            'translations' => $this->translationsFor($locale),
            'unreadNotifications' => $user
                ? NotificationCustom::query()->where('is_read', false)->count()
                : 0,
            'flash' => [
                'success' => $request->session()->get('success'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function translationsFor(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! is_readable($path)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
