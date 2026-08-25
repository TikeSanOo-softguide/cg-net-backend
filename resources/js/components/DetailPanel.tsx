import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type DetailPanelProps = {
    title: string;
    description?: string;
    icon: LucideIcon;
    actions?: ReactNode;
    footer?: ReactNode;
    children: ReactNode;
    className?: string;
};

export function DetailPanel({
    title,
    description,
    icon: Icon,
    actions,
    footer,
    children,
    className,
}: DetailPanelProps) {
    return (
        <div
            className={cn(
                'flex w-full flex-col overflow-hidden border border-border/80 bg-[#FFFFFF] shadow-[0_8px_24px_rgb(23_50_54/0.08),0_20px_48px_rgb(23_50_54/0.12)] dark:bg-card dark:shadow-[0_8px_24px_rgb(0_0_0/0.28),0_20px_48px_rgb(0_0_0/0.32)]',
                'rounded-none sm:rounded-[12px]',
                className,
            )}
        >
            <div className="flex shrink-0 items-start gap-3 border-b border-border/70 px-4 py-4 sm:px-5">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-[8px] bg-primary/10 text-primary">
                    <Icon className="size-[18px]" strokeWidth={1.85} />
                </div>
                <div className="min-w-0 flex-1">
                    <h2 className="text-[15px] leading-snug font-semibold text-foreground">{title}</h2>
                    {description ? (
                        <p className="mt-1 text-[13px] leading-5 text-muted-foreground">{description}</p>
                    ) : null}
                </div>
                {actions ? <div className="flex shrink-0 items-center gap-2">{actions}</div> : null}
            </div>
            <div className="px-4 py-4 sm:px-5 sm:py-5">
                <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">{children}</div>
            </div>
            {footer ? (
                <div className="flex w-full shrink-0 items-center justify-center gap-2 border-t border-border/70 px-4 py-3 sm:px-5 sm:py-4">
                    {footer}
                </div>
            ) : null}
        </div>
    );
}
