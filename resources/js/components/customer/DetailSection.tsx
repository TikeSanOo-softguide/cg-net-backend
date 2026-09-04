import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type DetailSectionProps = {
    icon: LucideIcon;
    title: string;
    description?: string;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
};

/** Bordered detail block with compact icon header — wraps tables on App User show. */
export function DetailSection({
    icon: Icon,
    title,
    description,
    actions,
    children,
    className,
}: DetailSectionProps) {
    return (
        <section
            className={cn(
                'flex min-w-0 flex-col overflow-hidden border border-border/80 bg-[#FFFFFF]',
                'shadow-[0_6px_18px_rgb(23_50_54/0.06),0_12px_28px_rgb(23_50_54/0.08)]',
                'rounded-none sm:rounded-[12px]',
                'dark:bg-card dark:shadow-[0_6px_18px_rgb(0_0_0/0.22)]',
                className,
            )}
        >
            <div className="flex shrink-0 items-center gap-2.5 border-b border-border/70 px-3 py-2.5 sm:px-4">
                <span className="inline-flex size-7 shrink-0 items-center justify-center rounded-[6px] bg-primary/10 text-primary">
                    <Icon className="size-3.5" strokeWidth={1.85} />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="truncate text-[13px] font-semibold leading-tight text-foreground">{title}</h2>
                    {description ? (
                        <p className="truncate text-[11px] leading-4 text-muted-foreground">{description}</p>
                    ) : null}
                </div>
                {actions ? <div className="flex shrink-0 items-center gap-1.5">{actions}</div> : null}
            </div>
            <div className="min-w-0 p-2 sm:p-3">{children}</div>
        </section>
    );
}
