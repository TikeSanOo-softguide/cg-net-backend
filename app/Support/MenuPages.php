<?php

namespace App\Support;

final class MenuPages
{
    /**
     * Placeholder admin pages for every sidebar item except Dashboard.
     *
     * @return list<array{path: string, titleKey: string, name: string}>
     */
    public static function all(): array
    {
        return [
            ['path' => '/broadband-accounts', 'titleKey' => 'menu.broadband_accounts', 'name' => 'broadband-accounts.index'],
            ['path' => '/cpe/inventory', 'titleKey' => 'menu.cpe_inventory', 'name' => 'cpe.inventory'],
            ['path' => '/cpe/assignment', 'titleKey' => 'menu.cpe_assignment', 'name' => 'cpe.assignment'],
            ['path' => '/cpe/status', 'titleKey' => 'menu.connection_status', 'name' => 'cpe.status'],
            ['path' => '/packages', 'titleKey' => 'menu.packages', 'name' => 'packages.index'],
            ['path' => '/packages/auto-renew', 'titleKey' => 'menu.auto_renew_rules', 'name' => 'packages.auto-renew'],
            ['path' => '/packages/recommended', 'titleKey' => 'menu.recommended_packages', 'name' => 'packages.recommended'],
            ['path' => '/billing/invoices', 'titleKey' => 'menu.invoices', 'name' => 'billing.invoices'],
            ['path' => '/billing/gateway-logs', 'titleKey' => 'menu.payment_gateway_logs', 'name' => 'billing.gateway-logs'],
            ['path' => '/billing/transactions', 'titleKey' => 'menu.transactions', 'name' => 'billing.transactions'],
            ['path' => '/vouchers/batch', 'titleKey' => 'menu.voucher_batch', 'name' => 'vouchers.batch'],
            ['path' => '/vouchers/redeem-history', 'titleKey' => 'menu.redeem_history', 'name' => 'vouchers.redeem-history'],
            ['path' => '/service-requests/installations', 'titleKey' => 'menu.installation_applications', 'name' => 'service-requests.installations'],
            ['path' => '/service-requests/failures', 'titleKey' => 'menu.failure_reports', 'name' => 'service-requests.failures'],
            ['path' => '/service-requests/relocations', 'titleKey' => 'menu.relocation_requests', 'name' => 'service-requests.relocations'],
            ['path' => '/service-requests/change-plan', 'titleKey' => 'menu.change_plan_requests', 'name' => 'service-requests.change-plan'],
            ['path' => '/regions', 'titleKey' => 'menu.region_management', 'name' => 'regions.index'],
            ['path' => '/notifications/compose', 'titleKey' => 'menu.push_composer', 'name' => 'notifications.compose'],
            ['path' => '/notifications/categories', 'titleKey' => 'menu.notification_categories', 'name' => 'notifications.categories'],
            ['path' => '/support/conversations', 'titleKey' => 'menu.chat_conversations', 'name' => 'support.conversations'],
            ['path' => '/support/agents', 'titleKey' => 'menu.agent_assignment', 'name' => 'support.agents'],
            ['path' => '/support/quick-replies', 'titleKey' => 'menu.quick_reply_templates', 'name' => 'support.quick-replies'],
            ['path' => '/banners', 'titleKey' => 'menu.banners', 'name' => 'banners.index'],
            ['path' => '/staff/accounts', 'titleKey' => 'menu.staff_accounts', 'name' => 'staff.accounts'],
            ['path' => '/staff/roles', 'titleKey' => 'menu.roles', 'name' => 'staff.roles'],
            ['path' => '/staff/permissions', 'titleKey' => 'menu.permissions_matrix', 'name' => 'staff.permissions'],
            ['path' => '/activity-logs', 'titleKey' => 'menu.activity_logs', 'name' => 'activity-logs.index'],
            ['path' => '/reports', 'titleKey' => 'menu.reports', 'name' => 'reports.index'],
            ['path' => '/settings/app-version', 'titleKey' => 'menu.app_version', 'name' => 'settings.app-version'],
            ['path' => '/settings/languages', 'titleKey' => 'menu.language_management', 'name' => 'settings.languages'],
            ['path' => '/settings/general', 'titleKey' => 'menu.general_settings', 'name' => 'settings.general'],
        ];
    }
}
