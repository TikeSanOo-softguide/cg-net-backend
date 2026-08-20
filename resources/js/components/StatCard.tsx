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
};

export function StatCard({ title, value, icon: Icon, className, iconClassName, iconWrapperClassName }: StatCardProps) {
    return (
        <Card className={cn('gap-0 py-5', className)}>
            <CardContent className="px-5">
                <div
                    className={cn(
                        'flex size-10 items-center justify-center rounded-lg bg-muted text-foreground',
                        iconWrapperClassName,
                    )}
                >
                    <Icon className={cn('size-5', iconClassName)} strokeWidth={1.9} />
                </div>
                <p className="mt-4 text-[13px] font-medium leading-5 text-muted-foreground">{title}</p>
                <p className="mt-1 font-heading text-2xl font-bold tracking-tight text-card-foreground">{value}</p>
            </CardContent>
        </Card>
    );
}
