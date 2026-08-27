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
            'recentNotifications' => $user ? $this->recentNotifications() : [],
            'flash' => $this->flashPayload($request),
        ];
    }

    /**
     * @return list<array{id: int, title: string, body: string, category: string, is_read: bool, time: string}>
     */
    private function recentNotifications(): array
    {
        return NotificationCustom::query()
            ->select(['id', 'title', 'body', 'category', 'is_read', 'sent_at', 'created_at'])
            ->latest('sent_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (NotificationCustom $notification): array => [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'category' => $notification->category->value,
                'is_read' => $notification->is_read,
                'time' => ($notification->sent_at ?? $notification->created_at)->format('g:i A'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{success: mixed, error: mixed, count: mixed, token: string|null}
     */
    private function flashPayload(Request $request): array
    {
        $success = $request->session()->pull('success');
        $error = $request->session()->pull('error');
        $count = $request->session()->pull('deleted_count');

        return [
            'success' => $success,
            'error' => $error,
            'count' => $count,
            'token' => ($success !== null || $error !== null) ? (string) str()->uuid() : null,
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
