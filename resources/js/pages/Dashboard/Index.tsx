import { Head } from '@inertiajs/react';
import { UsersIcon, UserPlusIcon, PackageIcon, CoinsIcon, ClipboardListIcon } from 'lucide-react';

import { DashboardRegionChart, type RegionChartSlice } from '@/components/dashboard/DashboardRegionChart';
import { DashboardRequestLevels, type RequestTypeChart } from '@/components/dashboard/DashboardRequestLevels';
import { DashboardTrendChart, type TrendPoint } from '@/components/dashboard/DashboardTrendChart';
import { DataTable } from '@/components/DataTable';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatCard } from '@/components/StatCard';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDate } from '@/lib/utils';

type RecentRequest = {
    id: string;
    type: string;
    customer: string;
    status: string;
    created_at: string;
};

type DashboardProps = {
    stats: {
        total_customers: number;
        monthly_signups: number;
        active_packages: number;
        todays_topup_usage: number;
        pending_requests: number;
    };
    chart: TrendPoint[];
    topupUsageChange: number | null;
    regionChart: RegionChartSlice[];
    requestTypeChart: RequestTypeChart;
    recentRequests: RecentRequest[];
};

export default function DashboardIndex({
    stats,
    chart,
    topupUsageChange,
    regionChart,
    requestTypeChart,
    recentRequests,
}: DashboardProps) {
    const { t } = useTranslation();
    const cards = [
        {
            key: 'dashboard.total_customers',
            title: t('dashboard.total_customers'),
            value: stats.total_customers.toLocaleString(),
            icon: UsersIcon,
        },
        {
            key: 'dashboard.new_signups_this_month',
            title: t('dashboard.new_signups_this_month'),
            value: stats.monthly_signups.toLocaleString(),
            icon: UserPlusIcon,
        },
        {
            key: 'dashboard.active_packages',
            title: t('dashboard.active_packages'),
            value: stats.active_packages.toLocaleString(),
            icon: PackageIcon,
        },
        {
            key: 'dashboard.todays_topup_usage',
            title: t('dashboard.todays_topup_usage'),
            value: (
                <>
                    {stats.todays_topup_usage.toLocaleString()}{' '}
                    <span className="text-[18px] font-medium">{t('dashboard.points')}</span>
                </>
            ),
            icon: CoinsIcon,
        },
        {
            key: 'dashboard.pending_requests',
            title: t('dashboard.pending_requests'),
            value: stats.pending_requests.toLocaleString(),
            icon: ClipboardListIcon,
        },
    ];

    return (
        <>
            <Head title={t('menu.dashboard')} />
            <PageContent className="gap-3 lg:gap-3.5">
                <PageHeader />

                <StatCard items={cards} />

                <div className="grid grid-cols-1 items-stretch gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <DashboardTrendChart
                        data={chart}
                        titleKey="dashboard.top_up_usage"
                        showLegend={false}
                        series={[
                            {
                                dataKey: 'topup_usage',
                                labelKey: 'dashboard.top_up_usage',
                                color: '#4F46E5',
                                unit: t('dashboard.points'),
                                change: topupUsageChange,
                            },
                        ]}
                    />
                    <DashboardTrendChart
                        data={chart}
                        titleKey="dashboard.ftth_bill_payments"
                        showLegend={false}
                        series={[
                            {
                                dataKey: 'ftth_bill_payments',
                                labelKey: 'dashboard.ftth_bill_payments',
                                color: '#0891B2',
                                unit: t('dashboard.points'),
                            },
                        ]}
                    />
                    <DashboardTrendChart
                        data={chart}
                        titleKey="dashboard.wifi_package_orders"
                        showLegend={false}
                        series={[
                            {
                                dataKey: 'wifi_package_orders',
                                labelKey: 'dashboard.wifi_package_orders',
                                color: '#16A34A',
                                unit: t('dashboard.points'),
                            },
                        ]}
                    />
                    <DashboardTrendChart
                        data={chart}
                        titleKey="dashboard.new_signups"
                        showLegend={false}
                        series={[
                            {
                                dataKey: 'signups',
                                labelKey: 'dashboard.new_signups',
                                color: '#E11D48',
                                unit: t('dashboard.users'),
                            },
                        ]}
                    />
                    <DashboardRegionChart data={regionChart} />
                    <DashboardRequestLevels data={requestTypeChart} />
                </div>

                <DataTable
                    className="min-w-0"
                    numbered={false}
                    showSearch={false}
                    title={t('dashboard.today_requests')}
                    titleIcon={ClipboardListIcon}
                    data={recentRequests}
                    getRowId={(row) => row.id}
                    columns={[
                        {
                            id: 'customer',
                            header: t('dashboard.customer'),
                            className: 'font-medium',
                            mobile: 'title',
                            searchValue: (row) => row.customer,
                            cell: (row) => row.customer,
                        },
                        {
                            id: 'type',
                            header: t('dashboard.type'),
                            mobile: 'subtitle',
                            searchValue: (row) => t(`type.${row.type}`),
                            cell: (row) => t(`type.${row.type}`),
                        },
                        {
                            id: 'status',
                            header: t('dashboard.status'),
                            mobile: 'badge',
                            searchValue: (row) => t(`status.${row.status}`),
                            cell: (row) => <StatusBadge status={row.status} />,
                        },
                        {
                            id: 'date',
                            header: t('dashboard.date'),
                            className: 'text-[11px] text-muted-foreground',
                            mobile: 'meta',
                            searchValue: (row) => row.created_at ?? '',
                            cell: (row) => formatDate(row.created_at),
                        },
                    ]}
                />
            </PageContent>
        </>
    );
}
