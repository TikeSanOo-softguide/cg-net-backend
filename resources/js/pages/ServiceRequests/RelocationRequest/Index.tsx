import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowRightIcon,
    CalendarDaysIcon,
    CalendarIcon,
    CheckCircle2Icon,
    Clock3Icon,
    FileTextIcon,
    MapPinIcon,
    NavigationIcon,
    PhoneCallIcon,
    PhoneIcon,
    UserIcon,
    WifiIcon,
    XIcon,
} from 'lucide-react';

import { DataTable } from '@/components/DataTable';
import { FormDialog } from '@/components/FormDialog';
import { formActionBarClass, formActionButtonClass, formActionSubmitClass } from '@/components/FormActionBar';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { cn, formatDate, truncateText } from '@/lib/utils';
import { StatCard } from '@/components/StatCard';
import { StaffListAvatar } from '@/components/staff/StaffListAvatar';
import { CHANGE_PLAN_STATUS } from '@/lib/CommonNameConst';
import { RelocationRequestDetailDialog } from '@/components/service-requests/RelocationRequest/RelocationRequestDetailDialog';

type RelocationRequest = {
    id: number;
    current_address: string;
    new_address: string;
    preferred_date: string | null;
    phone: string;
    details: string | null;
    status: string;
    user: {
        id: number;
        name: string;
        phone: string;
    };
    broadband_account: {
        id: number;
        account_number: string;
        customer_name: string;
    };
    admin: {
        id: number;
        username: string;
    } | null;
};

type Filters = {
    search: string;
    status: string;
};

type Stats = {
    total: number;
    under_review: number;
    approved: number;
};

type Props = {
    requests: Paginated<RelocationRequest>;
    filters: Filters;
    statuses: string[];
    stats: Stats;
};

export default function RelocationRequestIndex({ requests, filters, statuses, stats }: Props) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [selectedRequest, setSelectedRequest] = useState<RelocationRequest | null>(null);
    const debounce = useRef<number>(0);
    const totalRequests = stats.total;
    const underReviewCount = stats.under_review;
    const approvedCount = stats.approved;

    const cards = [
        {
            key: 'relocation_requests.total_requests',
            title: t('relocation_requests.total_requests'),
            value: stats.total.toLocaleString(),
            icon: NavigationIcon,
        },
        {
            key: 'relocation_requests.under_review_requests',
            title: t('relocation_requests.under_review_requests'),
            value: stats.under_review.toLocaleString(),
            icon: Clock3Icon,
        },
        {
            key: 'relocation_requests.approved_requests',
            title: t('relocation_requests.approved_requests'),
            value: stats.approved.toLocaleString(),
            icon: CheckCircle2Icon,
        },
    ];
    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => {
        return () => {
            window.clearTimeout(debounce.current);
        };
    }, []);

    const visit = (next: Partial<Filters>) => {
        router.get(
            '/service-requests/relocations',
            {
                search: (next.search ?? filters.search) || undefined,
                status: next.status ?? filters.status,
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
            <Head title={t('menu.relocation_requests')} />

            <PageContent>
                <PageHeader />
                <StatCard items={cards} />

                <DataTable
                    data={requests.data}
                    getRowId={(request) => String(request.id)}
                    search={search}
                    onSearchChange={onSearchChange}
                    searchPlaceholder={t('relocation_requests.search')}
                    pagination={requests}
                    onView={(request) => setSelectedRequest(request)}
                    directActions
                    filters={
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
                                `${request.user.name} ${request.broadband_account.account_number} ${request.phone}`,
                        },
                        {
                            id: 'current_address',
                            header: t('relocation_requests.current_address'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex max-w-[260px] items-center gap-1.5 text-xs">
                                    <MapPinIcon className="size-3.5 shrink-0 text-muted-foreground/60" />
                                    <span>{truncateText(request.current_address, 30)}</span>
                                </span>
                            ),
                            searchValue: (request) => request.current_address,
                        },
                        {
                            id: 'new_address',
                            header: t('relocation_requests.new_address'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex max-w-[260px] items-center gap-1.5 text-xs ">
                                    <MapPinIcon className="size-3.5 shrink-0 text-muted-foreground/60 text-success/70" />
                                    <span>{truncateText(request.new_address, 30)}</span>
                                </span>
                            ),
                            searchValue: (request) => request.new_address,
                        },
                        {
                            id: 'phone',
                            header: t('relocation_requests.phone'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex items-center gap-1.5 text-xs">
                                    <PhoneIcon className="size-3.5" />
                                    {request.phone}
                                </span>
                            ),
                            searchValue: (request) => request.phone,
                        },
                        {
                            id: 'preferred_date',
                            header: t('relocation_requests.preferred_date'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex items-center gap-1.5 text-xs">
                                    <CalendarIcon className="size-3.5" />
                                    {formatDate(request.preferred_date)}
                                </span>
                            ),
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

            <RelocationRequestDetailDialog
                open={selectedRequest !== null}
                onOpenChange={(open) => !open && setSelectedRequest(null)}
                request={selectedRequest}
                statuses={statuses}
                canUpdate={can('service-requests.update')}
            />
        </>
    );
}
