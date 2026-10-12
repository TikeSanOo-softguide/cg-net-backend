<?php

namespace App\Services\AdminNotification;

use App\Enums\AdminStatus;
use App\Events\AdminNotificationCreated;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Support\AppPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminNotificationDelivery
{
    private const RECIPIENT_PERMISSION_CACHE_KEY = 'admin-notifications.eligible-admin-ids';

    private const RECIPIENT_PERMISSIONS = ['reports.view', 'service-requests.view'];

    public function deliver(AdminNotification $notification, string $permission): void
    {
        $eligibleAdminIds = Cache::remember(
            $this->recipientPermissionCacheKey($permission),
            now()->addDay(),
            fn (): array => $this->eligibleAdminIds($permission),
        );

        $admins = Admin::query()
            ->where('status', AdminStatus::Active->value)
            ->whereKey($eligibleAdminIds)
            ->get()
            ->values();

        if ($admins->isEmpty()) {
            Log::warning('Admin notification has no eligible recipients.', [
                'notification_id' => $notification->getKey(),
                'required_permission' => $permission,
            ]);

            return;
        }

        $notification->admins()->attach(
            $admins->mapWithKeys(fn (Admin $admin): array => [
                $admin->getKey() => ['is_read' => false, 'read_at' => null],
            ])->all(),
        );

        foreach ($admins as $admin) {
            AdminNotificationCreated::dispatch($notification, $admin);
        }
    }

    public function forgetCachedRecipients(): void
    {
        foreach (self::RECIPIENT_PERMISSIONS as $permission) {
            Cache::forget($this->recipientPermissionCacheKey($permission));
        }
    }

    /**
     * @return list<int>
     */
    private function eligibleAdminIds(string $permission): array
    {
        return Admin::query()
            ->where(function (Builder $query) use ($permission): void {
                $query
                    ->whereHas('roles', fn (Builder $roles) => $roles
                        ->where('name', AppPermissions::SuperAdmin)
                        ->where('guard_name', 'web'))
                    ->orWhere(function (Builder $query) use ($permission): void {
                        foreach (array_unique(['notifications.view', $permission]) as $requiredPermission) {
                            $query->where(function (Builder $query) use ($requiredPermission): void {
                                $query
                                    ->whereHas('permissions', fn (Builder $permissions) => $permissions
                                        ->where('name', $requiredPermission)
                                        ->where('guard_name', 'web'))
                                    ->orWhereHas('roles.permissions', fn (Builder $permissions) => $permissions
                                        ->where('name', $requiredPermission)
                                        ->where('guard_name', 'web'));
                            });
                        }
                    });
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function recipientPermissionCacheKey(string $permission): string
    {
        return self::RECIPIENT_PERMISSION_CACHE_KEY.'.'.sha1($permission);
    }
}
