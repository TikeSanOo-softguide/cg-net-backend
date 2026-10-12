import { TrendingUpIcon } from 'lucide-react';

import { TrendChart } from '@/components/ui/charts';
import { useTranslation } from '@/hooks/useTranslation';

export type TrendPoint = {
    date: string;
    topup_usage: number;
    signups: number;
    ftth_bill_payments: number;
    wifi_package_orders: number;
};

export type TrendSeries<T extends { date: string }> = {
    dataKey: Exclude<keyof T, 'date'>;
    labelKey: string;
    color: string;
    unit?: string;
    change?: number | null;
};

type DashboardTrendChartProps<T extends { date: string }> = {
    data: T[];
    series: TrendSeries<T>[];
    titleKey: string;
    showLegend?: boolean;
};

export function DashboardTrendChart<T extends { date: string }>({
    data,
    series,
    titleKey,
    showLegend = true,
}: DashboardTrendChartProps<T>) {
    const { t } = useTranslation();

    return (
        <TrendChart
            data={data}
            title={t(titleKey)}
            description={t('dashboard.last_30_days')}
            icon={<TrendingUpIcon className="size-3.5" strokeWidth={1.85} />}
            showLegend={showLegend}
            series={series.map((item) => ({
                dataKey: item.dataKey,
                label: t(item.labelKey),
                color: item.color,
                unit: item.unit,
                change: item.change,
            }))}
        />
    );
}
