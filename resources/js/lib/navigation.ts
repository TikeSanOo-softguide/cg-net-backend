import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    AlertTriangle,
    ArrowRightLeft,
    Banknote,
    Bell,
    Boxes,
    ClipboardList,
    Contact,
    CreditCard,
    FileText,
    Files,
    FolderTree,
    Headphones,
    History,
    Image,
    Images,
    Languages,
    LayoutDashboard,
    Link2,
    MapPinned,
    Megaphone,
    MessageSquare,
    Move,
    Newspaper,
    Package,
    PenLine,
    Plug,
    Receipt,
    RefreshCw,
    Reply,
    Router,
    ScrollText,
    Settings,
    Shield,
    ShieldCheck,
    Signal,
    SlidersHorizontal,
    Smartphone,
    Sparkles,
    Tags,
    Ticket,
    UserCog,
    UserRound,
    Users,
    Wallet,
    Wifi,
} from 'lucide-react';

export type NavItem = {
    labelKey: string;
    descriptionKey: string;
    href: string;
    icon: LucideIcon;
};

export type NavGroup = {
    id: string;
    labelKey: string;
    descriptionKey?: string;
    href?: string;
    icon: LucideIcon;
    children?: NavItem[];
};

export type MenuPageContext = {
    titleKey: string;
    descriptionKey?: string;
};

