import { useEffect, useState, type ReactNode } from 'react';

import { SidebarNav, readSidebarExpanded, writeSidebarExpanded } from '@/components/layout/SidebarNav';
import { SidebarToggle } from '@/components/layout/SidebarToggle';
import { TopBar } from '@/components/layout/TopBar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type AppLayoutProps = {
    children: ReactNode;
};

export default function AppLayout({ children }: AppLayoutProps) {
    const { t } = useTranslation();
    const [expanded, setExpanded] = useState(true);

    useEffect(() => {
        setExpanded(readSidebarExpanded());
    }, []);

    const toggleSidebar = () => {
        setExpanded((current) => {
            const next = ! current;
            writeSidebarExpanded(next);

            return next;
        });
    };

    return (
        <TooltipProvider>
            <div className="flex h-dvh overflow-hidden bg-background">
                <aside
                    id="app-sidebar"
                    className={cn(
                        'relative z-[61] flex h-full min-h-0 shrink-0 flex-col overflow-visible bg-sidebar shadow-sidebar',
                        'motion-reduce:transition-none transition-[width] duration-300 ease-out',
                        expanded ? 'w-[min(var(--sidebar-width),85vw)]' : 'w-[88px]',
                    )}
                >
                    <SidebarNav expanded={expanded} />
                    <SidebarToggle
                        expanded={expanded}
                        onToggle={toggleSidebar}
                        label={t('common.toggle_sidebar')}
                    />
                </aside>

                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <TopBar sidebarOpen={expanded} />
                    <main className="min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto px-4 pb-6 sm:px-6 lg:px-8">
                        {children}
                    </main>
                </div>
            </div>
        </TooltipProvider>
    );
}
