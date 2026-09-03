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
    innerRadius?: number;
    outerRadius?: number;
    percent?: number;
};

type TooltipEntry = {
    name?: string;
    value?: number;
    payload?: RegionChartSlice & { fill?: string; percent?: number };
};

type ChartTooltipProps = {
    active?: boolean;
    payload?: TooltipEntry[];
};

const RADIAN = Math.PI / 180;

const REGION_COLORS = ['#3B82F6', '#F97316', '#22C55E', '#EF4444', '#A855F7'];

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

function percentLabel({ cx = 0, cy = 0, midAngle = 0, innerRadius = 0, outerRadius = 0, percent = 0 }: SliceLabelProps) {
    if (percent < 0.06) {
        return null;
    }

    const radius = innerRadius + (outerRadius - innerRadius) * 0.52;
    const x = cx + radius * Math.cos(-midAngle * RADIAN);
    const y = cy + radius * Math.sin(-midAngle * RADIAN);

    return (
        <text x={x} y={y} fill="#fff" textAnchor="middle" dominantBaseline="central" className="text-[11px] font-bold tabular-nums">
            {(percent * 100).toFixed(1)}%
        </text>
    );
}

function RegionTooltip({ active, payload }: ChartTooltipProps) {
    if (! active || ! payload?.[0]) {
        return null;
    }

    const entry = payload[0];

    return (
        <div className="min-w-[10rem] rounded-xl border border-border/80 bg-card/95 px-3.5 py-2.5 shadow-lg backdrop-blur-sm">
            <p className="flex items-center gap-2 text-[13px] font-medium text-card-foreground">
                <span className="size-2 rounded-full" style={{ background: entry.payload?.fill }} />
                {entry.name}
            </p>
            <p className="mt-1 text-[12px] text-muted-foreground">
                <span className="font-semibold tabular-nums text-card-foreground">{Number(entry.value ?? 0).toLocaleString()}</span>
                {typeof entry.payload?.percent === 'number' ? (
                    <span className="ml-1.5 tabular-nums">({(entry.payload.percent * 100).toFixed(1)}%)</span>
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
        <Card className="h-full gap-0 overflow-hidden py-0 transition-shadow duration-200 hover:shadow-md">
            <CardHeader className="flex flex-row items-start gap-3 px-5 py-5 sm:px-6">
                <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300">
                    <MapPinIcon className="size-[22px]" strokeWidth={1.85} />
                </div>
                <div className="min-w-0">
                    <CardTitle className="text-lg font-semibold">{t('dashboard.region')}</CardTitle>
                    <CardDescription>{t('dashboard.installations_by_region')}</CardDescription>
                </div>
            </CardHeader>
            <CardContent className="px-5 pb-5 sm:px-6 sm:pb-6">
                {slices.length === 0 || total === 0 ? (
                    <div className="flex h-64 flex-col items-center justify-center gap-2 text-center sm:h-72">
                        <MapPinIcon className="size-8 text-muted-foreground/60" strokeWidth={1.5} />
                        <p className="max-w-[16rem] text-[13px] text-muted-foreground">{t('dashboard.no_region_data')}</p>
                    </div>
                ) : (
                    <div className="dashboard-chart flex h-64 flex-col sm:h-72">
                        <div className="min-h-0 flex-1 outline-none">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart accessibilityLayer={false} style={{ outline: 'none' }}>
                                    <Pie
                                        data={slices}
                                        dataKey="value"
                                        nameKey="name"
                                        cx="50%"
                                        cy="50%"
                                        innerRadius="54%"
                                        outerRadius="86%"
                                        startAngle={90}
                                        endAngle={-270}
                                        paddingAngle={2}
                                        stroke="none"
                                        label={percentLabel}
                                        labelLine={false}
                                        isAnimationActive={false}
                                    >
                                        {slices.map((slice) => (
                                            <Cell key={`${slice.id ?? 'other'}-${slice.name}`} fill={slice.fill} stroke="none" style={{ outline: 'none' }} />
                                        ))}
                                    </Pie>
                                    <Tooltip cursor={false} content={<RegionTooltip />} />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                        <ul className="flex shrink-0 flex-wrap justify-center gap-x-4 gap-y-2 px-1 py-2">
                            {slices.map((slice) => (
                                <li key={`${slice.id ?? 'other'}-${slice.name}`} className="flex max-w-full items-center gap-2 text-[12px] text-card-foreground">
                                    <span className="size-2.5 shrink-0 rounded-full" style={{ background: slice.fill }} />
                                    <span className="truncate">{slice.name}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
