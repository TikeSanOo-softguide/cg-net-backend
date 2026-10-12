import { ClipboardListIcon } from 'lucide-react';

import { BarListChart } from '@/components/ui/charts';
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

export function DashboardRequestLevels({ data }: DashboardRequestLevelsProps) {
    const { t } = useTranslation();

    return (
        <BarListChart
            data={data.items.map((item, index) => ({
                label: t(`type.${item.type}`),
                value: item.value,
                color: ['#4F46E5', '#E11D48', '#0EA5E9', '#14B8A6', '#F59E0B'][index % 5],
            }))}
            title={t('dashboard.request_levels')}
            description={t('dashboard.request_levels_duration')}
            icon={<ClipboardListIcon className="size-3.5" strokeWidth={1.85} />}
            total={data.items.reduce((sum, item) => sum + item.value, 0)}
        />
    );
}
