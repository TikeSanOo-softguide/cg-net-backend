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
        ];
    }
}
