<?php

namespace App\Support;

final class AppPermissions
{
    public const SuperAdmin = 'Super Admin';

    public const StaffOfficer = 'Staff Officer';

    public const SupportAgent = 'Support Agent';

    /**
     * Permission groups matching sidebar modules.
     *
     * @return list<array{module: string, labelKey: string, actions: list<string>}>
     */
    public static function groups(): array
    {
        return [
            ['module' => 'dashboard', 'labelKey' => 'menu.dashboard', 'actions' => ['view']],
            ['module' => 'customers', 'labelKey' => 'menu.customer_management', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'cpe', 'labelKey' => 'menu.cpe_management', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'packages', 'labelKey' => 'menu.package_management', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'billing', 'labelKey' => 'menu.billing', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'top-up-cards', 'labelKey' => 'menu.top_up_card_management', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'service-requests', 'labelKey' => 'menu.service_requests', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'regions', 'labelKey' => 'menu.region_management', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'notifications', 'labelKey' => 'menu.notifications', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'support', 'labelKey' => 'menu.support', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'cms', 'labelKey' => 'menu.cms', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'staff', 'labelKey' => 'menu.staff_accounts', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'roles', 'labelKey' => 'menu.roles', 'actions' => ['view', 'create', 'update', 'delete']],
            ['module' => 'activity', 'labelKey' => 'menu.activity_logs', 'actions' => ['view']],
            ['module' => 'reports', 'labelKey' => 'menu.reports', 'actions' => ['view']],
            ['module' => 'settings', 'labelKey' => 'menu.settings', 'actions' => ['view', 'update']],
        ];
    }

    public static function name(string $module, string $action): string
    {
        return $module.'.'.$action;
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        $names = [];

        foreach (self::groups() as $group) {
            foreach ($group['actions'] as $action) {
                $names[] = self::name($group['module'], $action);
            }
        }

        return $names;
    }

    /**
     * @return list<array{module: string, labelKey: string, permissions: list<array{name: string, action: string, labelKey: string}>}>
     */
    public static function matrix(): array
    {
        return array_map(function (array $group): array {
            return [
                'module' => $group['module'],
                'labelKey' => $group['labelKey'],
                'permissions' => array_map(fn (string $action): array => [
                    'name' => self::name($group['module'], $action),
                    'action' => $action,
                    'labelKey' => 'permissions.'.$action,
                ], $group['actions']),
            ];
        }, self::groups());
    }
}
