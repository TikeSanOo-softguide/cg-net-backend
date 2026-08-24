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
    Tag,
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
    href: string;
    icon: LucideIcon;
};

export type NavGroup = {
    id: string;
    labelKey: string;
    href?: string;
    icon: LucideIcon;
    children?: NavItem[];
};

export const navigation: NavGroup[] = [
    { id: 'dashboard', labelKey: 'menu.dashboard', href: '/dashboard', icon: LayoutDashboard },
    {
        id: 'customers',
        labelKey: 'menu.customer_management',
        icon: Users,
        children: [
            { labelKey: 'menu.customers_list', href: '/customers', icon: UserRound },
            { labelKey: 'menu.broadband_accounts', href: '/broadband-accounts', icon: Wifi },
        ],
    },
    {
        id: 'cpe',
        labelKey: 'menu.cpe_management',
        icon: Router,
        children: [
            { labelKey: 'menu.cpe_inventory', href: '/cpe/inventory', icon: Boxes },
            { labelKey: 'menu.cpe_assignment', href: '/cpe/assignment', icon: Link2 },
            { labelKey: 'menu.connection_status', href: '/cpe/status', icon: Signal },
        ],
    },
    {
        id: 'packages',
        labelKey: 'menu.package_management',
        icon: Package,
        children: [
            { labelKey: 'menu.packages', href: '/packages', icon: Package },
            { labelKey: 'menu.auto_renew_rules', href: '/packages/auto-renew', icon: RefreshCw },
            { labelKey: 'menu.recommended_packages', href: '/packages/recommended', icon: Sparkles },
        ],
    },
    {
        id: 'billing',
        labelKey: 'menu.billing',
        icon: CreditCard,
        children: [
            { labelKey: 'menu.invoices', href: '/billing/invoices', icon: FileText },
            { labelKey: 'menu.payment_gateway_logs', href: '/billing/gateway-logs', icon: ScrollText },
            { labelKey: 'menu.transactions', href: '/billing/transactions', icon: Receipt },
        ],
    },
    {
        id: 'vouchers',
        labelKey: 'menu.voucher_management',
        icon: Ticket,
        children: [
            { labelKey: 'menu.voucher_batch', href: '/vouchers/batch', icon: Ticket },
            { labelKey: 'menu.redeem_history', href: '/vouchers/redeem-history', icon: History },
        ],
    },
    {
        id: 'service-requests',
        labelKey: 'menu.service_requests',
        icon: ClipboardList,
        children: [
            { labelKey: 'menu.installation_applications', href: '/service-requests/installations', icon: Plug },
            { labelKey: 'menu.failure_reports', href: '/service-requests/failures', icon: AlertTriangle },
            { labelKey: 'menu.relocation_requests', href: '/service-requests/relocations', icon: Move },
            { labelKey: 'menu.change_plan_requests', href: '/service-requests/change-plan', icon: ArrowRightLeft },
        ],
    },
    { id: 'regions', labelKey: 'menu.region_management', href: '/regions', icon: MapPinned },
    {
        id: 'notifications',
        labelKey: 'menu.notifications',
        icon: Bell,
        children: [
            { labelKey: 'menu.push_composer', href: '/notifications/compose', icon: PenLine },
            { labelKey: 'menu.notification_categories', href: '/notifications/categories', icon: Tags },
        ],
    },
    {
        id: 'support',
        labelKey: 'menu.support',
        icon: Headphones,
        children: [
            { labelKey: 'menu.chat_conversations', href: '/support/conversations', icon: MessageSquare },
            { labelKey: 'menu.agent_assignment', href: '/support/agents', icon: UserCog },
            { labelKey: 'menu.quick_reply_templates', href: '/support/quick-replies', icon: Reply },
        ],
    },
    {
        id: 'cms',
        labelKey: 'menu.cms',
        icon: Files,
        children: [
            { labelKey: 'menu.cms_promotions', href: '/cms/promotions', icon: Megaphone },
            { labelKey: 'menu.cms_banners', href: '/cms/banners', icon: Image },
            { labelKey: 'menu.cms_tags', href: '/cms/tags', icon: Tag },
            { labelKey: 'menu.cms_categories', href: '/cms/categories', icon: FolderTree },
            { labelKey: 'menu.cms_news', href: '/cms/news', icon: Newspaper },
            { labelKey: 'menu.cms_gallery', href: '/cms/gallery', icon: Images },
            { labelKey: 'menu.cms_contacts', href: '/cms/contacts', icon: Contact },
        ],
    },
    {
        id: 'staff',
        labelKey: 'menu.staff_role_management',
        icon: Shield,
        children: [
            { labelKey: 'menu.staff_accounts', href: '/staff', icon: UserRound },
            { labelKey: 'menu.roles', href: '/roles', icon: ShieldCheck },
        ],
    },
    { id: 'activity', labelKey: 'menu.activity_logs', href: '/activity-logs', icon: Activity },
    { id: 'reports', labelKey: 'menu.reports', href: '/reports', icon: Banknote },
    {
        id: 'settings',
        labelKey: 'menu.settings',
        icon: Settings,
        children: [
            { labelKey: 'menu.app_version', href: '/settings/app-version', icon: Smartphone },
            { labelKey: 'menu.language_management', href: '/settings/languages', icon: Languages },
            { labelKey: 'menu.general_settings', href: '/settings/general', icon: SlidersHorizontal },
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

    if (! current.startsWith(`${href}/`)) {
        return false;
    }

    return ! navHrefs.some(
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

    if (href.startsWith('/vouchers')) {
        return 'vouchers.view';
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

                return ! permission || can(permission);
            });

            return children.length > 0 ? [{ ...group, children }] : [];
        }

        const permission = group.href ? viewPermissionForHref(group.href) : undefined;

        return ! permission || can(permission) ? [group] : [];
    });
}

export function titleKeyForPath(current: string): string {
    if (/^\/customers\/\d+/.test(current)) {
        return 'menu.customer_detail';
    }

    if (/^\/staff\/\d+/.test(current)) {
        return 'staff.detail';
    }

    for (const group of navigation) {
        if (group.href && isActivePath(current, group.href)) {
            return group.labelKey;
        }

        const child = group.children?.find((item) => isActivePath(current, item.href));

        if (child) {
            return child.labelKey;
        }
    }

    return 'menu.dashboard';
}