export const navigation: NavGroup[] = [
    {
        id: 'dashboard',
        labelKey: 'menu.dashboard',
        descriptionKey: 'menu.dashboard_description',
        href: '/dashboard',
        icon: LayoutDashboard,
    },
    {
        id: 'customers',
        labelKey: 'menu.customer_management',
        icon: Users,
        children: [
            { labelKey: 'menu.customers_list', descriptionKey: 'menu.customers_list_description', href: '/customers', icon: UserRound },
            { labelKey: 'menu.broadband_accounts', descriptionKey: 'menu.broadband_accounts_description', href: '/broadband-accounts', icon: Wifi },
        ],
    },
    {
        id: 'cpe',
        labelKey: 'menu.cpe_management',
        icon: Router,
        children: [
            { labelKey: 'menu.cpe_inventory', descriptionKey: 'menu.cpe_inventory_description', href: '/cpe/inventory', icon: Boxes },
            { labelKey: 'menu.cpe_assignment', descriptionKey: 'menu.cpe_assignment_description', href: '/cpe/assignment', icon: Link2 },
            { labelKey: 'menu.connection_status', descriptionKey: 'menu.connection_status_description', href: '/cpe/status', icon: Signal },
        ],
    },
    {
        id: 'packages',
        labelKey: 'menu.package_management',
        icon: Package,
        children: [
            { labelKey: 'menu.packages', descriptionKey: 'menu.packages_description', href: '/packages', icon: Package },
            { labelKey: 'menu.auto_renew_rules', descriptionKey: 'menu.auto_renew_rules_description', href: '/packages/auto-renew', icon: RefreshCw },
            { labelKey: 'menu.recommended_packages', descriptionKey: 'menu.recommended_packages_description', href: '/packages/recommended', icon: Sparkles },
        ],
    },
    {
        id: 'billing',
        labelKey: 'menu.billing',
        icon: CreditCard,
        children: [
            { labelKey: 'menu.invoices', descriptionKey: 'menu.invoices_description', href: '/billing/invoices', icon: FileText },
            { labelKey: 'menu.payment_gateway_logs', descriptionKey: 'menu.payment_gateway_logs_description', href: '/billing/gateway-logs', icon: ScrollText },
            { labelKey: 'menu.transactions', descriptionKey: 'menu.transactions_description', href: '/billing/transactions', icon: Receipt },
        ],
    },
    {
        id: 'top-up-cards',
        labelKey: 'menu.top_up_card_management',
        icon: Ticket,
        children: [
            { labelKey: 'menu.top_up_card_batch', descriptionKey: 'menu.top_up_card_batch_description', href: '/top-up-cards/batch', icon: Ticket },
            { labelKey: 'menu.redeem_history', descriptionKey: 'menu.redeem_history_description', href: '/top-up-cards/redeem-history', icon: History },
        ],
    },
    {
        id: 'service-requests',
        labelKey: 'menu.service_requests',
        icon: ClipboardList,
        children: [
            { labelKey: 'menu.installation_applications', descriptionKey: 'menu.installation_applications_description', href: '/service-requests/installations', icon: Plug },
            { labelKey: 'menu.failure_reports', descriptionKey: 'menu.failure_reports_description', href: '/service-requests/failures', icon: AlertTriangle },
            { labelKey: 'menu.relocation_requests', descriptionKey: 'menu.relocation_requests_description', href: '/service-requests/relocations', icon: Move },
            { labelKey: 'menu.change_plan_requests', descriptionKey: 'menu.change_plan_requests_description', href: '/service-requests/change-plan', icon: ArrowRightLeft },
        ],
    },
    {
        id: 'regions',
        labelKey: 'menu.region_management',
        descriptionKey: 'menu.region_management_description',
        href: '/regions',
        icon: MapPinned,
    },
    {
        id: 'notifications',
        labelKey: 'menu.notifications',
        icon: Bell,
        children: [
            { labelKey: 'menu.push_composer', descriptionKey: 'menu.push_composer_description', href: '/notifications/compose', icon: PenLine },
            { labelKey: 'menu.notification_categories', descriptionKey: 'menu.notification_categories_description', href: '/notifications/categories', icon: Tags },
        ],
    },
    {
        id: 'support',
        labelKey: 'menu.support',
        icon: Headphones,
        children: [
            { labelKey: 'menu.chat_conversations', descriptionKey: 'menu.chat_conversations_description', href: '/support/conversations', icon: MessageSquare },
            { labelKey: 'menu.agent_assignment', descriptionKey: 'menu.agent_assignment_description', href: '/support/agents', icon: UserCog },
            { labelKey: 'menu.quick_reply_templates', descriptionKey: 'menu.quick_reply_templates_description', href: '/support/quick-replies', icon: Reply },
        ],
    },
    {
        id: 'cms',
        labelKey: 'menu.cms',
        icon: Files,
        children: [
            { labelKey: 'menu.cms_promotions', descriptionKey: 'menu.cms_promotions_description', href: '/cms/promotions', icon: Megaphone },
            { labelKey: 'menu.cms_banners', descriptionKey: 'menu.cms_banners_description', href: '/cms/banners', icon: Image },
            { labelKey: 'menu.cms_categories', descriptionKey: 'menu.cms_categories_description', href: '/cms/categories', icon: FolderTree },
            { labelKey: 'menu.cms_news', descriptionKey: 'menu.cms_news_description', href: '/cms/news', icon: Newspaper },
            { labelKey: 'menu.cms_gallery', descriptionKey: 'menu.cms_gallery_description', href: '/cms/gallery', icon: Images },
            { labelKey: 'menu.cms_contacts', descriptionKey: 'menu.cms_contacts_description', href: '/cms/contacts', icon: Contact },
        ],
    },
    {
        id: 'staff',
        labelKey: 'menu.staff_role_management',
        icon: Shield,
        children: [
            { labelKey: 'menu.staff_accounts', descriptionKey: 'menu.staff_accounts_description', href: '/staff', icon: UserRound },
            { labelKey: 'menu.roles', descriptionKey: 'menu.roles_description', href: '/roles', icon: ShieldCheck },
        ],
    },
    {
        id: 'activity',
        labelKey: 'menu.activity_logs',
        descriptionKey: 'menu.activity_logs_description',
        href: '/activity-logs',
        icon: Activity,
    },
    {
        id: 'reports',
        labelKey: 'menu.reports',
        descriptionKey: 'menu.reports_description',
        href: '/reports',
        icon: Banknote,
    },
    {
        id: 'settings',
        labelKey: 'menu.settings',
        icon: Settings,
        children: [
            { labelKey: 'menu.app_version', descriptionKey: 'menu.app_version_description', href: '/settings/app-version', icon: Smartphone },
            { labelKey: 'menu.language_management', descriptionKey: 'menu.language_management_description', href: '/settings/languages', icon: Languages },
            { labelKey: 'menu.general_settings', descriptionKey: 'menu.general_settings_description', href: '/settings/general', icon: SlidersHorizontal },
        ],
    },
];

