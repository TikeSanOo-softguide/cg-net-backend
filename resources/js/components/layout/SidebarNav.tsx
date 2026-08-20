import { useEffect, useState, type ReactElement } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDownIcon } from 'lucide-react';

import { BrandLockup } from '@/components/layout/BrandLockup';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useTranslation } from '@/hooks/useTranslation';
import { groupIsActive, isActivePath, navigation, type NavGroup } from '@/lib/navigation';
import { cn } from '@/lib/utils';

const EXPANDED_KEY = 'isp-admin-sidebar-pinned';

type SidebarNavProps = {
    expanded: boolean;
    onNavigate?: () => void;
    groups?: typeof navigation;
    showBrand?: boolean;
};

function itemClass(active: boolean, expanded: boolean) {
    return cn(
        'flex items-center rounded-[8px] text-[15px] font-medium transition-colors duration-200',
        expanded ? 'w-full gap-3 px-3 py-2.5' : 'mx-auto size-11 justify-center p-0',
        active
            ? 'bg-primary text-primary-foreground hover:bg-primary-hover hover:text-primary-foreground'
            : 'text-sidebar-foreground hover:bg-primary/12 hover:text-foreground',
    );
}

function RailLabel({ children, label }: { children: ReactElement; label: string }) {
    return (
        <Tooltip delayDuration={80}>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent
                side="right"
                align="center"
                sideOffset={16}
                collisionPadding={16}
                className="z-[80] rounded-[8px] border-border bg-surface px-2.5 py-1.5 text-sm font-medium text-foreground shadow-card"
            >
                {label}
            </TooltipContent>
        </Tooltip>
    );
}

export function SidebarNav({
    expanded,
    onNavigate,
    groups = navigation,
    showBrand = true,
}: SidebarNavProps) {
    const { t } = useTranslation();
    const { url } = usePage();
    const current = url.split('?')[0];
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({});

    useEffect(() => {
        setOpenGroups((prev) => {
            const next: Record<string, boolean> = { ...prev };
            let changed = false;

            for (const group of groups) {
                if (groupIsActive(current, group) && ! next[group.id]) {
                    next[group.id] = true;
                    changed = true;
                }
            }

            return changed ? next : prev;
        });
    }, [current, groups]);

    const toggleGroup = (id: string) => {
        setOpenGroups((prev) => ({ ...prev, [id]: ! prev[id] }));
    };

    return (
        <div className="flex h-full min-h-0 flex-col overflow-hidden bg-sidebar">
            {showBrand ? (
                <div
                    className={cn(
                        'flex h-[var(--navbar-height)] shrink-0 items-center border-b border-sidebar-border px-3',
                        expanded ? '' : 'justify-center',
                    )}
                >
                    <BrandLockup compact={! expanded} logoClassName={expanded ? undefined : 'size-11'} />
                </div>
            ) : null}

            <nav
                className="sidebar-scroll min-h-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-contain px-3 pt-3 pb-4"
                aria-label="Sidebar"
            >
                <ul className="flex flex-col gap-2">
                    {groups.map((group) => (
                        <SidebarGroup
                            key={group.id}
                            group={group}
                            expanded={expanded}
                            current={current}
                            open={Boolean(openGroups[group.id])}
                            onToggle={() => toggleGroup(group.id)}
                            onNavigate={onNavigate}
                            t={t}
                        />
                    ))}
                </ul>
            </nav>
        </div>
    );
}

function SidebarGroup({
    group,
    expanded,
    current,
    open,
    onToggle,
    onNavigate,
    t,
}: {
    group: NavGroup;
    expanded: boolean;
    current: string;
    open: boolean;
    onToggle: () => void;
    onNavigate?: () => void;
    t: (key: string) => string;
}) {
    const active = groupIsActive(current, group);
    const Icon = group.icon;
    const label = t(group.labelKey);
    const href = group.href ?? group.children?.[0]?.href ?? '#';

    if (! expanded) {
        return (
            <li>
                <RailLabel label={label}>
                    <Link href={href} onClick={onNavigate} className={itemClass(active, false)}>
                        <Icon className="size-5 shrink-0" strokeWidth={1.9} />
                        <span className="sr-only">{label}</span>
                    </Link>
                </RailLabel>
            </li>
        );
    }

    if (! group.children) {
        return (
            <li>
                <Link href={href} onClick={onNavigate} className={itemClass(active, true)}>
                    <Icon className="size-5 shrink-0" strokeWidth={1.9} />
                    <span className="truncate">{label}</span>
                </Link>
            </li>
        );
    }

    return (
        <li>
            <button type="button" onClick={onToggle} className={itemClass(active, true)} aria-expanded={open}>
                <Icon className="size-5 shrink-0" strokeWidth={1.9} />
                <span className="flex-1 truncate text-left">{label}</span>
                <ChevronDownIcon className={cn('size-4 shrink-0 transition-transform duration-200', open && 'rotate-180')} />
            </button>
            {open ? (
                <ul className="mt-1 ml-3 flex flex-col gap-1.5 border-l border-sidebar-border pl-2">
                    {group.children.map((child) => {
                        const childActive = isActivePath(current, child.href);

                        return (
                            <li key={child.href}>
                                <Link
                                    href={child.href}
                                    onClick={onNavigate}
                                    className={cn(
                                        'flex items-center rounded-[8px] px-3 py-2 text-sm font-medium transition-colors duration-200',
                                        childActive
                                            ? 'bg-primary text-primary-foreground hover:bg-primary-hover hover:text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-primary/12 hover:text-foreground',
                                    )}
                                >
                                    {t(child.labelKey)}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            ) : null}
        </li>
    );
}

export function readSidebarExpanded(): boolean {
    if (typeof window === 'undefined') {
        return true;
    }

    const stored = window.localStorage.getItem(EXPANDED_KEY);

    return stored === null ? true : stored === 'true';
}

export function writeSidebarExpanded(expanded: boolean): void {
    window.localStorage.setItem(EXPANDED_KEY, String(expanded));
}
