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
            ['path' => '/broadband-accounts', 'titleKey' => 'menu.broadband_accounts', 'name' => 'broadband-accounts.index', 'permission' => 'customers.view'],
            ['path' => '/cpe/inventory', 'titleKey' => 'menu.cpe_inventory', 'name' => 'cpe.inventory', 'permission' => 'cpe.view'],
            ['path' => '/cpe/assignment', 'titleKey' => 'menu.cpe_assignment', 'name' => 'cpe.assignment', 'permission' => 'cpe.view'],
            ['path' => '/cpe/status', 'titleKey' => 'menu.connection_status', 'name' => 'cpe.status', 'permission' => 'cpe.view'],
            ['path' => '/packages', 'titleKey' => 'menu.packages', 'name' => 'packages.index', 'permission' => 'packages.view'],
            ['path' => '/packages/auto-renew', 'titleKey' => 'menu.auto_renew_rules', 'name' => 'packages.auto-renew', 'permission' => 'packages.view'],
            ['path' => '/packages/recommended', 'titleKey' => 'menu.recommended_packages', 'name' => 'packages.recommended', 'permission' => 'packages.view'],
            ['path' => '/billing/invoices', 'titleKey' => 'menu.invoices', 'name' => 'billing.invoices', 'permission' => 'billing.view'],
            ['path' => '/billing/gateway-logs', 'titleKey' => 'menu.payment_gateway_logs', 'name' => 'billing.gateway-logs', 'permission' => 'billing.view'],
            ['path' => '/billing/transactions', 'titleKey' => 'menu.transactions', 'name' => 'billing.transactions', 'permission' => 'billing.view'],
            ['path' => '/service-requests/installations', 'titleKey' => 'menu.installation_applications', 'name' => 'service-requests.installations', 'permission' => 'service-requests.view'],
            ['path' => '/service-requests/relocations', 'titleKey' => 'menu.relocation_requests', 'name' => 'service-requests.relocations', 'permission' => 'service-requests.view'],
            ['path' => '/service-requests/change-plan', 'titleKey' => 'menu.change_plan_requests', 'name' => 'service-requests.change-plan', 'permission' => 'service-requests.view'],
            ['path' => '/notifications/compose', 'titleKey' => 'menu.push_composer', 'name' => 'notifications.compose', 'permission' => 'notifications.view'],
            ['path' => '/notifications/categories', 'titleKey' => 'menu.notification_categories', 'name' => 'notifications.categories', 'permission' => 'notifications.view'],
            ['path' => '/support/conversations', 'titleKey' => 'menu.chat_conversations', 'name' => 'support.conversations', 'permission' => 'support.view'],
            ['path' => '/support/agents', 'titleKey' => 'menu.agent_assignment', 'name' => 'support.agents', 'permission' => 'support.view'],
            ['path' => '/support/quick-replies', 'titleKey' => 'menu.quick_reply_templates', 'name' => 'support.quick-replies', 'permission' => 'support.view'],
            ['path' => '/activity-logs', 'titleKey' => 'menu.activity_logs', 'name' => 'activity-logs.index', 'permission' => 'activity.view'],
            ['path' => '/reports', 'titleKey' => 'menu.reports', 'name' => 'reports.index', 'permission' => 'reports.view'],
            ['path' => '/settings/app-version', 'titleKey' => 'menu.app_version', 'name' => 'settings.app-version', 'permission' => 'settings.view'],
            ['path' => '/settings/languages', 'titleKey' => 'menu.language_management', 'name' => 'settings.languages', 'permission' => 'settings.view'],
            ['path' => '/settings/general', 'titleKey' => 'menu.general_settings', 'name' => 'settings.general', 'permission' => 'settings.view'],
        ];
    }
}
