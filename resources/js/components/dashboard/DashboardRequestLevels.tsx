import { ClipboardListIcon } from 'lucide-react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/useTranslation';

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

const BAR_COLORS = ['#4F46E5', '#E11D48', '#0EA5E9', '#14B8A6', '#F59E0B'];

function barColor(index: number): string {
    return BAR_COLORS[index % BAR_COLORS.length];
}

function trackColor(index: number): string {
    return `${barColor(index)}22`;
}

export function DashboardRequestLevels({ data }: DashboardRequestLevelsProps) {
    const { t } = useTranslation();
    const total = data.items.reduce((sum, item) => sum + item.value, 0);

    return (
        <Card className="h-full gap-0 overflow-hidden border-border/70 py-0 shadow-[0_8px_24px_rgb(23_50_54/0.06)]">
            <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0 px-4 pb-2 pt-3.5 sm:px-5">
                <div className="flex min-w-0 items-start gap-2.5">
                    <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-300">
                        <ClipboardListIcon className="size-3.5" strokeWidth={1.85} />
                    </span>
                    <div className="min-w-0">
                        <CardTitle className="text-[14px] font-semibold tracking-tight text-foreground sm:text-[15px]">
                            {t('dashboard.request_levels')}
                        </CardTitle>
                        <CardDescription className="mt-0.5 text-[11px] leading-4">
                            {t('dashboard.request_levels_duration')}
                        </CardDescription>
                    </div>
                </div>
                <span className="inline-flex h-6 shrink-0 items-center rounded-[6px] bg-orange-50 px-2 text-[11px] font-bold tabular-nums text-orange-700 dark:bg-orange-500/15 dark:text-orange-300">
                    {total.toLocaleString()}
                </span>
            </CardHeader>
            <CardContent className="flex h-40 flex-col justify-center gap-2.5 px-4 pb-3.5 pt-0 sm:h-44 sm:px-5">
                {data.items.length === 0 ? (
                    <p className="text-center text-[12px] text-muted-foreground">{t('dashboard.no_request_data')}</p>
                ) : (
                    data.items.map((item, index) => (
                        <div key={item.type} className="flex flex-col gap-1">
                            <div className="flex items-center justify-between gap-2 text-[11px]">
                                <span className="min-w-0 truncate font-medium text-foreground">{t(`type.${item.type}`)}</span>
                                <span
                                    className="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-[5px] px-1.5 text-[10px] font-bold tabular-nums text-white"
                                    style={{ background: barColor(index) }}
                                >
                                    {item.value.toLocaleString()}
                                </span>
                            </div>
                            <div className="h-1.5 overflow-hidden rounded-full" style={{ background: trackColor(index) }}>
                                <div
                                    className="h-full rounded-full transition-[width] duration-500"
                                    style={{ width: `${Math.min(item.percent, 100)}%`, background: barColor(index) }}
                                />
                            </div>
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}
