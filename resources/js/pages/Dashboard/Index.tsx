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
            value: stats.total_customers.toLocaleString(),
            icon: UsersIcon,
            iconWrapperClassName: 'bg-teal-100 text-teal-800 dark:bg-teal-500/15 dark:text-teal-300',
            accentClassName: 'bg-teal-500',
        },
        {
            key: 'dashboard.active_broadband_accounts',
            value: stats.active_broadband_accounts.toLocaleString(),
            icon: WifiIcon,
            iconWrapperClassName: 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
            accentClassName: 'bg-sky-500',
        },
        {
            key: 'dashboard.active_packages',
            value: stats.active_packages.toLocaleString(),
            icon: PackageIcon,
            iconWrapperClassName: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
            accentClassName: 'bg-amber-500',
        },
        {
            key: 'dashboard.todays_revenue',
            value: `${Number(stats.todays_revenue).toLocaleString()} MMK`,
            icon: BanknoteIcon,
            iconWrapperClassName: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
            accentClassName: 'bg-emerald-500',
        },
        {
            key: 'dashboard.pending_requests',
            value: stats.pending_requests.toLocaleString(),
            icon: ClipboardListIcon,
            iconWrapperClassName: 'bg-orange-100 text-orange-800 dark:bg-orange-500/15 dark:text-orange-300',
            accentClassName: 'bg-orange-500',
        },
    ];

    return (
        <>
            <Head title={t('menu.dashboard')} />
            <PageContent className="gap-6 lg:gap-8">
                <PageHeader />

                <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    {cards.map((card) => (
                        <StatCard
                            key={card.key}
                            title={t(card.key)}
                            value={card.value}
                            icon={card.icon}
                            iconWrapperClassName={card.iconWrapperClassName}
                            accentClassName={card.accentClassName}
                        />
                    ))}
                </section>

                <div className="grid grid-cols-1 items-stretch gap-4 xl:grid-cols-2">
                    <DashboardTrendChart data={chart} isMobile={isMobile} />
                    <DashboardRegionChart data={regionChart} />
                </div>

                <div className="grid grid-cols-1 items-stretch gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,0.85fr)]">
                    <DataTable
                        className="min-w-0"
                        numbered={false}
                        title={t('dashboard.recent_requests')}
                        data={recentRequests}
                        getRowId={(row) => row.id}
                        searchPlaceholder={t('dashboard.search_requests')}
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
                    <DashboardRequestLevels data={requestTypeChart} />
                </div>
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
