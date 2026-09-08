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
import { CHANGE_PLAN_STATUS } from '@/lib/CommonNameConst';
import { useTranslation } from '@/hooks/useTranslation';
import { cn, formatDate } from '@/lib/utils';
import { StatCard } from '@/components/StatCard';
import { StaffListAvatar } from '@/components/staff/StaffListAvatar';

type Filters = {
    search: string;
    status: string;
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

export default function ChangePlanIndex({ requests, filters, statuses, stats }: Props) {
    const { t } = useTranslation();
    const [selectedRequest, setSelectedRequest] = useState<ChangePlanRequestItem | null>(null);
    const [search, setSearch] = useState(filters.search);
    const debounce = useRef<number>(0);

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

        const preferredDate = new Date(dateString);
        const today = new Date();

        return preferredDate < today;
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
                        <FormControl icon={WifiIcon} compact className="w-full shrink-0 sm:w-48">
                            <Select value={filters.status || 'all'} onValueChange={(status) => visit({ status })}>
                                <SelectTrigger className="w-full">
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

                                    <span className="min-w-0 truncate font-medium">{request.user.name}</span>

                                    <p className="truncate font-mono text-xs text-muted-foreground">
                                        {request.broadband_account.account_number}
                                    </p>
                                </div>
                            ),
                            searchValue: (request) =>
                                `${request.user.name} ${request.broadband_account.account_number}`,
                        },
                        {
                            id: 'current_plans',
                            header: t('change_plan.current_packages'),
                            mobile: false,
                            cell: (request) => (
                                <span className="truncate text-xs text-muted-foreground mb-1">
                                    {packageName(request.current_package)}
                                </span>
                            ),
                            searchValue: (request) => packageName(request.current_package),
                        },
                        {
                            id: 'requested_plans',
                            header: t('change_plan.requested_packages'),
                            mobile: 'subtitle' as const,
                            cell: (request) => {
                                const currentSpeed = request.current_package.speed?.mbps;
                                const newSpeed = request.new_package.speed?.mbps;

                                const speedChanged =
                                    currentSpeed !== undefined && newSpeed !== undefined && newSpeed !== currentSpeed;

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
                                        <span className="truncate">{packageName(request.new_package)}</span>
                                    </span>
                                );
                            },
                            searchValue: (request) => packageName(request.new_package),
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
                                    {request.status === CHANGE_PLAN_STATUS.APPROVED && request.admin && (
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
                onOpenChange={(open) => !open && setSelectedRequest(null)}
                request={selectedRequest}
            />
        </>
    );
}
