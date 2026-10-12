import { useCallback, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Bar as RechartsBar,
    BarChart as RechartsBarChart,
    CartesianGrid,
    Cell,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type ChartHeaderProps = {
    icon?: ReactNode;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
};

function ChartHeader({ icon, title, description, action, className }: ChartHeaderProps) {
    return (
        <CardHeader
            className={
                className ?? 'flex flex-row items-start justify-between gap-3 space-y-0 px-4 pb-2 pt-3.5 sm:px-5'
            }
        >
            <div className="flex min-w-0 items-start gap-2.5">
                {icon ? (
                    <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                        {icon}
                    </span>
                ) : null}
                <div className="min-w-0">
                    <CardTitle className="text-[14px] font-semibold tracking-tight text-foreground sm:text-[15px]">
                        {title}
                    </CardTitle>
                    {description ? (
                        <CardDescription className="mt-0.5 text-[11px] leading-4">{description}</CardDescription>
                    ) : null}
                </div>
            </div>
            {action ? <div className="shrink-0">{action}</div> : null}
        </CardHeader>
    );
}

type TrendSeries<T extends { date: string }> = {
    dataKey: Exclude<keyof T, 'date'>;
    label: string;
    color: string;
    unit?: string;
    change?: number | null;
};

type TooltipEntry = {
    dataKey?: string;
    value?: number | string;
};

type TooltipMotionMode = 'hidden' | 'entering' | 'moving';

function useTooltipMotion() {
    const [mode, setMode] = useState<TooltipMotionMode>('hidden');

    const onActivate = useCallback(() => {
        setMode((currentMode) => (currentMode === 'hidden' ? 'entering' : currentMode));
    }, []);
    const onDeactivate = useCallback(() => setMode('hidden'), []);

    useEffect(() => {
        if (mode !== 'entering') {
            return;
        }

        const timeout = window.setTimeout(() => setMode('moving'), 200);
        return () => window.clearTimeout(timeout);
    }, [mode]);

    return { mode, onActivate, onDeactivate };
}

type TrendTooltipProps<T extends { date: string }> = {
    active?: boolean;
    label?: string;
    payload?: TooltipEntry[];
    series: TrendSeries<T>[];
    initialFade: boolean;
    onActivate: () => void;
    onDeactivate: () => void;
};

function formatValue(value: number, unit?: string): string {
    const formattedValue = value.toLocaleString();
    return unit ? `${formattedValue} ${unit}` : formattedValue;
}

function formatTrendDate(value: string): string {
    return /^\d{4}-\d{2}(?:-\d{2})?$/.test(value) ? value.slice(5) : value;
}

function TrendTooltip<T extends { date: string }>({
    active,
    label,
    payload,
    series,
    initialFade,
    onActivate,
    onDeactivate,
}: TrendTooltipProps<T>) {
    const wasActive = useRef(false);

    useEffect(() => {
        if (active && !wasActive.current) {
            wasActive.current = true;
            onActivate();
        } else if (!active && wasActive.current) {
            wasActive.current = false;
            onDeactivate();
        }
    }, [active, onActivate, onDeactivate]);

    if (!active || !payload?.length) {
        return null;
    }

    const visibleSeries = payload.flatMap((entry) => {
        const item = series.find((candidate) => String(candidate.dataKey) === String(entry.dataKey));
        return item ? [{ entry, item }] : [];
    });

    if (!visibleSeries.length) {
        return null;
    }

    return (
        <div
            className={`min-w-[10.5rem] rounded-[10px] border border-border/70 bg-card px-3 py-2.5 shadow-md ${
                initialFade ? 'animate-in fade-in-0 duration-200' : ''
            }`}
        >
            <p className="mb-1.5 text-[10px] font-medium text-muted-foreground">{label}</p>
            <div className="space-y-1.5">
                {visibleSeries.map(({ entry, item }) => (
                    <div key={String(item.dataKey)} className="flex items-center justify-between gap-4 text-[12px]">
                        <span className="flex items-center gap-1.5 text-muted-foreground">
                            <span className="size-1.5 rounded-full" style={{ background: item.color }} />
                            {item.label}
                        </span>
                        <span className="font-semibold tabular-nums text-foreground">
                            {formatValue(Number(entry.value ?? 0), item.unit)}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

type TrendChartProps<T extends { date: string }> = {
    data: T[];
    series: TrendSeries<T>[];
    title: string;
    description?: string;
    icon?: ReactNode;
    showLegend?: boolean;
    className?: string;
};

export function TrendChart<T extends { date: string }>({
    data,
    series,
    title,
    description,
    icon,
    showLegend = true,
    className,
}: TrendChartProps<T>) {
    const tooltipMotion = useTooltipMotion();
    const iconColor = series.length === 1 ? series[0].color : undefined;
    const singleSeriesChange = series.length === 1 ? series[0].change : null;

    return (
        <Card
            className={
                className ?? 'h-full gap-0 overflow-hidden border-border/70 py-0 shadow-[0_8px_24px_rgb(23_50_54/0.06)]'
            }
        >
            <ChartHeader
                title={title}
                description={description ?? 'Last 30 days'}
                icon={
                    icon ? (
                        <span
                            className="inline-flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-indigo-50 dark:bg-indigo-500/15"
                            style={iconColor ? { color: iconColor } : undefined}
                        >
                            {icon}
                        </span>
                    ) : null
                }
                action={
                    singleSeriesChange !== undefined && singleSeriesChange !== null ? (
                        <span className="inline-flex shrink-0 items-center rounded-[6px] bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold tabular-nums text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200">
                            {singleSeriesChange >= 0 ? '+' : ''}
                            {singleSeriesChange.toFixed(1)}%
                        </span>
                    ) : null
                }
                className="flex flex-row items-start justify-between gap-3 space-y-0 px-4 pb-2 pt-3.5 sm:px-5"
            />
            <CardContent className="px-4 pb-3.5 pt-0 sm:px-5">
                <div className="dashboard-chart flex h-48 w-full min-w-0 flex-col outline-none sm:h-52">
                    <div className="min-h-0 flex-1">
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart
                                key={JSON.stringify(data)}
                                accessibilityLayer={false}
                                data={data}
                                margin={{ top: 4, right: 18, left: -8, bottom: 0 }}
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
                                    tickFormatter={(value) => formatTrendDate(String(value))}
                                    dy={4}
                                />
                                <YAxis
                                    axisLine={false}
                                    tickLine={false}
                                    tick={{ fill: 'var(--muted-foreground)', fontSize: 10 }}
                                    width={36}
                                />
                                <Tooltip
                                    isAnimationActive={tooltipMotion.mode === 'moving'}
                                    animationDuration={200}
                                    content={
                                        <TrendTooltip
                                            series={series}
                                            initialFade={tooltipMotion.mode === 'entering'}
                                            onActivate={tooltipMotion.onActivate}
                                            onDeactivate={tooltipMotion.onDeactivate}
                                        />
                                    }
                                    cursor={{ stroke: 'var(--border)', strokeDasharray: '3 3' }}
                                />
                                {series.map((item) => (
                                    <Line
                                        key={String(item.dataKey)}
                                        type="monotone"
                                        dataKey={String(item.dataKey)}
                                        name={item.label}
                                        stroke={item.color}
                                        strokeWidth={2}
                                        dot={false}
                                        activeDot={{ r: 3.5, strokeWidth: 2, stroke: '#fff', fill: item.color }}
                                        isAnimationActive
                                        animationBegin={0}
                                        animationDuration={500}
                                        animationEasing="ease-out"
                                    />
                                ))}
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                    {showLegend ? (
                        <ul className="flex shrink-0 flex-wrap justify-center gap-x-3 gap-y-1 px-1 pt-1.5">
                            {series.map((item) => (
                                <li
                                    key={String(item.dataKey)}
                                    className="flex max-w-full items-center gap-1.5 text-[10px] text-muted-foreground"
                                >
                                    <span
                                        className="size-2 shrink-0 rounded-[2px]"
                                        style={{ backgroundColor: item.color }}
                                    />
                                    <span className="truncate text-foreground/80">{item.label}</span>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}

type PieSlice = {
    name: string;
    value: number;
    color: string;
};

type DonutTooltipEntry = {
    name?: string;
    value?: number | string;
};

type DonutTooltipProps = {
    active?: boolean;
    payload?: DonutTooltipEntry[];
    data: PieSlice[];
    initialFade: boolean;
    onActivate: () => void;
    onDeactivate: () => void;
};

function DonutTooltip({ active, payload, data, initialFade, onActivate, onDeactivate }: DonutTooltipProps) {
    const wasActive = useRef(false);
    const entry = payload?.[0];

    useEffect(() => {
        if (active && !wasActive.current) {
            wasActive.current = true;
            onActivate();
        } else if (!active && wasActive.current) {
            wasActive.current = false;
            onDeactivate();
        }
    }, [active, onActivate, onDeactivate]);

    if (!active || !entry) {
        return null;
    }

    const value = Number(entry.value ?? 0);
    const denominator = data.reduce((sum, slice) => sum + slice.value, 0);
    const slice = data.find((item) => item.name === entry.name);

    return (
        <div
            className={`min-w-[9rem] rounded-[10px] border border-border/70 bg-card px-3 py-2 shadow-md ${
                initialFade ? 'animate-in fade-in-0 duration-200' : ''
            }`}
        >
            <p className="flex items-center gap-1.5 text-[12px] font-medium text-foreground">
                <span className="size-2 rounded-[2px]" style={{ background: slice?.color }} />
                {entry.name}
            </p>
            <p className="mt-1 text-[11px] text-muted-foreground">
                <span className="font-semibold tabular-nums text-foreground">{value.toLocaleString()}</span>
                {denominator > 0 ? (
                    <span className="ml-1 tabular-nums">({((value / denominator) * 100).toFixed(1)}%)</span>
                ) : null}
            </p>
        </div>
    );
}

type DonutChartProps = {
    data: PieSlice[];
    title: string;
    description?: string;
    icon?: ReactNode;
    total?: number;
    totalLabel?: string;
    className?: string;
};

export function DonutChart({ data, title, description, icon, total, totalLabel, className }: DonutChartProps) {
    const tooltipMotion = useTooltipMotion();
    const hasData = data.some((item) => item.value > 0);
    const chartData = hasData ? data : [{ name: '', value: 1, color: 'var(--muted)' }];

    return (
        <Card
            className={
                className ?? 'h-full gap-0 overflow-hidden border-border/70 py-0 shadow-[0_8px_24px_rgb(23_50_54/0.06)]'
            }
        >
            <ChartHeader
                title={title}
                description={description}
                icon={
                    icon ? (
                        <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-pink-50 text-pink-600 dark:bg-pink-500/15 dark:text-pink-300">
                            {icon}
                        </span>
                    ) : null
                }
                className="flex flex-row items-start gap-2.5 space-y-0 px-4 pb-2 pt-3.5 sm:px-5"
            />
            <CardContent className="px-4 pb-3.5 pt-0 sm:px-5">
                {hasData ? (
                    <div className="dashboard-chart flex h-40 flex-col sm:h-44">
                        <div className="min-h-0 flex-1 outline-none">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart
                                    accessibilityLayer={false}
                                    margin={{ top: 4, right: 8, bottom: 0, left: 8 }}
                                    style={{ outline: 'none' }}
                                >
                                    <Pie
                                        key={JSON.stringify(chartData)}
                                        data={chartData}
                                        dataKey="value"
                                        nameKey="name"
                                        cx="50%"
                                        cy="48%"
                                        innerRadius="58%"
                                        outerRadius="78%"
                                        startAngle={90}
                                        endAngle={-270}
                                        paddingAngle={1.5}
                                        stroke="#fff"
                                        strokeWidth={2}
                                        isAnimationActive
                                        animationBegin={0}
                                        animationDuration={500}
                                        animationEasing="ease-out"
                                    >
                                        {chartData.map((slice, index) => (
                                            <Cell
                                                key={`${slice.name || 'empty'}-${index}`}
                                                fill={slice.color}
                                                stroke="#fff"
                                                strokeWidth={2}
                                                style={{ outline: 'none' }}
                                            />
                                        ))}
                                    </Pie>
                                    <Tooltip
                                        cursor={false}
                                        isAnimationActive={tooltipMotion.mode === 'moving'}
                                        animationDuration={200}
                                        content={
                                            <DonutTooltip
                                                data={data}
                                                initialFade={tooltipMotion.mode === 'entering'}
                                                onActivate={tooltipMotion.onActivate}
                                                onDeactivate={tooltipMotion.onDeactivate}
                                            />
                                        }
                                    />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                        <ul className="flex shrink-0 flex-wrap justify-center gap-x-3 gap-y-1 px-1 pt-1">
                            {data.map((slice) => (
                                <li
                                    key={slice.name}
                                    className="flex max-w-full items-center gap-1.5 text-[10px] text-muted-foreground"
                                >
                                    <span
                                        className="size-2 shrink-0 rounded-[2px]"
                                        style={{ background: slice.color }}
                                    />
                                    <span className="truncate text-foreground/80">{slice.name}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : (
                    <div className="flex h-40 flex-col items-center justify-center gap-1.5 text-center sm:h-44">
                        <p className="max-w-[14rem] text-[12px] text-muted-foreground">
                            {totalLabel ? `${totalLabel}: 0` : 'No data'}
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

type BarValue = {
    label: string;
    value: number;
    color?: string;
};

type BarListChartProps = {
    data: BarValue[];
    title: string;
    description?: string;
    icon?: ReactNode;
    total?: number;
    colorPalette?: string[];
    className?: string;
};

export function BarListChart({
    data,
    title,
    description,
    icon,
    total,
    colorPalette = ['#4F46E5', '#E11D48', '#0EA5E9', '#14B8A6', '#F59E0B'],
    className,
}: BarListChartProps) {
    const maxValue = Math.max(...data.map((item) => item.value), 1);

    return (
        <Card
            className={
                className ?? 'h-full gap-0 overflow-hidden border-border/70 py-0 shadow-[0_8px_24px_rgb(23_50_54/0.06)]'
            }
        >
            <ChartHeader
                title={title}
                description={description}
                icon={
                    icon ? (
                        <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-300">
                            {icon}
                        </span>
                    ) : null
                }
                action={
                    typeof total === 'number' ? (
                        <span className="inline-flex h-6 shrink-0 items-center rounded-[6px] bg-orange-50 px-2 text-[11px] font-bold tabular-nums text-orange-700 dark:bg-orange-500/15 dark:text-orange-300">
                            {total.toLocaleString()}
                        </span>
                    ) : null
                }
                className="flex flex-row items-start justify-between gap-3 space-y-0 px-4 pb-2 pt-3.5 sm:px-5"
            />
            <CardContent
                key={JSON.stringify(data)}
                className="mt-3 flex min-h-40 flex-col justify-center gap-2.5 px-4 pb-3.5 pt-0 sm:min-h-44 sm:px-5"
            >
                {data.length === 0 ? (
                    <p className="text-center text-[12px] text-muted-foreground">No data available</p>
                ) : (
                    data.map((item, index) => {
                        const barColor = item.color ?? colorPalette[index % colorPalette.length];
                        const width = Math.max((item.value / maxValue) * 100, 6);

                        return (
                            <div key={item.label} className="flex flex-col gap-1">
                                <div className="flex items-center justify-between gap-2 text-[11px]">
                                    <span className="min-w-0 flex-1 whitespace-normal break-words font-medium text-foreground">
                                        {item.label}
                                    </span>
                                    <span
                                        className="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-[5px] px-1.5 text-[10px] font-bold tabular-nums text-white"
                                        style={{ background: barColor }}
                                    >
                                        {item.value.toLocaleString()}
                                    </span>
                                </div>
                                <div
                                    className="h-1.5 overflow-hidden rounded-full"
                                    style={{ background: `color-mix(in srgb, ${barColor} 13%, transparent)` }}
                                >
                                    <div
                                        className="chart-bar-grow h-full rounded-full transition-[width] duration-500"
                                        style={{
                                            width: `${width}%`,
                                            background: barColor,
                                            animationDelay: `${index * 50}ms`,
                                        }}
                                    />
                                </div>
                            </div>
                        );
                    })
                )}
            </CardContent>
        </Card>
    );
}

export { RechartsBar as Bar, RechartsBarChart as BarChart, Pie as RechartsPie, PieChart as RechartsPieChart };
