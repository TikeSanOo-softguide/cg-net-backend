import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { ChevronDownIcon, UsersIcon, WifiIcon, PackageIcon, BanknoteIcon, ClipboardListIcon, Trash2Icon } from 'lucide-react';
import { Bar, CartesianGrid, ComposedChart, Legend, Line, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { PageHeader } from '@/components/PageHeader';
import { StatCard } from '@/components/StatCard';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useCan } from '@/hooks/useCan';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import { visitBulkDelete } from '@/lib/bulk-delete';

type ChartPoint = {
    date: string;
    revenue: number;
    signups: number;
};

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
    chart: ChartPoint[];
    recentRequests: RecentRequest[];
};

export default function DashboardIndex({ stats, chart, recentRequests }: DashboardProps) {
    const { t } = useTranslation();
    const can = useCan();
    const isMobile = useMediaQuery('(max-width: 639px)');
    const [legendOpen, setLegendOpen] = useState(false);
    const [pendingIds, setPendingIds] = useState<string[]>([]);
    const [processing, setProcessing] = useState(false);
    const canDelete = can('service-requests.delete');
    const cards = [
        {
            key: 'dashboard.total_customers',
            value: stats.total_customers.toLocaleString(),
            icon: UsersIcon,
            iconWrapperClassName: 'bg-teal-100 text-teal-800 dark:bg-teal-500/15 dark:text-teal-300',
        },
        {
            key: 'dashboard.active_broadband_accounts',
            value: stats.active_broadband_accounts.toLocaleString(),
            icon: WifiIcon,
            iconWrapperClassName: 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
        },
        {
            key: 'dashboard.active_packages',
            value: stats.active_packages.toLocaleString(),
            icon: PackageIcon,
            iconWrapperClassName: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
        },
        {
            key: 'dashboard.todays_revenue',
            value: `${Number(stats.todays_revenue).toLocaleString()} MMK`,
            icon: BanknoteIcon,
            iconWrapperClassName: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
        },
        {
            key: 'dashboard.pending_requests',
            value: stats.pending_requests.toLocaleString(),
            icon: ClipboardListIcon,
            iconWrapperClassName: 'bg-orange-100 text-orange-800 dark:bg-orange-500/15 dark:text-orange-300',
        },
    ];

    return (
        <>
            <Head title={t('menu.dashboard')} />
            <div className="flex w-full flex-col gap-6 pt-6 lg:gap-8 lg:pt-8">
                <PageHeader eyebrow={t('dashboard.welcome_back')} title={t('dashboard.overview')} />

                <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    {cards.map((card) => (
                        <StatCard
                            key={card.key}
                            title={t(card.key)}
                            value={card.value}
                            icon={card.icon}
                            iconWrapperClassName={card.iconWrapperClassName}
                        />
                    ))}
                </section>

                <Card className="gap-0 py-0">
                    <CardHeader className="flex flex-row items-center justify-between gap-3 px-5 py-5 sm:px-6">
                        <div>
                            <CardTitle className="text-lg font-semibold">{t('dashboard.last_30_days')}</CardTitle>
                            <CardDescription>
                                {t('dashboard.revenue')}
                                {! isMobile ? ` · ${t('dashboard.new_signups')}` : null}
                            </CardDescription>
                        </div>
                        {isMobile ? (
                            <Button type="button" variant="outline" size="sm" onClick={() => setLegendOpen((open) => ! open)}>
                                {legendOpen ? t('dashboard.hide_legend') : t('dashboard.show_legend')}
                                <ChevronDownIcon className={cn('size-4 transition-transform duration-200', legendOpen && 'rotate-180')} />
                            </Button>
                        ) : null}
                    </CardHeader>
                    <CardContent className="px-5 pb-5 sm:px-6 sm:pb-6">
                        {isMobile && legendOpen ? (
                            <ul className="mb-4 flex flex-col gap-1 text-sm">
                                <li className="flex items-center gap-2">
                                    <span className="size-2 rounded-full bg-chart-1" />
                                    {t('dashboard.revenue')}
                                </li>
                                <li className="flex items-center gap-2">
                                    <span className="size-2 rounded-full bg-chart-2" />
                                    {t('dashboard.new_signups')}
                                </li>
                            </ul>
                        ) : null}
                        <div className="h-64 w-full min-w-0 sm:h-72">
                            <ResponsiveContainer width="100%" height="100%">
                                <ComposedChart data={chart} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                                    <CartesianGrid stroke="var(--border)" strokeDasharray="3 3" />
                                    <XAxis dataKey="date" tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }} tickFormatter={(value) => String(value).slice(5)} />
                                    <YAxis yAxisId="left" tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }} />
                                    {! isMobile || legendOpen ? (
                                        <YAxis yAxisId="right" orientation="right" tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }} />
                                    ) : null}
                                    <Tooltip
                                        contentStyle={{
                                            background: 'var(--card)',
                                            border: '1px solid var(--border)',
                                            borderRadius: '8px',
                                            color: 'var(--card-foreground)',
                                            boxShadow: 'var(--elev-card)',
                                        }}
                                    />
                                    {! isMobile ? (
                                        <Legend
                                            formatter={(value) => (value === 'revenue' ? t('dashboard.revenue') : t('dashboard.new_signups'))}
                                        />
                                    ) : null}
                                    <Line
                                        yAxisId="left"
                                        type="monotone"
                                        dataKey="revenue"
                                        name="revenue"
                                        stroke="var(--chart-1)"
                                        strokeWidth={2}
                                        dot={false}
                                    />
                                    {! isMobile || legendOpen ? (
                                        <Bar yAxisId="right" dataKey="signups" name="signups" fill="var(--chart-2)" radius={[4, 4, 0, 0]} />
                                    ) : null}
                                </ComposedChart>
                            </ResponsiveContainer>
                        </div>
                    </CardContent>
                </Card>

                <DataTable
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
            </div>
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
