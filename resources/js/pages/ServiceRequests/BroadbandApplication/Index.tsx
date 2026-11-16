import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { BookX, CalendarIcon, CircleCheckBig, ClipboardList, MapPinIcon, ScanEye } from 'lucide-react';

import { BroadbandApplicationDetailDialog } from '@/components/service-requests/broadband-application/BroadbandApplicationDetailDialog';
import { DataTable } from '@/components/DataTable';
import { SearchInput } from '@/components/SearchInput';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatCard } from '@/components/StatCard';
import { StatusBadge } from '@/components/StatusBadge';
import { StaffListAvatar } from '@/components/staff/StaffListAvatar';
import { FormField } from '@/components/ui/form-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { formatPhoneInternational } from '@/lib/phone';
import { useOpenRequestFromQuery } from '@/hooks/useOpenRequestFromQuery';
import { formatDate, truncateText } from '@/lib/utils';

type BroadbandApplication = {
    id: number;
    notification_unread: boolean;
    contact_phone: string;
    address: string;
    note: string | null;
    status: string;
    created_at: string;
    contact_name: string;
    service_type: string | null;
    ticket_number: string | null;
    package_detail: {
        product_id?: string;
        product_name?: string;
        product_line?: string;
        product_speed?: string;
        product_price?: number | string;
        installation_fee?: number | string;
        month_cycle?: number | string;
        month_cycle_unit?: string;
    } | null;
    user: {
        id: number;
        name: string;
        phone: string;
        broadband_account_number: string | null;
    } | null;
    admin: {
        id: number;
        username: string;
    } | null;
};

type Filters = {
    search: string;
    status: string;
    service_type: string;
};

type Props = {
    requests: Paginated<BroadbandApplication>;
    filters: Filters;
    statuses: string[];
    stats: {
        total_requests: number;
        under_reviews_requests: number;
        approved_requests: number;
        cancelled_requests: number;
    };
};

function packageName(detail: BroadbandApplication['package_detail']): string {
    const name = detail?.product_name?.trim();

    return name ? name : 'N/A';
}

