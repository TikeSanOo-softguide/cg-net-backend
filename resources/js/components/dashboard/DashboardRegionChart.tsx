import { MapPinIcon } from 'lucide-react';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/useTranslation';
import type { SupportedLocale } from '@/types';

export type RegionChartSlice = {
    id: number | null;
    name_en: string;
    name_zh: string;
    name_my: string;
    value: number;
};

type DashboardRegionChartProps = {
    data: RegionChartSlice[];
};

type SliceLabelProps = {
    cx?: number;
    cy?: number;
    midAngle?: number;
    outerRadius?: number;
    percent?: number;
};

type TooltipEntry = {
    name?: string;
    value?: number;
    payload?: RegionChartSlice & { fill?: string; percent?: number; name?: string };
};

type ChartTooltipProps = {
    active?: boolean;
    payload?: TooltipEntry[];
};

const RADIAN = Math.PI / 180;

/** Matches the reference donut palette (compact market-share style). */
const REGION_COLORS = ['#EC4899', '#3B82F6', '#F97316', '#22C55E', '#8B5CF6', '#94A3B8'];

function sliceName(slice: RegionChartSlice, locale: SupportedLocale, otherLabel: string): string {
    if (slice.id === null) {
        return otherLabel;
    }

    if (locale === 'my') {
        return slice.name_my || slice.name_en;
    }

    if (locale === 'zh') {
        return slice.name_zh || slice.name_en;
    }

    return slice.name_en;
}

function outsidePercentLabel({ cx = 0, cy = 0, midAngle = 0, outerRadius = 0, percent = 0 }: SliceLabelProps) {
    if (percent < 0.04) {
        return null;
    }

    const radius = outerRadius + 14;
    const x = cx + radius * Math.cos(-midAngle * RADIAN);
    const y = cy + radius * Math.sin(-midAngle * RADIAN);

    return (
        <text
            x={x}
            y={y}
            fill="var(--muted-foreground)"
            textAnchor={x > cx ? 'start' : 'end'}
            dominantBaseline="central"
            className="text-[10px] font-semibold tabular-nums"
        >
            {(percent * 100).toFixed(0)}%
        </text>
    );
}

function RegionTooltip({ active, payload }: ChartTooltipProps) {
    if (! active || ! payload?.[0]) {
        return null;
    }

    const entry = payload[0];

    return (
        <div className="min-w-[9rem] rounded-[10px] border border-border/70 bg-card px-3 py-2 shadow-md">
            <p className="flex items-center gap-1.5 text-[12px] font-medium text-foreground">
                <span className="size-2 rounded-[2px]" style={{ background: entry.payload?.fill }} />
                {entry.name}
            </p>
            <p className="mt-1 text-[11px] text-muted-foreground">
                <span className="font-semibold tabular-nums text-foreground">{Number(entry.value ?? 0).toLocaleString()}</span>
                {typeof entry.payload?.percent === 'number' ? (
                    <span className="ml-1 tabular-nums">({(entry.payload.percent * 100).toFixed(1)}%)</span>
                ) : null}
            </p>
        </div>
    );
}

export function DashboardRegionChart({ data }: DashboardRegionChartProps) {
    const { t, locale } = useTranslation();
    const otherLabel = t('dashboard.other');
    const total = data.reduce((sum, slice) => sum + slice.value, 0);
    const slices = data.map((slice, index) => ({
        ...slice,
        name: sliceName(slice, locale, otherLabel),
        fill: REGION_COLORS[index % REGION_COLORS.length],
    }));

    return (
        <Card className="h-full gap-0 overflow-hidden border-border/70 py-0 shadow-[0_8px_24px_rgb(23_50_54/0.06)]">
            <CardHeader className="flex flex-row items-start gap-2.5 space-y-0 px-4 pb-2 pt-3.5 sm:px-5">
                <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-pink-50 text-pink-600 dark:bg-pink-500/15 dark:text-pink-300">
                    <MapPinIcon className="size-3.5" strokeWidth={1.85} />
                </span>
                <div className="min-w-0">
                    <CardTitle className="text-[14px] font-semibold tracking-tight text-foreground sm:text-[15px]">
                        {t('dashboard.region')}
                    </CardTitle>
                    <CardDescription className="mt-0.5 text-[11px] leading-4">
                        {t('dashboard.installations_by_region')}
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent className="px-4 pb-3.5 pt-0 sm:px-5">
                {slices.length === 0 || total === 0 ? (
                    <div className="flex h-40 flex-col items-center justify-center gap-1.5 text-center sm:h-44">
                        <p className="max-w-[14rem] text-[12px] text-muted-foreground">{t('dashboard.no_region_data')}</p>
                    </div>
                ) : (
                    <div className="dashboard-chart flex h-40 flex-col sm:h-44">
                        <div className="min-h-0 flex-1 outline-none">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart accessibilityLayer={false} margin={{ top: 4, right: 8, bottom: 0, left: 8 }} style={{ outline: 'none' }}>
                                    <Pie
                                        data={slices}
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
                                        label={outsidePercentLabel}
                                        labelLine={false}
                                        isAnimationActive={false}
                                    >
                                        {slices.map((slice) => (
                                            <Cell
                                                key={`${slice.id ?? 'other'}-${slice.name}`}
                                                fill={slice.fill}
                                                stroke="#fff"
                                                strokeWidth={2}
                                                style={{ outline: 'none' }}
                                            />
                                        ))}
                                    </Pie>
                                    <Tooltip cursor={false} content={<RegionTooltip />} />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                        <ul className="flex shrink-0 flex-wrap justify-center gap-x-3 gap-y-1 px-1 pt-1">
                            {slices.map((slice) => (
                                <li
                                    key={`${slice.id ?? 'other'}-${slice.name}`}
                                    className="flex max-w-full items-center gap-1.5 text-[10px] text-muted-foreground"
                                >
                                    <span className="size-2 shrink-0 rounded-[2px]" style={{ background: slice.fill }} />
                                    <span className="truncate text-foreground/80">{slice.name}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
