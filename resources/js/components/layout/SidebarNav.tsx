import { useEffect, useRef, useState, type ReactElement } from 'react';
import { createPortal } from 'react-dom';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDownIcon } from 'lucide-react';

import { BrandLockup } from '@/components/layout/BrandLockup';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { useTranslation } from '@/hooks/useTranslation';
import { groupIsActive, isActivePath, navigation, type NavGroup, type NavItem } from '@/lib/navigation';
import { cn } from '@/lib/utils';

const EXPANDED_KEY = 'isp-admin-sidebar-pinned';

export const DESKTOP_SIDEBAR_QUERY = '(min-width: 1024px)';
const FINE_HOVER_QUERY = '(hover: hover) and (pointer: fine)';

type SidebarNavProps = {
    expanded: boolean;
    onNavigate?: () => void;
    groups?: typeof navigation;
    showBrand?: boolean;
};

const selectedItemClass =
    'bg-sidebar-item text-white hover:bg-sidebar-item-hover hover:text-white [&_svg]:text-white';
const selectedChildItemClass =
    'bg-sidebar-child-selected text-primary hover:bg-sidebar-child-selected-hover hover:text-primary [&_svg]:text-primary';
const idleItemClass = 'text-sidebar-foreground hover:bg-primary/12 hover:text-foreground';

function itemClass(active: boolean, expanded: boolean) {
    return cn(
        'flex items-center overflow-hidden rounded-[6px] text-[14px] font-medium',
        'motion-reduce:transition-none transition-[gap,color,background-color,padding,width,height] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
        expanded
            ? 'h-10 w-full justify-start gap-3 px-3'
            : 'mx-auto size-9 justify-center gap-0 px-0',
        active ? selectedItemClass : idleItemClass,
    );
}

function RailLabel({ children, label, disabled = false }: { children: ReactElement; label: string; disabled?: boolean }) {
    const [open, setOpen] = useState(false);

    return (
        <Tooltip delayDuration={80} open={disabled ? false : open} onOpenChange={setOpen}>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent
                side="right"
                align="center"
                sideOffset={16}
                collisionPadding={16}
                className="z-[80] rounded-[6px] border-border bg-surface px-2.5 py-1.5 text-sm font-medium text-foreground shadow-card"
            >
                {label}
            </TooltipContent>
        </Tooltip>
    );
}

