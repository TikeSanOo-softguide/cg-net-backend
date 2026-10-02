import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link, router, usePage } from '@inertiajs/react';
import { BellIcon, CalendarIcon, CommandIcon, LayoutGridIcon, SettingsIcon, type LucideIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import type { AdminNotification, RecentNotification } from '@/types';

const navbarActionClass =
    'text-primary hover:bg-primary/12 hover:text-primary active:bg-primary/18 dark:text-foreground dark:hover:bg-muted dark:hover:text-foreground dark:active:bg-muted/80 transition-all duration-200 ease-out hover:scale-105 active:scale-95 motion-reduce:transition-colors motion-reduce:hover:scale-100 motion-reduce:active:scale-100';

const navbarIconClass =
    'size-5 transition-transform duration-200 ease-out group-hover:scale-110 group-hover:-rotate-12 motion-reduce:transition-none motion-reduce:group-hover:scale-100 motion-reduce:group-hover:rotate-0';

const categoryLook: Partial<Record<RecentNotification['category'], { icon: LucideIcon; className: string }>> = {
    request: {
        icon: CalendarIcon,
        className: 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
    },
    security: {
        icon: SettingsIcon,
        className: 'bg-orange-100 text-orange-500 dark:bg-orange-500/15 dark:text-orange-300',
    },
    other: {
        icon: LayoutGridIcon,
        className: 'bg-teal-100 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300',
    },
    service_update: {
        icon: CalendarIcon,
        className: 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
    },
    account: {
        icon: SettingsIcon,
        className: 'bg-orange-100 text-orange-500 dark:bg-orange-500/15 dark:text-orange-300',
    },
    promotion: {
        icon: LayoutGridIcon,
        className: 'bg-teal-100 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300',
    },
};

const fallbackLook = {
    icon: CommandIcon,
    className: 'bg-muted text-muted-foreground',
};

type NotificationDropdownProps = {
    unread: number;
};

export function NotificationDropdown({ unread }: NotificationDropdownProps) {
    const { t } = useTranslation();
    const { auth, recentNotifications = [] } = usePage().props;
    const canViewNotifications = auth?.permissions?.includes('notifications.view') ?? false;
    const rootRef = useRef<HTMLDivElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState(recentNotifications);
    const [unreadCount, setUnreadCount] = useState(unread);
    const [pos, setPos] = useState({ top: 0, left: 0, width: 280 });

    useEffect(() => {
        setNotifications(recentNotifications);
        setUnreadCount(unread);
    }, [recentNotifications, unread]);

    useEffect(() => {
        if (!open || !canViewNotifications) {
            return;
        }

        const loadNotifications = async () => {
            const response = await fetch('/dashboard/notifications?limit=5', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`Loading admin notifications failed with status ${response.status}.`);
            }

            const result: { data: AdminNotification[]; unread_count: number } = await response.json();
            setNotifications(result.data);
            setUnreadCount(result.unread_count);
        };

        void loadNotifications().catch((error: unknown) => {
            console.error('Unable to load admin notifications.', error);
        });
    }, [canViewNotifications, open]);

    const place = () => {
        if (!rootRef.current) {
            return;
        }

        const rect = rootRef.current.getBoundingClientRect();
        const width = Math.min(280, window.innerWidth - 16);
        const left = Math.min(Math.max(8, rect.right - width), window.innerWidth - width - 8);
        const top = Math.min(rect.bottom + 8, window.innerHeight - 16);

        setPos({ top, left, width });
    };

    useLayoutEffect(() => {
        if (!open) {
            return;
        }

        place();

        const onReposition = () => place();

        window.addEventListener('resize', onReposition);
        document.addEventListener('scroll', onReposition, true);

        return () => {
            window.removeEventListener('resize', onReposition);
            document.removeEventListener('scroll', onReposition, true);
        };
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: PointerEvent) => {
            const target = event.target as Node;

            if (rootRef.current?.contains(target) || panelRef.current?.contains(target)) {
                return;
            }

            setOpen(false);
        };

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    return (
        <div ref={rootRef} className="relative">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className={cn('group relative size-10', navbarActionClass)}
                aria-label={t('common.notifications')}
                aria-expanded={open}
                aria-haspopup="dialog"
                onClick={() => setOpen((current) => !current)}
            >
                <BellIcon className={navbarIconClass} strokeWidth={1.9} />
                {unreadCount > 0 ? (
                    <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full border-2 border-background bg-danger px-0.5 text-[9px] font-semibold leading-none text-danger-foreground">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                ) : null}
            </Button>
            {open
                ? createPortal(
                      <div
                          ref={panelRef}
                          role="dialog"
                          aria-label={t('common.notifications')}
                          className="fixed z-[90] overflow-hidden rounded-[8px] border border-primary/20 bg-card text-card-foreground shadow-[0_12px_40px_rgb(23_50_54/0.14)] dark:shadow-[0_12px_40px_rgb(0_0_0/0.4)]"
                          style={{ top: pos.top, left: pos.left, width: pos.width }}
                      >
                          <div className="flex items-center justify-between gap-2 px-3 py-2">
                              <h2 className="text-[13px] font-semibold tracking-tight text-primary">
                                  {t('common.notifications')}
                              </h2>
                              {unreadCount > 0 ? (
                                  <span className="inline-flex h-5 items-center rounded-full bg-primary px-2 text-[10px] font-semibold text-primary-foreground">
                                      {t('common.notifications_new').replace(':count', String(unreadCount))}
                                  </span>
                              ) : null}
                          </div>

                          <div className="max-h-[min(16rem,calc(100vh-14rem))] overflow-y-auto overscroll-contain">
                              {notifications.length === 0 ? (
                                  <p className="px-3 py-5 text-center text-[12px] text-muted-foreground">
                                      {t('common.no_notifications')}
                                  </p>
                              ) : (
                                  <ul>
                                      {notifications.map((item) => {
                                          const look = categoryLook[item.category] ?? fallbackLook;
                                          const Icon = look.icon;
                                          const isAdminNotification = 'reference_type' in item;
                                          const message = isAdminNotification ? item.message : item.body;
                                          const time = isAdminNotification
                                              ? new Date(item.created_at).toLocaleTimeString([], {
                                                    hour: 'numeric',
                                                    minute: '2-digit',
                                                })
                                              : item.time;
                                          const isRead = isAdminNotification ? item.read_at !== null : item.is_read;
                                          const content = (
                                              <div className="flex items-start gap-2 px-3 py-2">
                                                  <span
                                                      className={cn(
                                                          'relative mt-px flex size-7 shrink-0 items-center justify-center rounded-full',
                                                          look.className,
                                                      )}
                                                  >
                                                      <Icon className="size-3.5" strokeWidth={1.75} />

                                                      {!isRead && (
                                                          <span className="absolute top-0.5 right-0.5 size-1.5 rounded-full bg-danger ring-2 ring-background" />
                                                      )}
                                                  </span>
                                                  <div className="min-w-0 flex-1">
                                                      <div className="flex items-start justify-between gap-2">
                                                          <p
                                                              className={cn(
                                                                  'truncate text-[12px] leading-4 text-primary',
                                                                  isRead ? 'font-medium' : 'font-semibold',
                                                              )}
                                                          >
                                                              {item.title}
                                                          </p>
                                                          <span className="shrink-0 text-[10px] leading-4 text-muted-foreground">
                                                              {time}
                                                          </span>
                                                      </div>
                                                      <p className="mt-px line-clamp-1 text-[11px] leading-4 text-muted-foreground">
                                                          {message}
                                                      </p>
                                                  </div>
                                              </div>
                                          );

                                          return (
                                              <li key={`${isAdminNotification ? 'admin' : 'custom'}-${item.id}`}>
                                                  {isAdminNotification && item.href ? (
                                                      <button
                                                          type="button"
                                                          className="w-full text-left transition-colors hover:bg-muted/60 focus-visible:bg-muted/60 focus-visible:outline-none"
                                                          onClick={() => {
                                                              setOpen(false);
                                                              const href =
                                                                  item.reference_id === null
                                                                      ? item.href!
                                                                      : `${item.href}?status=all&open_request=${item.reference_id}`;

                                                              if (item.read_at === null) {
                                                                  router.put(
                                                                      `/dashboard/notifications/${item.id}/read`,
                                                                      {},
                                                                      {
                                                                          preserveScroll: true,
                                                                          onSuccess: () => {
                                                                              setNotifications((current) =>
                                                                                  current.map((notification) =>
                                                                                      notification.id === item.id &&
                                                                                      'reference_type' in notification
                                                                                          ? {
                                                                                                ...notification,
                                                                                                read_at:
                                                                                                    new Date().toISOString(),
                                                                                            }
                                                                                          : notification,
                                                                                  ),
                                                                              );
                                                                              setUnreadCount((current) =>
                                                                                  Math.max(0, current - 1),
                                                                              );
                                                                              router.visit(href);
                                                                          },
                                                                      },
                                                                  );
                                                              } else {
                                                                  router.visit(href);
                                                              }
                                                          }}
                                                      >
                                                          {content}
                                                      </button>
                                                  ) : (
                                                      content
                                                  )}
                                              </li>
                                          );
                                      })}
                                  </ul>
                              )}
                          </div>

                          <div className="p-2">
                              <Button
                                  asChild
                                  variant="primary"
                                  size="sm"
                                  className="h-7 w-full rounded-[6px] text-[11px] font-medium"
                              >
                                  <Link href="/dashboard/notifications" onClick={() => setOpen(false)}>
                                      {t('common.see_all_notifications')}
                                  </Link>
                              </Button>
                          </div>
                      </div>,
                      document.body,
                  )
                : null}
        </div>
    );
}