export default function BroadbandApplicationIndex({ requests, filters, statuses, stats }: Props) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [selectedRequest, setSelectedRequest] = useState<BroadbandApplication | null>(null);
    const debounce = useRef<number>(0);

    const closeRequestDetails = useOpenRequestFromQuery(
        requests.data,
        selectedRequest,
        setSelectedRequest,
        'installation_application',
    );

    const cards = [
        {
            key: 'broadband.total_requests',
            title: t('requests.total_requests'),
            value: stats.total_requests.toLocaleString(),
            icon: ClipboardList,
        },
        {
            key: 'broadband.under_review_requests',
            title: t('requests.under_review_requests'),
            value: stats.under_reviews_requests.toLocaleString(),
            icon: ScanEye,
        },
        {
            key: 'broadband.approved_requests',
            title: t('requests.approved_requests'),
            value: stats.approved_requests.toLocaleString(),
            icon: CircleCheckBig,
        },
        {
            key: 'broadband.cancelled_requests',
            title: t('requests.cancelled_requests'),
            value: stats.cancelled_requests.toLocaleString(),
            icon: BookX,
        },
    ];

    useEffect(() => setSearch(filters.search), [filters.search]);
    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const visit = (next: Partial<Filters>) => {
        router.get(
            '/service-requests/installations',
            {
                search: (next.search ?? filters.search) || undefined,
                status: next.status ?? filters.status,
                service_type: (next.service_type ?? filters.service_type) || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const onSearchChange = (value: string) => {
        setSearch(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => {
            visit({ search: value });
        }, 300);
    };

    return (
        <>
            <Head title={t('menu.installation_applications')} />

            <PageContent>
                <PageHeader />
                <StatCard items={cards} className="xl:grid-cols-4" />

                <DataTable
                    data={requests.data}
                    getRowId={(request) => String(request.id)}
                    search={search}
                    onSearchChange={onSearchChange}
                    showSearch={false}
                    pagination={requests}
                    onView={(request) => setSelectedRequest(request)}
                    directActions
                    filters={
                        <>
                            <FormField
                                label={t('common.search')}
                                htmlFor="installation-search"
                                className="w-full shrink-0 sm:w-60"
                                labelClassName="text-[13px]"
                            >
                                <SearchInput
                                    id="installation-search"
                                    value={search}
                                    onChange={onSearchChange}
                                    placeholder={t('broadband_application.search_placeholder')}
                                    size="sm"
                                />
                            </FormField>
                            <FormField
                                label={t('broadband_application.service_type')}
                                htmlFor="installation-service-type"
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
                                        id="installation-service-type"
                                        className="w-full rounded-xl border-border/60 bg-background"
                                    >
                                        <SelectValue placeholder={t('broadband_application.service_type')} />
                                    </SelectTrigger>
                                    <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        <SelectItem value="FTTH">FTTH</SelectItem>
                                        <SelectItem value="CG">{t('broadband_application.cg_router')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label={t('common.status')}
                                htmlFor="installation-status"
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
                                        id="installation-status"
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
                            header: t('broadband_application.app_user'),
                            mobile: 'title' as const,
                            cell: (request) => (
                                <div className="grid min-w-0 grid-cols-[auto_minmax(0,1fr)] gap-x-2.5">
                                    <div className="row-span-2">
                                        <StaffListAvatar
                                            username={request.user?.name || '—'}
                                            unreadLabel={
                                                request.notification_unread
                                                    ? t('admin_notifications.unread')
                                                    : undefined
                                            }
                                        />
                                    </div>
                                    <span className="line-clamp-2 min-w-0 font-medium leading-5">
                                        {request.user?.name || '—'}
                                    </span>

                                    <p className="mt-1 truncate font-mono text-muted-foreground">
                                        {request.user?.broadband_account_number ?? '—'}
                                    </p>
                                </div>
                            ),
                            searchValue: (request) =>
                                `${request.user?.name ?? ''} ${request.user?.broadband_account_number ?? ''} ${request.user?.phone ?? ''}`,
                        },
                        {
                            id: 'ticket_number',
                            header: t('broadband_application.ticket_number'),
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
                            header: t('broadband_application.service_type'),
                            mobile: 'meta' as const,
                            cell: (request) => (
                                <span>
                                    {request.service_type === 'CG'
                                        ? t('broadband_application.cg_router')
                                        : request.service_type?.trim() || '—'}
                                </span>
                            ),
                            searchValue: (request) => request.service_type ?? '',
                        },
                        {
                            id: 'package',
                            header: t('broadband_application.requested_plan'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex max-w-[260px] items-center gap-1.5">
                                    <span>{truncateText(packageName(request.package_detail), 40)}</span>{' '}
                                </span>
                            ),
                            searchValue: (request) => packageName(request.package_detail),
                        },
                        {
                            id: 'address',
                            header: t('requests.address'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex max-w-[260px] items-center gap-2">
                                    <MapPinIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                    <span className="truncate">{truncateText(request.address, 35)}</span>
                                </span>
                            ),
                            searchValue: (request) => request.address,
                        },
                        {
                            id: 'created_at',
                            header: t('requests.requested_date'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex items-center gap-1.5">
                                    <CalendarIcon className="size-3.5" />
                                    {formatDate(request.created_at)}
                                </span>
                            ),
                            searchValue: (request) => formatDate(request.created_at),
                        },
                        {
                            id: 'status',
                            header: t('common.status'),
                            mobile: 'badge' as const,
                            cell: (request) => (
                                <div className="flex flex-col items-start gap-1">
                                    <StatusBadge status={request.status} />
                                    {request.status === 'approved' && request.admin && (
                                        <span className="text-[11px] text-muted-foreground">
                                            {t('requests.approved_by')}: {request.admin.username}
                                        </span>
                                    )}
                                </div>
                            ),
                            searchValue: (request) => request.status,
                        },
                    ]}
                />
            </PageContent>

            <BroadbandApplicationDetailDialog
                open={selectedRequest !== null}
                onOpenChange={(open) => !open && closeRequestDetails()}
                request={selectedRequest}
                canUpdate={can('service-requests.update')}
                statuses={statuses}
            />
        </>
    );
}
