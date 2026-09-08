import type { CSSProperties } from 'react';
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
        <ul className={cn('grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5', className)}>
            {items.map((item) => {
                const Icon = item.icon;

                return (
                    <li key={item.key} className="min-w-0">
                        <Card className="flex h-[100px] flex-row items-center gap-3 overflow-hidden rounded-[12px] border border-border/70 px-4 py-0 sm:px-5">
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
                        </Card>
                    </li>
                );
            })}
        </ul>
    );
}
