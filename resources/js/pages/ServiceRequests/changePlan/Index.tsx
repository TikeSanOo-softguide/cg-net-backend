import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    ArrowUp,
    ArrowDown,
    RefreshCcw,
    CalendarIcon,
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
import { SearchInput } from '@/components/SearchInput';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { Badge } from '@/components/ui/badge';
import { FormField } from '@/components/ui/form-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { REQUEST_STATUS } from '@/lib/CommonNameConst';
import { useTranslation } from '@/hooks/useTranslation';
import { cn, formatDate, todayInTimezone } from '@/lib/utils';
import { formatPhoneInternational } from '@/lib/phone';
import { StatCard } from '@/components/StatCard';
import { StaffListAvatar } from '@/components/staff/StaffListAvatar';
import { useOpenRequestFromQuery } from '@/hooks/useOpenRequestFromQuery';

type Filters = {
    search: string;
    status: string;
    service_type: string;
};

type ChangePlanRequestWithNotification = ChangePlanRequestItem & {
    notification_unread: boolean;
};

type Props = {
    requests: Paginated<ChangePlanRequestWithNotification>;
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
    const [selectedRequest, setSelectedRequest] = useState<ChangePlanRequestWithNotification | null>(null);
    const [search, setSearch] = useState(filters.search);
    const debounce = useRef<number>(0);

    const closeRequestDetails = useOpenRequestFromQuery(
        requests.data,
        selectedRequest,
        setSelectedRequest,
        'change_plan_request',
    );

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
                    showSearch={false}
                    pagination={requests}
                    onView={(row) => setSelectedRequest(row)}
                    directActions
                    filters={
                        <>
                            <FormField
                                label={t('common.search')}
                                htmlFor="change-plan-search"
                                className="w-full shrink-0 sm:w-60"
                                labelClassName="text-[13px]"
                            >
                                <SearchInput
                                    id="change-plan-search"
                                    value={search}
                                    onChange={onSearchChange}
                                    placeholder={t('change_plan.search_placeholder')}
                                    size="sm"
                                />
                            </FormField>
                            <FormField
                                label={t('change_plan.service_type')}
                                htmlFor="change-plan-service-type"
                                className="w-full shrink-0 sm:w-40"
                                labelClassName="text-[13px]"
                            >
                                <Select
                                    value={filters.service_type || 'all'}
                                    onValueChange={(serviceType) =>
                                        visit({
                                            service_type: serviceType === 'all' ? '' : serviceType,
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        id="change-plan-service-type"
                                        className="w-full rounded-xl border-border/60 bg-background"
                                    >
                                        <SelectValue placeholder={t('change_plan.service_type')} />
                                    </SelectTrigger>
                                    <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        <SelectItem value="FTTH">FTTH</SelectItem>
                                        <SelectItem value="CG">{t('change_plan.cg_router')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label={t('common.status')}
                                htmlFor="change-plan-status"
                                className="w-full shrink-0 sm:w-48"
                                labelClassName="text-[13px]"
                            >
                                <Select
                                    value={filters.status || 'all'}
                                    onValueChange={(status) =>
                                        visit({
                                            status: status === 'all' ? '' : status,
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        id="change-plan-status"
                                        className="w-full rounded-xl border-border/60 bg-background"
                                    >
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
                            </FormField>
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
                                        <StaffListAvatar
                                            username={request.user.name}
                                            unreadLabel={
                                                request.notification_unread
                                                    ? t('admin_notifications.unread')
                                                    : undefined
                                            }
                                        />
                                    </div>

                                    <span className="line-clamp-2 font-semibold leading-5">{request.user.name}</span>

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
                            id: 'contact',
                            header: t('requests.contact_information'),
                            mobile: 'meta' as const,
                            cell: (request) => (
                                <div className="min-w-0 text-xs text-muted-foreground">
                                    <p className="truncate font-medium leading-6 text-foreground">{request.contact_name}</p>
                                    <p className="truncate">{formatPhoneInternational(request.contact_phone)}</p>
                                </div>
                            ),
                            searchValue: (request) => `${request.contact_name} ${request.contact_phone}`,
                        },
                        {
                            id: 'service_type',
                            header: t('change_plan.service_type'),
                            mobile: false,
                            cell: (request) => (
                                <span>
                                    {request.service_type === 'CG'
                                        ? t('change_plan.cg_router')
                                        : request.service_type?.trim() || '—'}
                                </span>
                            ),
                            searchValue: (request) => request.service_type ?? '',
                        },
                        {
                            id: 'current_plans',
                            header: t('change_plan.current_plan'),
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
                            header: t('change_plan.new_plan'),
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
