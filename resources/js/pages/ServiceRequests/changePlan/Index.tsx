import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    ArrowUp,
    ArrowDown,
    RefreshCcw,
    CalendarIcon,
    WifiIcon,
    ClipboardList,
    ScanEye,
    CircleCheckBig,
    BookX,
} from 'lucide-react';

import {
    ChangePlanDetailDialog,
    type ChangePlanRequestItem,
    packageName,
} from '@/components/service-requests/changePlan/ChangePlanDetailDialog';
import { DataTable } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { REQUEST_STATUS } from '@/lib/CommonNameConst';
import { useTranslation } from '@/hooks/useTranslation';
import { cn, formatDate, todayInTimezone } from '@/lib/utils';
import { StatCard } from '@/components/StatCard';
import { StaffListAvatar } from '@/components/staff/StaffListAvatar';
import { useOpenRequestFromQuery } from '@/hooks/useOpenRequestFromQuery';

type Filters = {
    search: string;
    status: string;
    service_type: string;
};

type Props = {
    requests: Paginated<ChangePlanRequestItem>;
    filters: Filters;
    statuses: string[];
    stats: {
        total_requests: number;
        under_reviews_requests: number;
        approved_requests: number;
        cancelled_requests: number;
    };
};

function appServiceType(account?: string | null): string {
    if (account?.startsWith('FTTH')) return 'FTTH';
    if (account?.startsWith('CG')) return 'CG';
    return '';
}

