<?php

namespace App\Support;

final class MenuPages
{
    /**
     * Placeholder admin pages for sidebar items that are not yet fully built.
     *
     * @return list<array{path: string, titleKey: string, name: string, permission: string}>
     */
    public static function all(): array
    {
        return [
            [
                'path' => '/cpe/inventory',
                'titleKey' => 'menu.cpe_inventory',
                'name' => 'cpe.inventory',
                'permission' => 'cpe.view',
            ],
            [
                'path' => '/cpe/assignment',
                'titleKey' => 'menu.cpe_assignment',
                'name' => 'cpe.assignment',
                'permission' => 'cpe.view',
            ],
            [
                'path' => '/cpe/status',
                'titleKey' => 'menu.connection_status',
                'name' => 'cpe.status',
                'permission' => 'cpe.view',
            ],
            [
                'path' => '/packages',
                'titleKey' => 'menu.packages',
                'name' => 'packages.index',
                'permission' => 'packages.view',
            ],
            [
                'path' => '/billing/gateway-logs',
                'titleKey' => 'menu.payment_gateway_logs',
                'name' => 'billing.gateway-logs',
                'permission' => 'billing.view',
            ],
            [
                'path' => '/settings/languages',
                'titleKey' => 'menu.language_management',
                'name' => 'settings.languages',
                'permission' => 'settings.view',
            ],
            [
                'path' => '/settings/general',
                'titleKey' => 'menu.general_settings',
                'name' => 'settings.general',
                'permission' => 'settings.view',
            ],
        ];
    }
}
