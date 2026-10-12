import { MapPinIcon } from 'lucide-react';

import { DonutChart } from '@/components/ui/charts';
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

export function DashboardRegionChart({ data }: DashboardRegionChartProps) {
    const { t, locale } = useTranslation();
    const otherLabel = t('dashboard.other');

    const slices = data.map((slice, index) => ({
        name: sliceName(slice, locale, otherLabel),
        value: slice.value,
        color: REGION_COLORS[index % REGION_COLORS.length],
    }));

    return (
        <DonutChart
            data={slices}
            title={t('dashboard.region')}
            description={`${t('dashboard.installations_by_region')} · ${t('dashboard.last_30_days')}`}
            icon={<MapPinIcon className="size-3.5" strokeWidth={1.85} />}
            total={data.reduce((sum, slice) => sum + slice.value, 0)}
            totalLabel={t('dashboard.region')}
        />
    );
}
