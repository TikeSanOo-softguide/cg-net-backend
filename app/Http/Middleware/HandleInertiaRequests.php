<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\NotificationCustom;
use App\Support\AppPermissions;
use App\Support\JsonTranslations;
use App\Support\NavigationStack;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

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

    public function handle(Request $request, \Closure $next): Response
    {
        $response = parent::handle($request, $next);
        NavigationStack::commit($request, $response);

        return $response;
    }

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
                'user' => $user
                    ? [
                        'id' => $user->id,
                        'username' => $user->username,
                    ]
                    : null,
                'permissions' => $user instanceof Admin ? $user->getAllPermissions()->pluck('name')->values()->all() : [],
                'roles' => $user instanceof Admin ? $user->getRoleNames()->values()->all() : [],
                'is_super_admin' => $user instanceof Admin && $user->hasRole(AppPermissions::SuperAdmin),
            ],
            'locale' => $locale,
            'translations' => $this->translationsFor($locale),
            'unreadNotifications' =>
                $user instanceof Admin
                    ? ($user->can('notifications.view')
                        ? AdminNotification::query()->whereNull('read_at')->count()
                        : 0)
                    : ($user
                        ? NotificationCustom::query()->where('user_id', $user->id)->where('is_read', false)->count()
                        : 0),
            'recentNotifications' =>
                $user instanceof Admin
                    ? ($user->can('notifications.view')
                        ? $this->recentAdminNotifications()
                        : [])
                    : ($user
                        ? $this->recentNotifications($user->id)
                        : []),
            'flash' => $this->flashPayload($request),
            'return_to' => NavigationStack::preview($request),
        ];
    }

    /**
     * @return list<array{id: int, source: string, title: string, body: string, category: string, is_read: bool, time: string}>
     */
    private function recentNotifications(int $userId): array
    {
        return NotificationCustom::query()
            ->select(['id', 'title', 'body', 'category', 'is_read', 'sent_at', 'created_at'])
            ->where('user_id', $userId)
            ->latest('sent_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(
                fn (NotificationCustom $notification): array => [
                    'id' => $notification->id,
                    'source' => 'custom',
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'category' => $notification->category->value,
                    'is_read' => $notification->is_read,
                    'time' => ($notification->sent_at ?? $notification->created_at)->format('g:i A'),
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentAdminNotifications(): array
    {
        return AdminNotification::query()
            ->latest('created_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (AdminNotification $notification): array => $notification->toDropdownArray())
            ->all();
    }

    /**
     * @return array{success: mixed, error: mixed, import_error: mixed, import_error_token: string|null, count: mixed, token: string|null}
     */
    private function flashPayload(Request $request): array
    {
        $success = $request->session()->pull('success');
        $error = $request->session()->pull('error');
        $importError = $request->session()->pull('import_error');
        $count = $request->session()->pull('deleted_count');
        $importErrorToken = $importError !== null ? (string) str()->uuid() : null;

        return [
            'success' => $success,
            'error' => $error,
            'import_error' => $importError,
            'import_error_token' => $importErrorToken,
            'count' => $count,
            'token' => $success !== null || $error !== null ? (string) str()->uuid() : null,
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
