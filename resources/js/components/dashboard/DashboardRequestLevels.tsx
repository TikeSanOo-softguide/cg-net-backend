import { ArrowDownIcon, ArrowUpIcon } from 'lucide-react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

export type RequestTypeSlice = {
    type: string;
    value: number;
    percent: number;
};

export type RequestTypeChart = {
    change: number | null;
    items: RequestTypeSlice[];
};

type DashboardRequestLevelsProps = {
    data: RequestTypeChart;
};

const BAR_COLORS = ['#2563EB', '#F97316', '#22D3EE', '#14B8A6', '#EAB308'];

function barColor(index: number): string {
    return BAR_COLORS[index % BAR_COLORS.length];
}

function trackColor(index: number): string {
    return `${barColor(index)}1f`;
}

export function DashboardRequestLevels({ data }: DashboardRequestLevelsProps) {
    const { t } = useTranslation();
    const change = data.change;
    const rising = (change ?? 0) >= 0;

    return (
        <Card className="h-full gap-0 overflow-hidden py-0 transition-shadow duration-200 hover:shadow-md">
            <CardHeader className="flex flex-row items-start justify-between gap-3 px-5 py-5 sm:px-6">
                <div className="min-w-0">
                    <CardTitle className="text-lg font-semibold">{t('dashboard.request_levels')}</CardTitle>
                    <CardDescription>{t('dashboard.request_levels_description')}</CardDescription>
                </div>
                {change !== null ? (
                    <span
                        className={cn(
                            'inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold tabular-nums',
                            rising
                                ? 'bg-teal-50 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300'
                                : 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300',
                        )}
                    >
                        {rising ? <ArrowUpIcon className="size-3" strokeWidth={2.4} /> : <ArrowDownIcon className="size-3" strokeWidth={2.4} />}
                        {change > 0 ? '+' : ''}
                        {change}%
                    </span>
                ) : null}
            </CardHeader>
            <CardContent className="flex flex-col gap-5 px-5 pb-5 sm:px-6 sm:pb-6">
                {data.items.map((item, index) => (
                    <div key={item.type} className="flex flex-col gap-2">
                        <div className="flex items-center justify-between gap-3 text-[13px] font-medium text-card-foreground">
                            <span className="min-w-0 truncate">{t(`type.${item.type}`)}</span>
                            <span className="shrink-0 tabular-nums">{item.percent}%</span>
                        </div>
                        <div className="h-2.5 overflow-hidden rounded-full" style={{ background: trackColor(index) }}>
                            <div
                                className="h-full rounded-full transition-[width] duration-500"
                                style={{ width: `${Math.min(item.percent, 100)}%`, background: barColor(index) }}
                            />
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
