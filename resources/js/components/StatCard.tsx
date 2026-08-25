import type { LucideIcon } from 'lucide-react';

import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type StatCardProps = {
    title: string;
    value: string;
    icon: LucideIcon;
    className?: string;
    iconClassName?: string;
    iconWrapperClassName?: string;
    accentClassName?: string;
};

export function StatCard({
    title,
    value,
    icon: Icon,
    className,
    iconClassName,
    iconWrapperClassName,
    accentClassName,
}: StatCardProps) {
    return (
        <Card
            className={cn(
                'group relative gap-0 overflow-hidden py-5 transition-shadow duration-200 hover:shadow-md',
                className,
            )}
        >
            {accentClassName ? (
                <span className={cn('absolute inset-y-0 left-0 w-[3px]', accentClassName)} aria-hidden />
            ) : null}
            <CardContent className={cn('px-5', accentClassName && 'pl-6')}>
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-[13px] font-medium leading-5 text-muted-foreground">{title}</p>
                        <p className="mt-1.5 font-heading text-[22px] font-bold tracking-tight break-words text-card-foreground sm:text-2xl">
                            {value}
                        </p>
                    </div>
                    <div
                        className={cn(
                            'flex size-11 shrink-0 items-center justify-center rounded-xl bg-muted text-foreground transition-colors duration-200',
                            iconWrapperClassName,
                        )}
                    >
                        <Icon className={cn('size-[22px]', iconClassName)} strokeWidth={1.85} />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
