import type { ReactNode } from 'react';
import { usePage } from '@inertiajs/react';

import { useTranslation } from '@/hooks/useTranslation';
import { resolvePageDescription, resolvePageTitle } from '@/lib/navigation';
import { cn } from '@/lib/utils';

type PageHeaderProps = {
    title?: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
};

export function PageHeader({ title, description, actions, className }: PageHeaderProps) {
    const { t } = useTranslation();
    const page = usePage();
    const path = page.url.split('?')[0];
    const displayTitle = resolvePageTitle(t, path, title);
    const displayDescription = resolvePageDescription(t, path, description);

    return (
        <div className={cn('flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between', className)}>
            <div className="min-w-0">
                <h1 className="font-heading text-base font-semibold tracking-tight text-primary/70 sm:text-lg">{displayTitle}</h1>
                {displayDescription ? <p className="mt-0.5 text-[13px] text-muted-foreground">{displayDescription}</p> : null}
            </div>
            {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
        </div>
    );
}