export default function ChangePlanIndex({ requests, filters, statuses, stats }: Props) {
    const { t } = useTranslation();
    const [selectedRequest, setSelectedRequest] = useState<ChangePlanRequestItem | null>(null);
    const [search, setSearch] = useState(filters.search);
    const debounce = useRef<number>(0);

    const closeRequestDetails = useOpenRequestFromQuery(requests.data, setSelectedRequest);

    const cards = [
        {
            key: 'change_plan.total_requests',
            title: t('change_plan.total_requests'),
            value: stats.total_requests.toLocaleString(),
            icon: ClipboardList,
        },
        {
            key: 'change_plan.under_review_requests',
            title: t('change_plan.under_review_requests'),
            value: stats.under_reviews_requests.toLocaleString(),
            icon: ScanEye,
        },
        {
            key: 'change_plan.approved_requests',
            title: t('change_plan.approved_requests'),
            value: stats.approved_requests.toLocaleString(),
            icon: CircleCheckBig,
        },
        {
            key: 'change_plan.cancelled_requests',
            title: t('change_plan.cancelled_requests'),
            value: stats.cancelled_requests.toLocaleString(),
            icon: BookX,
        },
    ];

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const visit = (next: Partial<Filters>) => {
        router.get(
            '/service-requests/change-plan',
            {
                search: (next.search ?? filters.search) || undefined,
                status: next.status ?? filters.status,
                service_type: (next.service_type ?? filters.service_type) || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const onSearchChange = (value: string) => {
        setSearch(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => visit({ search: value }), 300);
    };

    function isPreferredDateExpired(dateString: string | null, status: string): boolean {
        if (!dateString || status !== 'under_review') return false;

        return dateString < todayInTimezone();
    }

    return (
        <>
            <Head title={t('menu.change_plan_requests')} />
            <PageContent>
                <PageHeader />
                <StatCard items={cards} className="xl:grid-cols-4" />
                <DataTable
                    data={requests.data}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={onSearchChange}
                    searchPlaceholder={t('change_plan.search_placeholder')}
                    pagination={requests}
                    onView={(row) => setSelectedRequest(row)}
                    directActions
                    filters={
                        <>
                            <FormControl icon={WifiIcon} compact className="w-full shrink-0 sm:w-40">
                                <Select
                                    value={filters.service_type || 'all'}
                                    onValueChange={(serviceType) =>
                                        visit({
                                            service_type: serviceType === 'all' ? '' : serviceType,
                                        })
                                    }
                                >
                                    <SelectTrigger className="w-full rounded-xl border-border/60 bg-background">
                                        <SelectValue placeholder={t('change_plan.service_type')} />
                                    </SelectTrigger>
                                    <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        <SelectItem value="FTTH">FTTH</SelectItem>
                                        <SelectItem value="CG">{t('change_plan.cg_router')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormControl icon={WifiIcon} compact className="w-full shrink-0 sm:w-48">
                                <Select
                                    value={filters.status || 'all'}
                                    onValueChange={(status) =>
                                        visit({
                                            status: status === 'all' ? '' : status,
                                        })
                                    }
                                >
                                    <SelectTrigger className="w-full rounded-xl border-border/60 bg-background">
                                        <SelectValue placeholder={t('common.status')} />
                                    </SelectTrigger>
                                    <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {statuses.map((status) => (
                                            <SelectItem key={status} value={status}>
                                                {t(`status.${status}`)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormControl>
                        </>
                    }
                    columns={[
                        {
                            id: 'customer',
                            header: t('menu.customer_management'),
                            mobile: 'title' as const,
                            cell: (request) => (
                                <div className="grid min-w-0 grid-cols-[auto_minmax(0,1fr)] gap-x-2.5">
                                    <div className="row-span-2">
                                        <StaffListAvatar username={request.user.name} />
                                    </div>

                                    <span className="truncate font-semibold">{request.user.name}</span>

                                    <p className="truncate font-mono text-xs text-muted-foreground">
                                        {request.broadband_account_number ?? '—'}
                                    </p>
                                </div>
                            ),
                            searchValue: (request) => `${request.user.name} ${request.broadband_account_number ?? ''}`,
                        },
                        {
                            id: 'ticket_number',
                            header: t('change_plan.ticket_number'),
                            mobile: 'subtitle' as const,
                            cell: (request) => {
                                const ticket = request.ticket_number?.trim();

                                return ticket ? (
                                    <span className="inline-flex max-w-full items-center rounded-md bg-primary/10 px-2 py-0.5 font-mono text-[11px] font-medium text-primary">
                                        {ticket}
                                    </span>
                                ) : (
                                    <span>—</span>
                                );
                            },
                            searchValue: (request) => request.ticket_number ?? '',
                        },
                        {
                            id: 'service_type',
                            header: t('change_plan.service_type'),
                            mobile: false,
                            cell: (request) => {
                                const serviceType = appServiceType(request.broadband_account_number);

                                return (
                                    <span>
                                        {serviceType === 'CG'
                                            ? t('change_plan.cg_router')
                                            : serviceType || '—'}
                                    </span>
                                );
                            },
                            searchValue: (request) =>
                                appServiceType(request.broadband_account_number),
                        },
                        {
                            id: 'current_plans',
                            header: t('change_plan.current_packages'),
                            mobile: false,
                            cell: (request) => (
                                <span className="truncate text-xs text-muted-foreground mb-1">
                                    {packageName(request.current_package_detail)}
                                </span>
                            ),
                            searchValue: (request) => packageName(request.current_package_detail),
                        },
                        {
                            id: 'requested_plans',
                            header: t('change_plan.requested_packages'),
                            mobile: 'subtitle' as const,
                            cell: (request) => {
                                const currentSpeed = Number(request.current_package_detail?.speed?.mbps);
                                const newSpeed = Number(request.new_package_detail?.speed?.mbps);

                                const speedChanged =
                                    Number.isFinite(currentSpeed) &&
                                    Number.isFinite(newSpeed) &&
                                    newSpeed !== currentSpeed;

                                const SpeedIcon = speedChanged
                                    ? newSpeed > currentSpeed
                                        ? ArrowUp
                                        : ArrowDown
                                    : RefreshCcw;

                                const speedIconColor = speedChanged
                                    ? newSpeed > currentSpeed
                                        ? 'text-green-600'
                                        : 'text-red-400'
                                    : 'text-primary';

                                return (
                                    <span className="flex items-center gap-1.5 truncate font-semibold mb-1">
                                        {SpeedIcon && (
                                            <SpeedIcon
                                                className={`size-5 ${speedIconColor}`}
                                                strokeWidth={2.5}
                                                aria-hidden="true"
                                            />
                                        )}
                                        <span className="truncate">{packageName(request.new_package_detail)}</span>
                                    </span>
                                );
                            },
                            searchValue: (request) => packageName(request.new_package_detail),
                        },
                        {
                            id: 'preferred_date',
                            header: t('change_plan.preferred_date'),
                            mobile: 'meta' as const,
                            cell: (request) => {
                                const isExpired = isPreferredDateExpired(request.preferred_date, request.status);

                                return (
                                    <span
                                        className={cn(
                                            'inline-flex items-center gap-1.5 text-xs',
                                            isExpired && 'text-amber-600',
                                        )}
                                    >
                                        <CalendarIcon className="size-3.5" />
                                        {formatDate(request.preferred_date)}
                                        {isExpired && (
                                            <Badge variant="outline" className="text-[9px]">
                                                {t('change_plan.overdue')}
                                            </Badge>
                                        )}
                                    </span>
                                );
                            },
                            searchValue: (request) => formatDate(request.preferred_date),
                        },
                        {
                            id: 'status',
                            header: t('common.status'),
                            mobile: 'badge' as const,
                            cell: (request) => (
                                <div className="flex flex-col items-start gap-1">
                                    <StatusBadge status={request.status} />
                                    {request.status === REQUEST_STATUS.APPROVED && request.admin && (
                                        <span className="text-[11px] text-muted-foreground">
                                            {t('change_plan.approved_by')}: {request.admin.username}
                                        </span>
                                    )}
                                </div>
                            ),
                            searchValue: (request) => request.status,
                        },
                    ]}
                />
            </PageContent>

            <ChangePlanDetailDialog
                open={selectedRequest !== null}
                onOpenChange={(open) => !open && closeRequestDetails()}
                request={selectedRequest}
            />
        </>
    );
}
