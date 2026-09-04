import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { ArrowRightIcon, CalendarIcon, WifiIcon } from 'lucide-react';

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
import { useTranslation } from '@/hooks/useTranslation';
import { cn, formatDate } from '@/lib/utils';

type Filters = {
    search: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
};

type Props = {
    requests: Paginated<ChangePlanRequestItem>;
    filters: Filters;
    statuses: string[];
};

export default function ChangePlanIndex({ requests, filters, statuses }: Props) {
    const { t } = useTranslation();
    const [selectedRequest, setSelectedRequest] = useState<ChangePlanRequestItem | null>(null);
    const [search, setSearch] = useState(filters.search);
    const debounce = useRef<number>(0);

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const visit = (next: Partial<Filters>) => {
        router.get(
            '/service-requests/change-plan',
            {
                search: (next.search ?? filters.search) || undefined,
                status: (next.status ?? filters.status) || undefined,
                sort: next.sort ?? filters.sort,
                direction: next.direction ?? filters.direction,
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
                <DataTable
                    data={requests.data}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={onSearchChange}
                    searchPlaceholder={t('change_plan.search_placeholder')}
                    pagination={requests}
                    sort={filters.sort}
                    direction={filters.direction}
                    onSort={(column) => {
                        const nextDirection = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
                        visit({ sort: column, direction: nextDirection });
                    }}
                    onView={(row) => setSelectedRequest(row)}
                    directActions
                    filters={
                        <FormControl icon={WifiIcon} compact className="w-full shrink-0 sm:w-48">
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(status) => visit({ status: status === 'all' ? '' : status })}
                            >
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
                            sortable: true,
                            cell: (request) => (
                                <div className="min-w-0">
                                    <p className="truncate font-semibold text-primary mb-1">{request.user.name}</p>
                                    <p className="font-mono text-xs text-muted-foreground">
                                        {request.broadband_account.account_number}
                                    </p>
                                    {(request.contact_name || request.contact_phone) && (
                                        <p className="mt-1 truncate text-[11px] text-muted-foreground">
                                            <span className="font-medium text-foreground">Contact:</span>{' '}
                                            {request.contact_name || request.user.name}
                                            {request.contact_phone && ` / ${request.contact_phone}`}
                                        </p>
                                    )}
                                </div>
                            ),
                            searchValue: (request) =>
                                `${request.user.name} ${request.broadband_account.account_number}`,
                        },
                        {
                            id: 'plans',
                            header: t('change_plan.packages'),
                            mobile: 'subtitle' as const,
                            cell: (request) => (
                                <span className="inline-flex items-center gap-2">
                                    <span className="max-w-[140px] truncate rounded-md bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                                        {packageName(request.current_package)}
                                    </span>
                                    <ArrowRightIcon className="size-4 shrink-0 text-muted-foreground/70" />
                                    <span className="max-w-[140px] truncate rounded-md border border-primary/20 bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                                        {packageName(request.new_package)}
                                    </span>
                                </span>
                            ),
                            searchValue: (request) =>
                                `${packageName(request.current_package)} ${packageName(request.new_package)}`,
                        },
                        {
                            id: 'preferred_date',
                            header: t('change_plan.preferred_date'),
                            mobile: 'meta' as const,
                            sortable: true,
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
                            sortable: true,
                            cell: (request) => (
                                <div className="flex flex-col items-start gap-1">
                                    <StatusBadge status={request.status} />
                                    {request.admin && (
                                        <span className="text-[11px] text-muted-foreground">
                                            Approved By: {request.admin.username}
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
