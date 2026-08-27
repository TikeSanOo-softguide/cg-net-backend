import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link, usePage } from '@inertiajs/react';
import {
    BellIcon,
    CalendarIcon,
    CommandIcon,
    LayoutGridIcon,
    SettingsIcon,
    type LucideIcon,
} from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import type { RecentNotification } from '@/types';

const navbarActionClass =
    'text-primary hover:bg-primary/12 hover:text-primary active:bg-primary/18 dark:text-foreground dark:hover:bg-muted dark:hover:text-foreground dark:active:bg-muted/80 transition-all duration-200 ease-out hover:scale-105 active:scale-95 motion-reduce:transition-colors motion-reduce:hover:scale-100 motion-reduce:active:scale-100';

const navbarIconClass =
    'size-5 transition-transform duration-200 ease-out group-hover:scale-110 group-hover:-rotate-12 motion-reduce:transition-none motion-reduce:group-hover:scale-100 motion-reduce:group-hover:rotate-0';

const categoryLook: Record<RecentNotification['category'], { icon: LucideIcon; className: string }> = {
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
    const { recentNotifications = [] } = usePage().props;
    const rootRef = useRef<HTMLDivElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState({ top: 0, left: 0, width: 280 });

    const place = () => {
        if (! rootRef.current) {
            return;
        }

        const rect = rootRef.current.getBoundingClientRect();
        const width = Math.min(280, window.innerWidth - 16);
        const left = Math.min(Math.max(8, rect.right - width), window.innerWidth - width - 8);
        const top = Math.min(rect.bottom + 8, window.innerHeight - 16);

        setPos({ top, left, width });
    };

    useLayoutEffect(() => {
        if (! open) {
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
        if (! open) {
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
                onClick={() => setOpen((current) => ! current)}
            >
                <BellIcon className={navbarIconClass} strokeWidth={1.9} />
                {unread > 0 ? (
                    <span className="absolute top-1.5 right-1.5 flex size-4 items-center justify-center rounded-full bg-danger text-[9px] font-medium text-danger-foreground">
                        {unread > 9 ? '9+' : unread}
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
                              <h2 className="text-[13px] font-semibold tracking-tight text-primary">{t('common.notifications')}</h2>
                              {unread > 0 ? (
                                  <span className="inline-flex h-5 items-center rounded-full bg-primary px-2 text-[10px] font-semibold text-primary-foreground">
                                      {t('common.notifications_new').replace(':count', String(unread))}
                                  </span>
                              ) : null}
                          </div>

                          <div className="max-h-[min(16rem,calc(100vh-14rem))] overflow-y-auto overscroll-contain">
                              {recentNotifications.length === 0 ? (
                                  <p className="px-3 py-5 text-center text-[12px] text-muted-foreground">
                                      {t('common.no_notifications')}
                                  </p>
                              ) : (
                                  <ul>
                                      {recentNotifications.map((item) => {
                                          const look = categoryLook[item.category] ?? fallbackLook;
                                          const Icon = look.icon;

                                          return (
                                              <li key={item.id}>
                                                  <div className="flex items-start gap-2 px-3 py-2">
                                                      <span
                                                          className={cn(
                                                              'mt-px flex size-7 shrink-0 items-center justify-center rounded-full',
                                                              look.className,
                                                          )}
                                                      >
                                                          <Icon className="size-3.5" strokeWidth={1.75} />
                                                      </span>
                                                      <div className="min-w-0 flex-1">
                                                          <div className="flex items-start justify-between gap-2">
                                                              <p
                                                                  className={cn(
                                                                      'truncate text-[12px] leading-4 text-primary',
                                                                      item.is_read ? 'font-medium' : 'font-semibold',
                                                                  )}
                                                              >
                                                                  {item.title}
                                                              </p>
                                                              <span className="shrink-0 text-[10px] leading-4 text-muted-foreground">
                                                                  {item.time}
                                                              </span>
                                                          </div>
                                                          <p className="mt-px line-clamp-1 text-[11px] leading-4 text-muted-foreground">
                                                              {item.body}
                                                          </p>
                                                      </div>
                                                  </div>
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
                                  <Link href="/notifications/compose" onClick={() => setOpen(false)}>
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