export const bottomNavItems: { id: string; labelKey: string; href: string; icon: LucideIcon }[] = [
    { id: 'dashboard', labelKey: 'menu.dashboard', href: '/dashboard', icon: LayoutDashboard },
    { id: 'customers', labelKey: 'menu.customer_management', href: '/customers', icon: Users },
    { id: 'billing', labelKey: 'menu.billing', href: '/billing/invoices', icon: Wallet },
    { id: 'service-requests', labelKey: 'menu.service_requests', href: '/service-requests/installations', icon: ClipboardList },
    { id: 'support', labelKey: 'menu.support', href: '/support/conversations', icon: MessageSquare },
];

export const moreNavIds = new Set(bottomNavItems.map((item) => item.id));

const navHrefs: string[] = navigation.flatMap((group) => [
    ...(group.href ? [group.href] : []),
    ...(group.children?.map((child) => child.href) ?? []),
]);

export function isActivePath(current: string, href: string): boolean {
    if (href === '/dashboard') {
        return current === '/dashboard' || current === '/';
    }

    if (current === href) {
        return true;
    }

    if (!current.startsWith(`${href}/`)) {
        return false;
    }

    return !navHrefs.some(
        (other) => other !== href && other.startsWith(`${href}/`) && (current === other || current.startsWith(`${other}/`)),
    );
}

export function groupIsActive(current: string, group: NavGroup): boolean {
    if (group.href) {
        return isActivePath(current, group.href);
    }

    return Boolean(group.children?.some((child) => isActivePath(current, child.href)));
}

export function viewPermissionForHref(href: string): string | undefined {
    if (href === '/dashboard' || href === '/') {
        return 'dashboard.view';
    }

    if (href.startsWith('/customers') || href === '/broadband-accounts') {
        return 'customers.view';
    }

    if (href.startsWith('/cpe')) {
        return 'cpe.view';
    }

    if (href.startsWith('/packages')) {
        return 'packages.view';
    }

    if (href.startsWith('/billing')) {
        return 'billing.view';
    }

    if (href.startsWith('/top-up-cards')) {
        return 'top-up-cards.view';
    }

    if (href.startsWith('/service-requests')) {
        return 'service-requests.view';
    }

    if (href.startsWith('/regions')) {
        return 'regions.view';
    }

    if (href.startsWith('/notifications')) {
        return 'notifications.view';
    }

    if (href.startsWith('/support')) {
        return 'support.view';
    }

    if (href.startsWith('/cms')) {
        return 'cms.view';
    }

    if (href.startsWith('/roles')) {
        return 'roles.view';
    }

    if (href.startsWith('/staff')) {
        return 'staff.view';
    }

    if (href.startsWith('/activity-logs')) {
        return 'activity.view';
    }

    if (href.startsWith('/reports')) {
        return 'reports.view';
    }

    if (href.startsWith('/settings')) {
        return 'settings.view';
    }

    return undefined;
}

export function filterNavigation(groups: NavGroup[], can: (permission: string) => boolean): NavGroup[] {
    return groups.flatMap((group) => {
        if (group.children?.length) {
            const children = group.children.filter((child) => {
                const permission = viewPermissionForHref(child.href);

                return !permission || can(permission);
            });

            return children.length > 0 ? [{ ...group, children }] : [];
        }

        const permission = group.href ? viewPermissionForHref(group.href) : undefined;

        return !permission || can(permission) ? [group] : [];
    });
}

export function menuPageContextForPath(current: string): MenuPageContext | null {
    for (const group of navigation) {
        const child = group.children?.find((item) => isActivePath(current, item.href));

        if (child) {
            return {
                titleKey: child.labelKey,
                descriptionKey: child.descriptionKey,
            };
        }

        if (group.href && isActivePath(current, group.href)) {
            return {
                titleKey: group.labelKey,
                descriptionKey: group.descriptionKey,
            };
        }
    }

    return null;
}

export function resolvePageTitle(
    t: (key: string) => string,
    current: string,
    explicitTitle?: string,
): string {
    if (explicitTitle) {
        return explicitTitle;
    }

    const context = menuPageContextForPath(current);

    return context ? t(context.titleKey) : t('menu.dashboard');
}

export function resolvePageDescription(
    t: (key: string) => string,
    current: string,
    explicitDescription?: string,
): string | undefined {
    if (explicitDescription) {
        return explicitDescription;
    }

    const context = menuPageContextForPath(current);

    return context?.descriptionKey ? t(context.descriptionKey) : undefined;
}

export function titleKeyForPath(current: string): string {
    return menuPageContextForPath(current)?.titleKey ?? 'menu.dashboard';
}
