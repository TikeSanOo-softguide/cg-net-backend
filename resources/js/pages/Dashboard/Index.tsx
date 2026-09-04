import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { UsersIcon, WifiIcon, PackageIcon, BanknoteIcon, ClipboardListIcon, Trash2Icon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DashboardRegionChart, type RegionChartSlice } from '@/components/dashboard/DashboardRegionChart';
import { DashboardRequestLevels, type RequestTypeChart } from '@/components/dashboard/DashboardRequestLevels';
import { DashboardTrendChart, type TrendPoint } from '@/components/dashboard/DashboardTrendChart';
import { DataTable } from '@/components/DataTable';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatCard } from '@/components/StatCard';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { useCan } from '@/hooks/useCan';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { useTranslation } from '@/hooks/useTranslation';
import { visitBulkDelete } from '@/lib/bulk-delete';

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
        active_broadband_accounts: number;
        active_packages: number;
        todays_revenue: string;
        pending_requests: number;
    };
    chart: TrendPoint[];
    regionChart: RegionChartSlice[];
    requestTypeChart: RequestTypeChart;
    recentRequests: RecentRequest[];
};

export default function DashboardIndex({ stats, chart, regionChart, requestTypeChart, recentRequests }: DashboardProps) {
    const { t } = useTranslation();
    const can = useCan();
    const isMobile = useMediaQuery('(max-width: 639px)');
    const [pendingIds, setPendingIds] = useState<string[]>([]);
    const [processing, setProcessing] = useState(false);
    const canDelete = can('service-requests.delete');
    const cards = [
        {
            key: 'dashboard.total_customers',
            title: t('dashboard.total_customers'),
            value: stats.total_customers.toLocaleString(),
            icon: UsersIcon,
        },
        {
            key: 'dashboard.active_broadband_accounts',
            title: t('dashboard.active_broadband_accounts'),
            value: stats.active_broadband_accounts.toLocaleString(),
            icon: WifiIcon,
        },
        {
            key: 'dashboard.active_packages',
            title: t('dashboard.active_packages'),
            value: stats.active_packages.toLocaleString(),
            icon: PackageIcon,
        },
        {
            key: 'dashboard.todays_revenue',
            title: t('dashboard.todays_revenue'),
            value: `${Number(stats.todays_revenue).toLocaleString()} MMK`,
            icon: BanknoteIcon,
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

                <div className="grid grid-cols-1 items-stretch gap-3 xl:grid-cols-3">
                    <DashboardTrendChart data={chart} isMobile={isMobile} />
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
                    onBulkDelete={canDelete ? (ids) => visitBulkDelete('/dashboard/requests/bulk-destroy', ids) : undefined}
                    bulkDeleteTitle={t('dashboard.bulk_delete_title')}
                    actions={canDelete ? (row) => (
                        <TableActionButton
                            label={t('common.delete')}
                            icon={Trash2Icon}
                            tone="danger"
                            onClick={(event) => {
                                event.stopPropagation();
                                setPendingIds([row.id]);
                            }}
                        />
                    ) : undefined}
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
                            className: 'font-mono text-[11px] text-muted-foreground',
                            mobile: 'meta',
                            searchValue: (row) => row.created_at ?? '',
                            cell: (row) => row.created_at?.slice(0, 10),
                        },
                    ]}
                />
            </PageContent>
            <ConfirmDialog
                open={pendingIds.length === 1}
                onOpenChange={(open) => {
                    if (! open) {
                        setPendingIds([]);
                    }
                }}
                title={t('dashboard.delete_title')}
                description={t('dashboard.delete_description')}
                confirmLabel={t('common.delete')}
                destructive
                processing={processing}
                onConfirm={() => {
                    if (pendingIds.length !== 1) {
                        return;
                    }

                    router.delete('/dashboard/requests/bulk-destroy', {
                        data: { ids: pendingIds },
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => {
                            setProcessing(false);
                            setPendingIds([]);
                        },
                    });
                }}
            />
        </>
    );
}
