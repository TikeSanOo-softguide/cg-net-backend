import type { LucideIcon } from 'lucide-react';

import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type StatItem = {
    key: string;
    title: string;
    value: string;
    icon: LucideIcon;
};

type StatCardProps = {
    items: StatItem[];
    className?: string;
};

export function StatCard({ items, className }: StatCardProps) {
    return (
        <Card className={cn('gap-0 overflow-hidden py-0', className)}>
            <ul className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5">
                {items.map((item, index) => {
                    const Icon = item.icon;

                    return (
                        <li
                            key={item.key}
                            className={cn(
                                'flex h-[100px] min-w-0 items-center gap-3 px-4 sm:px-5',
                                'border-b border-primary/10 last:border-b-0',
                                'sm:border-b-0',
                                index % 2 === 1 && 'sm:border-l sm:border-primary/10',
                                index > 0 && 'xl:border-l xl:border-primary/10',
                                index >= 2 && 'sm:border-t sm:border-primary/10 xl:border-t-0',
                            )}
                        >
                            <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-[8px] bg-primary/10 text-primary">
                                <Icon className="size-[18px]" strokeWidth={1.85} />
                            </span>
                            <div className="min-w-0">
                                <p className="truncate text-[12px] font-medium leading-5 text-muted-foreground">
                                    {item.title}
                                </p>
                                <p className="mt-1.5 truncate font-heading text-[20px] font-semibold leading-7 tracking-tight tabular-nums text-primary sm:text-[22px]">
                                    {item.value}
                                </p>
                            </div>
                        </li>
                    );
                })}
            </ul>
        </Card>
    );
}
