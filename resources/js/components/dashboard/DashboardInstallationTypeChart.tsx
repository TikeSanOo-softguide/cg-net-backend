import { WifiIcon } from 'lucide-react';

import { DonutChart } from '@/components/ui/charts';
import { useTranslation } from '@/hooks/useTranslation';

export type InstallationTypeChartSlice = {
    service_type: string;
    value: number;
};

type DashboardInstallationTypeChartProps = {
    data: InstallationTypeChartSlice[];
};

const INSTALLATION_COLORS = ['#0891B2', '#4F46E5', '#94A3B8'];

function serviceTypeLabel(serviceType: string, t: (key: string) => string): string {
    if (serviceType === 'CG') {
        return t('broadband_application.cg_router');
    }

    if (serviceType === 'FTTH') {
        return 'FTTH';
    }

    return t('dashboard.other');
}

export function DashboardInstallationTypeChart({ data }: DashboardInstallationTypeChartProps) {
    const { t } = useTranslation();
    const slices = data.map((slice, index) => ({
        name: serviceTypeLabel(slice.service_type, t),
        value: slice.value,
        color: INSTALLATION_COLORS[index % INSTALLATION_COLORS.length],
    }));

    return (
        <DonutChart
            data={slices}
            title={t('dashboard.installation_service_type')}
            description={`${t('dashboard.installations_by_service_type')} · ${t('dashboard.last_30_days')}`}
            icon={<WifiIcon className="size-3.5" strokeWidth={1.85} />}
            total={data.reduce((sum, slice) => sum + slice.value, 0)}
            totalLabel={t('dashboard.installation_service_type')}
        />
    );
}
