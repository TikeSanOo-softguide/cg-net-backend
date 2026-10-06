import { TrendingUpIcon } from 'lucide-react';
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/useTranslation';

export type TrendPoint = {
    date: string;
    topup_usage: number;
    signups: number;
    ftth_bill_payments: number;
    wifi_package_orders: number;
};

type DashboardTrendChartProps = {
    data: TrendPoint[];
    dataKey: keyof Omit<TrendPoint, 'date'>;
    labelKey: string;
    color: string;
    unit?: string;
    change?: number | null;
};

type ChartTooltipProps = {
    active?: boolean;
    label?: string;
    payload?: { value?: number }[];
};

function formatValue(value: number, unit?: string): string {
    const formattedValue = value.toLocaleString();

    return unit ? `${formattedValue} ${unit}` : formattedValue;
}

function TrendTooltip({
    active,
    label,
    payload,
    labelKey,
    color,
    unit,
}: ChartTooltipProps & Pick<DashboardTrendChartProps, 'labelKey' | 'color' | 'unit'>) {
    const { t } = useTranslation();

    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="min-w-[10.5rem] rounded-[10px] border border-border/70 bg-card px-3 py-2.5 shadow-md">
            <p className="mb-1.5 text-[10px] font-medium text-muted-foreground">{label}</p>
            <div className="flex items-center justify-between gap-4 text-[12px]">
                <span className="flex items-center gap-1.5 text-muted-foreground">
                    <span className="size-1.5 rounded-full" style={{ background: color }} />
                    {t(labelKey)}
                </span>
                <span className="font-semibold tabular-nums text-foreground">
                    {formatValue(Number(payload[0].value ?? 0), unit)}
                </span>
            </div>
        </div>
    );
}

export function DashboardTrendChart({ data, dataKey, labelKey, color, unit, change = null }: DashboardTrendChartProps) {
    const { t } = useTranslation();

    return (
        <Card className="h-full gap-0 overflow-hidden border-border/70 py-0 shadow-[0_8px_24px_rgb(23_50_54/0.06)]">
            <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0 px-4 pb-2 pt-3.5 sm:px-5">
                <div className="flex min-w-0 items-start gap-2.5">
                    <span
                        className="inline-flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-indigo-50 dark:bg-indigo-500/15"
                        style={{ color }}
                    >
                        <TrendingUpIcon className="size-3.5" strokeWidth={1.85} />
                    </span>
                    <div className="min-w-0">
                        <CardTitle className="text-[14px] font-semibold tracking-tight text-foreground sm:text-[15px]">
                            {t(labelKey)}
                        </CardTitle>
                        <CardDescription className="mt-0.5 text-[11px] leading-4">
                            {t('dashboard.last_30_days')}
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
                <div className="dashboard-chart h-40 w-full min-w-0 outline-none sm:h-44">
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart
                            accessibilityLayer={false}
                            data={data}
                            margin={{ top: 4, right: 4, left: -8, bottom: 0 }}
                            style={{ outline: 'none' }}
                        >
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
                                axisLine={false}
                                tickLine={false}
                                tick={{ fill: 'var(--muted-foreground)', fontSize: 10 }}
                                width={36}
                            />
                            <Tooltip
                                content={<TrendTooltip labelKey={labelKey} color={color} unit={unit} />}
                                cursor={{ stroke: 'var(--border)', strokeDasharray: '3 3' }}
                            />
                            <Line
                                type="monotone"
                                dataKey={dataKey}
                                stroke={color}
                                strokeWidth={2}
                                dot={false}
                                activeDot={{
                                    r: 3.5,
                                    strokeWidth: 2,
                                    stroke: '#fff',
                                    fill: color,
                                }}
                            />
                        </LineChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    );
}
