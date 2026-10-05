import { TrendingUpIcon } from 'lucide-react';
import { Area, CartesianGrid, ComposedChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/useTranslation';

export type TrendPoint = {
    date: string;
    topup_usage: number;
    signups: number;
};

type DashboardTrendChartProps = {
    data: TrendPoint[];
    isMobile: boolean;
    change: number | null;
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

const TOP_UP_USAGE = '#4F46E5';
const SIGNUPS = '#E11D48';

function formatPoints(value: number, pointsLabel: string): string {
    return `${value.toLocaleString()} ${pointsLabel}`;
}

function TrendTooltip({ active, label, payload }: ChartTooltipProps) {
    const { t } = useTranslation();

    if (! active || ! payload?.length) {
        return null;
    }

    return (
        <div className="min-w-[10.5rem] rounded-[10px] border border-border/70 bg-card px-3 py-2.5 shadow-md">
            <p className="mb-1.5 text-[10px] font-medium text-muted-foreground">{label}</p>
            <ul className="flex flex-col gap-1">
                {payload.map((entry) => {
                    const isTopUpUsage = entry.dataKey === 'topup_usage';

                    return (
                        <li key={String(entry.dataKey)} className="flex items-center justify-between gap-4 text-[12px]">
                            <span className="flex items-center gap-1.5 text-muted-foreground">
                                <span className="size-1.5 rounded-full" style={{ background: entry.color }} />
                                {isTopUpUsage ? t('dashboard.top_up_usage') : t('dashboard.new_signups')}
                            </span>
                            <span className="font-semibold tabular-nums text-foreground">
                                {isTopUpUsage
                                    ? formatPoints(Number(entry.value ?? 0), t('dashboard.points'))
                                    : Number(entry.value ?? 0).toLocaleString()}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

export function DashboardTrendChart({ data, isMobile, change }: DashboardTrendChartProps) {
    const { t } = useTranslation();

    return (
        <Card className="h-full gap-0 overflow-hidden border-border/70 py-0 shadow-[0_8px_24px_rgb(23_50_54/0.06)]">
            <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0 px-4 pb-2 pt-3.5 sm:px-5">
                <div className="flex min-w-0 items-start gap-2.5">
                    <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                        <TrendingUpIcon className="size-3.5" strokeWidth={1.85} />
                    </span>
                    <div className="min-w-0">
                        <CardTitle className="text-[14px] font-semibold tracking-tight text-foreground sm:text-[15px]">
                            {t('dashboard.last_30_days')}
                        </CardTitle>
                        <CardDescription className="mt-0.5 text-[11px] leading-4">
                            {t('dashboard.top_up_usage')}
                            {! isMobile ? ` · ${t('dashboard.new_signups')}` : null}
                        </CardDescription>
                    </div>
                </div>
                {change !== null ? (
                    <span className="inline-flex shrink-0 items-center rounded-[6px] bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold tabular-nums text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200">
                        {change >= 0 ? '+' : ''}
                        {change.toFixed(1)}%
                    </span>
                ) : null}
            </CardHeader>
            <CardContent className="px-4 pb-3.5 pt-0 sm:px-5">
                <ul className="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                    <li className="inline-flex items-center gap-1.5 text-[10px] font-medium text-muted-foreground">
                        <span className="h-[2px] w-3 rounded-full" style={{ background: TOP_UP_USAGE }} />
                        <span className="size-1.5 rotate-45" style={{ background: TOP_UP_USAGE }} />
                        {t('dashboard.top_up_usage')}
                    </li>
                    <li className="inline-flex items-center gap-1.5 text-[10px] font-medium text-muted-foreground">
                        <span className="h-[2px] w-3 rounded-full" style={{ background: SIGNUPS }} />
                        <span className="size-1.5 rounded-[1px]" style={{ background: SIGNUPS }} />
                        {t('dashboard.new_signups')}
                    </li>
                </ul>
                <div className="dashboard-chart h-40 w-full min-w-0 outline-none sm:h-44">
                    <ResponsiveContainer width="100%" height="100%">
                        <ComposedChart
                            accessibilityLayer={false}
                            data={data}
                            margin={{ top: 4, right: 4, left: -8, bottom: 0 }}
                            style={{ outline: 'none' }}
                        >
                            <defs>
                                <linearGradient id="dashboard-topup-fill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor={TOP_UP_USAGE} stopOpacity={0.22} />
                                    <stop offset="100%" stopColor={TOP_UP_USAGE} stopOpacity={0} />
                                </linearGradient>
                                <linearGradient id="dashboard-signup-fill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor={SIGNUPS} stopOpacity={0.14} />
                                    <stop offset="100%" stopColor={SIGNUPS} stopOpacity={0} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid stroke="var(--border)" strokeOpacity={0.7} vertical={false} />
                            <XAxis
                                dataKey="date"
                                axisLine={false}
                                tickLine={false}
                                interval="preserveStartEnd"
                                minTickGap={28}
                                tick={{ fill: 'var(--muted-foreground)', fontSize: 10 }}
                                tickFormatter={(value) => String(value).slice(5)}
                                dy={4}
                            />
                            <YAxis
                                yAxisId="left"
                                axisLine={false}
                                tickLine={false}
                                tick={{ fill: 'var(--muted-foreground)', fontSize: 10 }}
                                width={36}
                            />
                            {! isMobile ? (
                                <YAxis
                                    yAxisId="right"
                                    orientation="right"
                                    axisLine={false}
                                    tickLine={false}
                                    tick={{ fill: 'var(--muted-foreground)', fontSize: 10 }}
                                    width={24}
                                />
                            ) : null}
                            <Tooltip content={<TrendTooltip />} cursor={{ stroke: 'var(--border)', strokeDasharray: '3 3' }} />
                            <Area
                                yAxisId="left"
                                type="monotone"
                                dataKey="topup_usage"
                                name="topup_usage"
                                stroke={TOP_UP_USAGE}
                                strokeWidth={2}
                                fill="url(#dashboard-topup-fill)"
                                dot={false}
                                activeDot={{ r: 3.5, strokeWidth: 2, stroke: '#fff', fill: TOP_UP_USAGE }}
                            />
                            <Area
                                yAxisId={isMobile ? 'left' : 'right'}
                                type="monotone"
                                dataKey="signups"
                                name="signups"
                                stroke={SIGNUPS}
                                strokeWidth={2}
                                fill="url(#dashboard-signup-fill)"
                                dot={false}
                                activeDot={{ r: 3.5, strokeWidth: 2, stroke: '#fff', fill: SIGNUPS }}
                            />
                        </ComposedChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    );
}
