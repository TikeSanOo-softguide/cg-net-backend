import { TrendingUpIcon } from 'lucide-react';
import { Area, Bar, CartesianGrid, ComposedChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/useTranslation';

export type TrendPoint = {
    date: string;
    revenue: number;
    signups: number;
};

type DashboardTrendChartProps = {
    data: TrendPoint[];
    isMobile: boolean;
};

type TooltipEntry = {
    dataKey?: string | number;
    value?: number;
    color?: string;
};

type ChartTooltipProps = {
    active?: boolean;
    label?: string;
    payload?: TooltipEntry[];
};

function formatRevenue(value: number): string {
    return `${value.toLocaleString()} MMK`;
}

function TrendTooltip({ active, label, payload }: ChartTooltipProps) {
    const { t } = useTranslation();

    if (! active || ! payload?.length) {
        return null;
    }

    return (
        <div className="min-w-[11.5rem] rounded-xl border border-border/80 bg-card/95 px-3.5 py-3 shadow-lg backdrop-blur-sm">
            <p className="mb-2 text-[11px] font-medium tracking-wide text-muted-foreground">{label}</p>
            <ul className="flex flex-col gap-1.5">
                {payload.map((entry) => {
                    const isRevenue = entry.dataKey === 'revenue';

                    return (
                        <li key={String(entry.dataKey)} className="flex items-center justify-between gap-6 text-[13px]">
                            <span className="flex items-center gap-2 text-muted-foreground">
                                <span className="size-2 rounded-full" style={{ background: entry.color }} />
                                {isRevenue ? t('dashboard.revenue') : t('dashboard.new_signups')}
                            </span>
                            <span className="font-semibold tabular-nums text-card-foreground">
                                {isRevenue ? formatRevenue(Number(entry.value ?? 0)) : Number(entry.value ?? 0).toLocaleString()}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

export function DashboardTrendChart({ data, isMobile }: DashboardTrendChartProps) {
    const { t } = useTranslation();

    return (
        <Card className="h-full gap-0 overflow-hidden py-0 transition-shadow duration-200 hover:shadow-md">
            <CardHeader className="flex flex-row items-start justify-between gap-3 px-5 py-5 sm:px-6">
                <div className="flex min-w-0 items-start gap-3">
                    <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                        <TrendingUpIcon className="size-[22px]" strokeWidth={1.85} />
                    </div>
                    <div className="min-w-0">
                        <CardTitle className="text-lg font-semibold">{t('dashboard.last_30_days')}</CardTitle>
                        <CardDescription>
                            {t('dashboard.revenue')}
                            {! isMobile ? ` · ${t('dashboard.new_signups')}` : null}
                        </CardDescription>
                    </div>
                </div>
                <ul className="hidden shrink-0 items-center gap-2 sm:flex">
                    <li className="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-medium text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">
                        <span className="size-1.5 rounded-full bg-sky-500" />
                        {t('dashboard.revenue')}
                    </li>
                    <li className="inline-flex items-center gap-1.5 rounded-full bg-orange-50 px-2.5 py-1 text-[11px] font-medium text-orange-800 dark:bg-orange-500/15 dark:text-orange-200">
                        <span className="size-1.5 rounded-full bg-orange-500" />
                        {t('dashboard.new_signups')}
                    </li>
                </ul>
            </CardHeader>
            <CardContent className="px-5 pb-5 sm:px-6 sm:pb-6">
                <ul className="mb-4 flex flex-wrap gap-2 sm:hidden">
                    <li className="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-medium text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">
                        <span className="size-1.5 rounded-full bg-sky-500" />
                        {t('dashboard.revenue')}
                    </li>
                    <li className="inline-flex items-center gap-1.5 rounded-full bg-orange-50 px-2.5 py-1 text-[11px] font-medium text-orange-800 dark:bg-orange-500/15 dark:text-orange-200">
                        <span className="size-1.5 rounded-full bg-orange-500" />
                        {t('dashboard.new_signups')}
                    </li>
                </ul>
                <div className="dashboard-chart h-64 w-full min-w-0 outline-none sm:h-72">
                    <ResponsiveContainer width="100%" height="100%">
                        <ComposedChart accessibilityLayer={false} data={data} margin={{ top: 8, right: 8, left: 0, bottom: 4 }} barCategoryGap="32%" style={{ outline: 'none' }}>
                            <defs>
                                <linearGradient id="dashboard-revenue-fill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor="#0ea5e9" stopOpacity={0.38} />
                                    <stop offset="100%" stopColor="#0ea5e9" stopOpacity={0} />
                                </linearGradient>
                                <linearGradient id="dashboard-signup-fill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor="#f97316" stopOpacity={1} />
                                    <stop offset="100%" stopColor="#fb923c" stopOpacity={0.7} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid stroke="var(--border)" strokeDasharray="0" vertical={false} />
                            <XAxis
                                dataKey="date"
                                axisLine={false}
                                tickLine={false}
                                tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                                tickFormatter={(value) => String(value).slice(5)}
                                dy={6}
                            />
                            <YAxis
                                yAxisId="left"
                                axisLine={false}
                                tickLine={false}
                                tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                                width={44}
                            />
                            <YAxis
                                yAxisId="right"
                                orientation="right"
                                hide={isMobile}
                                axisLine={false}
                                tickLine={false}
                                tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                                width={28}
                            />
                            <Tooltip content={<TrendTooltip />} cursor={false} />
                            <Area
                                yAxisId="left"
                                type="monotone"
                                dataKey="revenue"
                                name="revenue"
                                stroke="#0284c7"
                                strokeWidth={2.5}
                                fill="url(#dashboard-revenue-fill)"
                                dot={false}
                                activeDot={{ r: 5, strokeWidth: 2, stroke: '#fff', fill: '#0284c7' }}
                            />
                            <Bar
                                yAxisId="right"
                                dataKey="signups"
                                name="signups"
                                fill="url(#dashboard-signup-fill)"
                                radius={[6, 6, 0, 0]}
                                maxBarSize={18}
                            />
                        </ComposedChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    );
}
