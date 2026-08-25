import { useEffect, useMemo, useState, type ReactNode } from 'react';

import {
    DESKTOP_SIDEBAR_QUERY,
    SidebarNav,
    readSidebarExpanded,
    writeSidebarExpanded,
} from '@/components/layout/SidebarNav';
import { FlashToast } from '@/components/FlashToast';
import { FloatingThemeSettingsButton } from '@/components/theme/FloatingThemeSettingsButton';
import { SidebarToggle } from '@/components/layout/SidebarToggle';
import { TopBar } from '@/components/layout/TopBar';
import { Toaster } from '@/components/ui/toaster';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useCan } from '@/hooks/useCan';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { useTranslation } from '@/hooks/useTranslation';
import { filterNavigation, navigation } from '@/lib/navigation';
import { cn } from '@/lib/utils';

type AppLayoutProps = {
    children: ReactNode;
};

function readExpandedForViewport(isDesktop: boolean): boolean {
    return isDesktop ? readSidebarExpanded() : false;
}

export default function AppLayout({ children }: AppLayoutProps) {
    const { t } = useTranslation();
    const can = useCan();
    const isDesktop = useMediaQuery(DESKTOP_SIDEBAR_QUERY);
    const [expanded, setExpanded] = useState(() =>
        readExpandedForViewport(typeof window !== 'undefined' && window.matchMedia(DESKTOP_SIDEBAR_QUERY).matches),
    );
    const groups = useMemo(() => filterNavigation(navigation, can), [can]);

    useEffect(() => {
        setExpanded(readExpandedForViewport(isDesktop));
    }, [isDesktop]);

    useEffect(() => {
        const root = document.documentElement;
        const layoutWidth = isDesktop && expanded ? 'min(var(--sidebar-width), 85vw)' : '88px';

        root.style.setProperty('--app-navbar-current', 'var(--navbar-height)');
        root.style.setProperty('--app-sidebar-current', layoutWidth);

        return () => {
            root.style.removeProperty('--app-navbar-current');
            root.style.removeProperty('--app-sidebar-current');
        };
    }, [expanded, isDesktop]);

    useEffect(() => {
        if (isDesktop || ! expanded) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setExpanded(false);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [expanded, isDesktop]);

    const toggleSidebar = () => {
        setExpanded((current) => {
            const next = ! current;

            if (isDesktop) {
                writeSidebarExpanded(next);
            }

            return next;
        });
    };

    const closeMobileSidebar = () => {
        if (! isDesktop) {
            setExpanded(false);
        }
    };

    return (
        <TooltipProvider>
            <div className="relative flex h-dvh overflow-hidden app-shell">
                <div
                    className={cn(
                        'shrink-0',
                        'motion-reduce:transition-none transition-[width] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
                        isDesktop && expanded ? 'w-[min(var(--sidebar-width),85vw)]' : 'w-[88px]',
                    )}
                    aria-hidden
                />

                {! isDesktop && expanded ? (
                    <button
                        type="button"
                        aria-label={t('common.close')}
                        className="fixed inset-0 z-[61] bg-black/25"
                        onClick={closeMobileSidebar}
                    />
                ) : null}

                <div
                    className={cn(
                        'absolute inset-y-0 left-0 z-[62] overflow-visible',
                        'motion-reduce:transition-none transition-[width] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
                        expanded ? 'w-[min(var(--sidebar-width),85vw)]' : 'w-[88px]',
                    )}
                >
                    <aside id="app-sidebar" className="h-full w-full overflow-hidden shadow-sidebar">
                        <div className="flex h-full w-full min-w-0 flex-col">
                            <SidebarNav expanded={expanded} onNavigate={closeMobileSidebar} groups={groups} />
                        </div>
                    </aside>
                    <SidebarToggle
                        expanded={expanded}
                        onToggle={toggleSidebar}
                        label={t('common.toggle_sidebar')}
                    />
                </div>

                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <TopBar sidebarOpen={expanded} />
                    <main className="min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto px-3 pb-6 sm:px-6 lg:px-8">
                        {children}
                    </main>
                </div>
                <FloatingThemeSettingsButton />
                <Toaster />
                <FlashToast />
            </div>
        </TooltipProvider>
    );
}
