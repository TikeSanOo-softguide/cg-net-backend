import type { LucideIcon } from 'lucide-react';
import { BanknoteIcon, CalendarDaysIcon, TicketsIcon, UsersIcon } from 'lucide-react';

import { useTranslation } from '@/hooks/useTranslation';
import { formatTopUpAmount, type RedeemHistoryStats } from '@/lib/top-up-cards';
import { cn } from '@/lib/utils';

type StatItem = {
    key: keyof RedeemHistoryStats;
    labelKey: string;
    icon: LucideIcon;
    format: (stats: RedeemHistoryStats) => string;
};

const STATS: StatItem[] = [
    {
        key: 'total',
        labelKey: 'top_up_cards.stat_redeemed',
        icon: TicketsIcon,
        format: (stats) => stats.total.toLocaleString(),
    },
    {
        key: 'value',
        labelKey: 'top_up_cards.stat_value',
        icon: BanknoteIcon,
        format: (stats) => formatTopUpAmount(stats.value),
    },
    {
        key: 'month',
        labelKey: 'top_up_cards.stat_month',
        icon: CalendarDaysIcon,
        format: (stats) => stats.month.toLocaleString(),
    },
    {
        key: 'customers',
        labelKey: 'top_up_cards.stat_customers',
        icon: UsersIcon,
        format: (stats) => stats.customers.toLocaleString(),
    },
];

export function TopUpCardRedeemStats({ stats }: { stats: RedeemHistoryStats }) {
    const { t } = useTranslation();

    return (
        <div className="grid grid-cols-2 gap-2 xl:grid-cols-4">
            {STATS.map((stat) => {
                const Icon = stat.icon;

                return (
                    <article
                        key={stat.key}
                        className={cn(
                            'relative overflow-hidden rounded-[10px] border border-primary/15 bg-card p-3',
                            'shadow-[0_4px_12px_rgb(23_50_54/0.06)] transition-shadow hover:shadow-[0_6px_16px_rgb(23_50_54/0.10)]',
                            'dark:shadow-[0_4px_12px_rgb(0_0_0/0.24)]',
                        )}
                    >
                        <span className="absolute inset-y-0 left-0 w-[3px] bg-primary/70" aria-hidden />
                        <div className="flex items-start justify-between gap-2 pl-1.5">
                            <div className="min-w-0">
                                <p className="text-[11px] font-medium leading-none text-muted-foreground">
                                    {t(stat.labelKey)}
                                </p>
                                <p className="mt-1.5 truncate font-heading text-[18px] leading-none font-bold tabular-nums text-primary sm:text-[20px]">
                                    {stat.format(stats)}
                                </p>
                            </div>
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-primary/12 text-primary">
                                <Icon className="size-4" strokeWidth={1.85} />
                            </span>
                        </div>
                    </article>
                );
            })}
        </div>
    );
}
