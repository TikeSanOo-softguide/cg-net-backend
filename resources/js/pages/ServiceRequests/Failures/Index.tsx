import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    BookX,
    Calendar,
    CircleCheckBig,
    ClipboardList,
    PhoneIcon,
    ScanEye,
    WifiIcon,
    WifiOffIcon,
} from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { formActionBarClass, formActionButtonClass, formActionSubmitClass } from '@/components/FormActionBar';
import type { Paginated } from '@/components/Pagination';
import { Pagination } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { EDGE_PAD } from '@/components/data-table/styles';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { cn, formatDate, truncateText } from '@/lib/utils';
import { CHANGE_PLAN_STATUS } from '@/lib/CommonNameConst';

import { StatCard } from '@/components/StatCard';
import { DataTable } from '@/components/DataTable';
import { StaffListAvatar } from '@/components/staff/StaffListAvatar';
import { FailureReportDetailDialog } from '@/components/service-requests/FailureReport/FailureReportDetailDialog';

type FailureReportPhoto = {
    id: number;
    image_url: string;
    label: string | null;
};

type CustomerPackage = {
    id: number;
    package_id: number;
    expiry_date: string | null;
    start_date: string | null;
    package: {
        id: number;
        price: string;
        network: {
            id: number;
            name_en: string;
            name_zh: string;
            name_my: string;
        } | null;
        speed: {
            id: number;
            mbps: number;
        } | null;
        term: {
            id: number;
            months: number;
        } | null;
    } | null;
};

type FailureReportRow = {
    id: number;
    customer_name: string;
    customer_phone: string;
    account_number: string;
    account_customer: string;
    failure_type: string;
    contact_name: string;
    contact_phone: string;
    description: string;
    status: string;
    created_at: string;
    admin_name: string;
    photos: FailureReportPhoto[];
    customer_packages: CustomerPackage[];
};

type Filters = {
    search: string;
    status: string;
    type: string;
    sort: string;
    direction: 'asc' | 'desc';
};

type FailureReportsIndexProps = {
    reports: Paginated<FailureReportRow>;
    filters: Filters;
    statuses: string[];
    stats: {
        total_requests: number;
        under_reviews_requests: number;
        approved_requests: number;
        cancelled_requests: number;
    };
};

const visit = (filters: Partial<Filters>) => {
    router.get(
        '/service-requests/failures',
        {
            search: (filters.search ?? filters.search) || undefined,
            status: filters.status ?? filters.status,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};
export default function FailureReportsIndex({ reports, filters, statuses, stats }: FailureReportsIndexProps) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const debounce = useRef<number>(0);
    const [selectedRequest, setSelectedRequest] = useState<FailureReportRow | null>(null);

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const onSearchChange = (value: string) => {
        setSearch(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => {
            visit({ search: value });
        }, 300);
    };

    const cards = [
        {
            key: 'relocations.total_requests',
            title: t('requests.total_requests'),
            value: stats.total_requests.toLocaleString(),
            icon: ClipboardList,
        },
        {
            key: 'relocations.under_review_requests',
            title: t('requests.under_review_requests'),
            value: stats.under_reviews_requests.toLocaleString(),
            icon: ScanEye,
        },
        {
            key: 'relocations.approved_requests',
            title: t('requests.approved_requests'),
            value: stats.approved_requests.toLocaleString(),
            icon: CircleCheckBig,
        },
        {
            key: 'relocations.cancelled_requests',
            title: t('requests.cancelled_requests'),
            value: stats.cancelled_requests.toLocaleString(),
            icon: BookX,
        },
    ];

    return (
        <>
            <Head title={t('menu.failure_reports')} />

            <PageContent>
                <PageHeader />
                <StatCard items={cards} className="xl:grid-cols-4" />

                <DataTable
                    data={reports.data}
                    getRowId={(request) => String(request.id)}
                    search={search}
                    onSearchChange={onSearchChange}
                    searchPlaceholder={t('relocation_requests.search')}
                    pagination={reports}
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
                                        <StaffListAvatar username={request.customer_name} />
                                    </div>
                                    <span className="min-w-0 truncate font-medium">
                                        {truncateText(request.customer_name, 20)}
                                    </span>

                                    <p className="truncate font-mono mt-1 text-xs text-muted-foreground">
                                        {request.account_number}
                                    </p>
                                </div>
                            ),
                            searchValue: (request) => `${request.customer_name} ${request.account_number}`,
                        },
                        {
                            id: 'failure_type',
                            header: t('failure_report.failure_type'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex items-center gap-1.5">
                                    <WifiOffIcon className="size-3.5" />
                                    <span>{t(`failure_report.${request.failure_type}`)}</span>
                                </span>
                            ),
                            searchValue: (request) => request.failure_type,
                        },
                        {
                            id: 'description',
                            header: t('cms.description'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex max-w-[260px] items-center gap-1.5">
                                    <span>{truncateText(request.description, 30)}</span>
                                </span>
                            ),
                            searchValue: (request) => request.description,
                        },
                        {
                            id: 'phone',
                            header: t('requests.phone'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex items-center gap-1.5">
                                    <PhoneIcon className="size-3.5" />
                                    {request.contact_phone}
                                </span>
                            ),
                            searchValue: (request) => request.contact_phone,
                        },
                        {
                            id: 'created_at',
                            header: t('requests.requested_date'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex items-center gap-1.5">
                                    <Calendar className="size-3.5" />
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
                                    {request.status === CHANGE_PLAN_STATUS.APPROVED && request.admin_name && (
                                        <span className="text-[11px] text-muted-foreground">
                                            {t('requests.approved_by')}: {request.admin_name}
                                        </span>
                                    )}
                                </div>
                            ),
                            searchValue: (request) => request.status,
                        },
                    ]}
                />
            </PageContent>

            <FailureReportDetailDialog
                open={selectedRequest !== null}
                onOpenChange={(open) => !open && setSelectedRequest(null)}
                request={selectedRequest}
                statuses={statuses}
                canUpdate={can('service-requests.update')}
            />
        </>
    );
}