function RailFlyout({
    label,
    childrenItems,
    current,
    onNavigate,
    t,
    trigger,
    disabled = false,
    canHover = true,
}: {
    label: string;
    childrenItems: NavItem[];
    current: string;
    onNavigate?: () => void;
    t: (key: string) => string;
    trigger: ReactElement;
    disabled?: boolean;
    canHover?: boolean;
}) {
    const triggerRef = useRef<HTMLDivElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState({ top: 0, left: 0 });
    const closeTimer = useRef<number>(0);

    const place = () => {
        if (! triggerRef.current) {
            return;
        }

        const rect = triggerRef.current.getBoundingClientRect();
        const panelWidth = 220;
        const left = Math.min(rect.right + 12, window.innerWidth - panelWidth - 8);
        const top = Math.min(rect.top, window.innerHeight - 280);

        setPos({ top: Math.max(8, top), left: Math.max(8, left) });
    };

    const show = () => {
        if (disabled) {
            return;
        }
        window.clearTimeout(closeTimer.current);
        place();
        setOpen(true);
    };

    const hide = () => {
        window.clearTimeout(closeTimer.current);
        closeTimer.current = window.setTimeout(() => setOpen(false), 140);
    };

    const hideNow = () => {
        window.clearTimeout(closeTimer.current);
        setOpen(false);
    };

    const onTriggerClick = () => {
        if (disabled || canHover) {
            return;
        }

        if (open) {
            hideNow();
        } else {
            show();
        }
    };

    useEffect(() => {
        if (disabled) {
            setOpen(false);
        }
    }, [disabled]);

    useEffect(() => {
        if (! open || disabled) {
            return;
        }

        const onPointerDown = (event: PointerEvent) => {
            const target = event.target as Node;

            if (triggerRef.current?.contains(target) || panelRef.current?.contains(target)) {
                return;
            }

            hideNow();
        };

        document.addEventListener('pointerdown', onPointerDown);

        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, [open, disabled]);

    useEffect(() => () => window.clearTimeout(closeTimer.current), []);

    return (
        <div
            ref={triggerRef}
            className="relative w-full"
            onMouseEnter={canHover ? show : undefined}
            onMouseLeave={canHover ? hide : undefined}
            onClick={onTriggerClick}
        >
            {trigger}
            {open && ! disabled
                ? createPortal(
                      <div
                          ref={panelRef}
                          className="fixed z-[90] max-h-[min(80vh,360px)] min-w-[220px] overflow-y-auto rounded-[6px] border border-border bg-surface p-1.5 shadow-card"
                          style={{ top: pos.top, left: pos.left }}
                          onMouseEnter={canHover ? show : undefined}
                          onMouseLeave={canHover ? hide : undefined}
                      >
                          <p className="px-2.5 py-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                              {label}
                          </p>
                          <ul className="flex flex-col gap-0.5">
                              {childrenItems.map((child) => {
                                  const childActive = isActivePath(current, child.href);
                                  const ChildIcon = child.icon;

                                  return (
                                      <li key={child.href}>
                                          <Link
                                              href={child.href}
                                              onClick={() => {
                                                  setOpen(false);
                                                  onNavigate?.();
                                              }}
                                              aria-current={childActive ? 'page' : undefined}
                                              className={cn(
                                                  'flex items-center gap-2.5 rounded-[6px] px-2.5 py-2 text-[14px] font-medium transition-colors duration-200',
                                                  childActive ? selectedChildItemClass : idleItemClass,
                                              )}
                                          >
                                              <ChildIcon className="size-4 shrink-0" strokeWidth={1.9} />
                                              <span className="truncate">{t(child.labelKey)}</span>
                                          </Link>
                                      </li>
                                  );
                              })}
                          </ul>
                      </div>,
                      document.body,
                  )
                : null}
        </div>
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
    const canHover = useMediaQuery(FINE_HOVER_QUERY);
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({});
    const scrollRef = useRef<HTMLNavElement>(null);
    const [stick, setStick] = useState({ show: false, top: 12, height: 56 });

    const updateStick = () => {
        const el = scrollRef.current;

        if (! el) {
            return;
        }

        const overflow = el.scrollHeight - el.clientHeight;

        if (overflow <= 1) {
            setStick((current) => (current.show ? { ...current, show: false } : current));

            return;
        }

        const inset = 12;
        const track = Math.max(el.clientHeight - inset * 2, 1);
        const height = Math.min(72, Math.max(48, Math.round(track * 0.18)));
        const maxTop = track - height;
        const top = inset + (el.scrollTop / overflow) * maxTop;

        setStick({ show: true, top, height });
    };

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

    useEffect(() => {
        const el = scrollRef.current;

        if (! el) {
            return;
        }

        updateStick();
        el.addEventListener('scroll', updateStick, { passive: true });
        const observer = new ResizeObserver(updateStick);
        observer.observe(el);

        if (el.firstElementChild) {
            observer.observe(el.firstElementChild);
        }

        return () => {
            el.removeEventListener('scroll', updateStick);
            observer.disconnect();
        };
    }, [expanded, openGroups]);

    const toggleGroup = (id: string) => {
        setOpenGroups((prev) => ({ ...prev, [id]: ! prev[id] }));
    };

    return (
        <div className="flex h-full min-h-0 flex-col overflow-hidden bg-transparent">
            {showBrand ? (
                <div
                    className={cn(
                        'flex h-[var(--navbar-height)] shrink-0 items-center overflow-hidden border-b border-sidebar-border',
                        'motion-reduce:transition-none transition-[padding] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
                        expanded ? 'justify-start px-3' : 'justify-center px-0',
                    )}
                >
                    <BrandLockup expanded={expanded} className="overflow-hidden" />
                </div>
            ) : null}

            <div className="relative min-h-0 flex-1">
                <nav
                    ref={scrollRef}
                    className={cn(
                        'sidebar-scroll h-full min-h-0 overflow-y-auto overflow-x-hidden overscroll-contain py-3',
                        'motion-reduce:transition-none transition-[padding] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
                        expanded ? 'px-3' : 'px-2',
                    )}
                    aria-label="Sidebar"
                >
                    <ul className="flex flex-col gap-2.5">
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
                                canHover={canHover}
                            />
                        ))}
                    </ul>
                </nav>
                {stick.show && expanded ? (
                    <span
                        aria-hidden
                        className="pointer-events-none absolute right-1 w-[3px] rounded-full bg-muted-foreground/40"
                        style={{ top: stick.top, height: stick.height }}
                    />
                ) : null}
            </div>
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
    canHover,
}: {
    group: NavGroup;
    expanded: boolean;
    current: string;
    open: boolean;
    onToggle: () => void;
    onNavigate?: () => void;
    t: (key: string) => string;
    canHover: boolean;
}) {
    const active = groupIsActive(current, group);
    const Icon = group.icon;
    const label = t(group.labelKey);
    const href = group.href ?? group.children?.[0]?.href ?? '#';

    const row = (
        <div className={itemClass(active, expanded)}>
            <Icon className="size-5 shrink-0" strokeWidth={1.9} />
            <span
                className={cn(
                    'min-w-0 flex-1 truncate text-left',
                    'motion-reduce:transition-none transition-[max-width,opacity] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
                    expanded ? 'max-w-[11.25rem] opacity-100' : 'pointer-events-none max-w-0 opacity-0',
                )}
                aria-hidden={! expanded}
            >
                {label}
            </span>
            {group.children ? (
                <ChevronDownIcon
                    className={cn(
                        'size-4 shrink-0 overflow-hidden',
                        'motion-reduce:transition-none transition-[width,opacity,transform,margin] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
                        expanded ? 'w-4 opacity-100' : 'w-0 opacity-0',
                        open && expanded && 'rotate-180',
                    )}
                    strokeWidth={1.9}
                />
            ) : null}
        </div>
    );

    const trigger = group.children ? (
        <button type="button" onClick={expanded ? onToggle : undefined} className="w-full outline-none focus-visible:ring-2 focus-visible:ring-primary/30 rounded-[6px]" aria-expanded={open}>
            {row}
        </button>
    ) : (
        <Link href={href} onClick={onNavigate} className="block w-full outline-none focus-visible:ring-2 focus-visible:ring-primary/30 rounded-[6px]">
            {row}
        </Link>
    );

    return (
        <li>
            {group.children?.length ? (
                <RailFlyout
                    label={label}
                    childrenItems={group.children}
                    current={current}
                    onNavigate={onNavigate}
                    t={t}
                    trigger={trigger}
                    disabled={expanded}
                    canHover={canHover}
                />
            ) : (
                <RailLabel label={label} disabled={expanded || ! canHover}>
                    {trigger}
                </RailLabel>
            )}
            {group.children ? (
                <div
                    className={cn(
                        'grid motion-reduce:transition-none transition-[grid-template-rows,opacity] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
                        expanded && open ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0',
                    )}
                >
                    <div className="overflow-hidden">
                        <ul className="mt-1.5 ml-3 flex flex-col gap-2 border-l border-sidebar-border pl-2">
                            {group.children.map((child) => {
                                const childActive = isActivePath(current, child.href);
                                const ChildIcon = child.icon;

                                return (
                                    <li key={child.href}>
                                        <Link
                                            href={child.href}
                                            onClick={onNavigate}
                                            aria-current={childActive ? 'page' : undefined}
                                            tabIndex={expanded && open ? 0 : -1}
                                            className={cn(
                                                'flex items-center gap-2.5 rounded-[6px] px-3 py-2 text-[14px] font-medium transition-colors duration-200 outline-none focus-visible:ring-2 focus-visible:ring-primary/30',
                                                childActive ? selectedChildItemClass : idleItemClass,
                                            )}
                                        >
                                            <ChildIcon className="size-4 shrink-0" strokeWidth={1.9} />
                                            <span className="truncate">{t(child.labelKey)}</span>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </div>
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
