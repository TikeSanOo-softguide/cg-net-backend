import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    BookX,
    CalendarIcon,
    CircleCheckBig,
    ClipboardList,
    MapPinIcon,
    PhoneIcon,
    ScanEye,
    WifiIcon,
} from 'lucide-react';

import { BroadbandApplicationDetailDialog } from '@/components/service-requests/BroadbandApplication/BroadbandApplicationDetailDialog';
import { DataTable } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatCard } from '@/components/StatCard';
import { StatusBadge } from '@/components/StatusBadge';
import { StaffListAvatar } from '@/components/staff/StaffListAvatar';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDate, truncateText } from '@/lib/utils';

type BroadbandApplication = {
    id: number;
    phone: string;
    address: string;
    note: string | null;
    status: string;
    created_at: string;
    id_type: string;
    id_name: string;
    id_number: string;
    photos: {
        id: number;
        image_url: string;
    }[];
    user: {
        id: number;
        name: string;
        phone: string;
    } | null;
    area: {
        id: number;
        name_en: string;
        name_zh: string;
        name_my: string;
        region?: {
            id: number;
            name_en: string;
            name_zh: string;
            name_my: string;
            state?: {
                id: number;
                name_en: string;
                name_zh: string;
                name_my: string;
            } | null;
        } | null;
    } | null;
    package: {
        id: number;
        price?: string | number;
        network?: {
            id: number;
            name_en: string;
            name_zh: string;
            name_my: string;
        } | null;
        speed?: {
            id: number;
            mbps: number;
        } | null;
        term?: {
            id: number;
            months: number;
        } | null;
    } | null;
    admin: {
        id: number;
        username: string;
    } | null;
};

type Filters = {
    search: string;
    status: string;
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

function packageName(pkg: BroadbandApplication['package']): string {
    if (!pkg) {
        return 'N/A';
    }

    const parts = [
        pkg.network?.name_en ?? pkg.network?.name_my ?? pkg.network?.name_zh ?? null,
        pkg.speed ? `${pkg.speed.mbps} Mbps` : null,
        pkg.term ? `${pkg.term.months} months` : null,
    ].filter(Boolean);

    return parts.length > 0 ? parts.join(' - ') : `Package #${pkg.id}`;
}

function areaName(area: BroadbandApplication['area']): string {
    if (!area) {
        return 'N/A';
    }

    const parts = [
        area.name_en ?? area.name_my ?? area.name_zh ?? null,
        area.region?.name_en ?? area.region?.name_my ?? area.region?.name_zh ?? null,
        area.region?.state?.name_en ?? area.region?.state?.name_my ?? area.region?.state?.name_zh ?? null,
    ].filter(Boolean);

    return parts.length > 0 ? parts.join(' - ') : `Area #${area.id}`;
}

export default function BroadbandApplicationIndex({ requests, filters, statuses, stats }: Props) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [selectedRequest, setSelectedRequest] = useState<BroadbandApplication | null>(null);
    const debounce = useRef<number>(0);

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
                    searchPlaceholder={t('common.search')}
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
                                        <StaffListAvatar
                                            username={(request.user?.name ?? request.id_name) || 'Customer'}
                                        />
                                    </div>
                                    <span className="min-w-0 truncate font-medium">
                                        {truncateText(request.user?.name ?? request.id_name, 20)}
                                    </span>

                                    <p className="mt-1 truncate font-mono text-muted-foreground">
                                        {request.id_number || request.phone}
                                    </p>
                                </div>
                            ),
                            searchValue: (request) =>
                                `${request.user?.name ?? ''} ${request.id_name} ${request.id_number} ${request.phone}`,
                        },
                        {
                            id: 'package',
                            header: t('requests.requested_packages'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex max-w-[260px] items-center gap-1.5">
                                    <span>{truncateText(packageName(request.package) ?? '—', 40)}</span>
                                </span>
                            ),
                            searchValue: (request) => `${packageName(request.package)}`,
                        },
                        {
                            id: 'address',
                            header: t('requests.contact_information'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <div className="min-w-0 space-y-1">
                                    <div className="flex min-w-0 items-center gap-2">
                                        <MapPinIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                        <span className="truncate">{truncateText(request.address, 35)}</span>
                                    </div>

                                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <span>({request.phone})</span>
                                    </div>
                                </div>
                            ),
                            searchValue: (request) => request.phone,
                        },
                        {
                            id: 'area',
                            header: t('broadband_application.location'),
                            mobile: 'meta' as const,
                            sortable: true,
                            cell: (request) => (
                                <span className="inline-flex max-w-[260px] items-center gap-1.5">
                                    <span> {truncateText(areaName(request.area) ?? '—', 35)}</span>
                                </span>
                            ),
                            searchValue: (request) => `${areaName(request.area)}`,
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
                onOpenChange={(open) => !open && setSelectedRequest(null)}
                request={selectedRequest}
                canUpdate={can('service-requests.update')}
                statuses={statuses}
            />
        </>
    );
}
