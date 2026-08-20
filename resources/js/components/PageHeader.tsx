import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type PageHeaderProps = {
    eyebrow?: string;
    title: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
};

export function PageHeader({ eyebrow, title, description, actions, className }: PageHeaderProps) {
    return (
        <div className={cn('flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between', className)}>
            <div className="min-w-0">
                {eyebrow ? <p className="text-[13px] font-medium text-muted-foreground">{eyebrow}</p> : null}
                <h1 className="font-heading text-2xl font-bold tracking-tight text-foreground lg:text-[28px]">{title}</h1>
                {description ? <p className="mt-1 text-sm text-muted-foreground">{description}</p> : null}
            </div>
            {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
        </div>
    );
}
