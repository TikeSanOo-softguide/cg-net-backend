import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { AlertTriangleIcon, CheckCircle2Icon, ChevronDownIcon, CircleDotIcon, FilterIcon, Trash2Icon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { Card } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { EDGE_PAD } from '@/components/data-table/styles';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

import { failureTypeOptions, statusOptions } from './options';

type FailureReportPhoto = {
    id: number;
    image_url: string;
    label?: string | null;
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
    photos: FailureReportPhoto[];
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
};

function visitIndex(filters: Filters) {
    router.get('/service-requests/failures', {
        search: filters.search || undefined,
        status: filters.status || undefined,
        type: filters.type || undefined,
        sort: filters.sort,
        direction: filters.direction,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

export default function FailureReportsIndex({ reports, filters }: FailureReportsIndexProps) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const debounce = useRef<number>(0);
    const canDelete = can('service-requests.delete');

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const onSearchChange = (value: string) => {
        setSearch(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => {
            visitIndex({ ...filters, search: value });
        }, 300);
    };

    return (
        <>
            <Head title={t('menu.failure_reports')} />
            <PageContent>
                <PageHeader />
                <Card className="flex min-h-0 flex-col gap-0 overflow-hidden border-0 py-0 shadow-[0_4px_16px_rgb(23_50_54/0.06)] dark:shadow-[0_4px_16px_rgb(0_0_0/0.22)]">
                    <div className={cn('flex flex-col gap-2.5 py-3 sm:flex-row sm:items-center', EDGE_PAD)}>
                        <SearchInput
                            value={search}
                            onChange={onSearchChange}
                            placeholder={t('common.search')}
                            size="sm"
                            className="w-full sm:max-w-64"
                        />

                        <div className="flex w-full flex-col gap-2 sm:ms-auto sm:w-auto sm:flex-row sm:items-center">
                            <FormControl icon={CircleDotIcon} compact className="w-full shrink-0 sm:w-40">
                                <Select
                                    value={filters.status || 'all'}
                                    onValueChange={(value) => visitIndex({ ...filters, status: value === 'all' ? '' : value })}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder={t('common.status')} />
                                    </SelectTrigger>
                                    <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {statusOptions.map((item) => (
                                            <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormControl icon={FilterIcon} compact className="w-full shrink-0 sm:w-48">
                                <Select
                                    value={filters.type || 'all'}
                                    onValueChange={(value) => visitIndex({ ...filters, type: value === 'all' ? '' : value })}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder={t('common.type')} />
                                    </SelectTrigger>
                                    <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {failureTypeOptions.map((item) => (
                                            <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormControl>
                        </div>
                    </div>

                    <div className={cn(EDGE_PAD, 'grid gap-3 pb-4 lg:grid-cols-2')}>
                        {reports.data.length === 0 ? (
                            <p className="col-span-full py-12 text-center text-[13px] text-muted-foreground">{t('common.no_results')}</p>
                        ) : null}

                        {reports.data.map((report) => (
                            <article
                                key={report.id}
                                className="flex items-start gap-2.5 rounded-[12px] border border-border/70 bg-white p-3 shadow-[0_2px_8px_rgb(23_50_54/0.06)] dark:bg-card dark:shadow-[0_2px_8px_rgb(0_0_0/0.22)] sm:p-4"
                            >
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300">
                                    <AlertTriangleIcon className="size-5" strokeWidth={1.8} />
                                </span>

                                <div className="min-w-0 flex-1 space-y-3">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <h2 className="truncate text-[15px] font-semibold text-primary">{report.customer_name}</h2>
                                            <p className="mt-0.5 text-[12px] text-muted-foreground">{report.account_number}</p>
                                        </div>
                                        <StatusBadge status={report.status} />
                                    </div>

                                    <div className="mt-1.5 flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
                                        <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 font-medium text-primary">
                                            {failureTypeOptions.find((item) => item.value === report.failure_type)?.label ?? report.failure_type}
                                        </span>
                                        <span>{report.created_at}</span>
                                    </div>

                                    <div className="space-y-1 text-[12px] text-muted-foreground">
                                        <p>
                                            <span className="font-medium text-foreground">Contact:</span> {report.contact_name} / {report.contact_phone}
                                        </p>
                                        <p>
                                            <span className="font-medium text-foreground">Customer phone:</span> {report.customer_phone}
                                        </p>
                                        <p className="line-clamp-3">{report.description}</p>
                                    </div>

                                    {report.photos.length > 0 ? (
                                        <div className="space-y-2">
                                            <div className="grid grid-cols-3 gap-2">
                                                {report.photos.slice(0, 3).map((photo) => (
                                                    <div key={photo.id} className="space-y-1">
                                                        <img
                                                            src={photo.image_url}
                                                            alt={photo.label ?? 'Failure report evidence'}
                                                            className="h-20 w-full rounded-md border border-border object-cover"
                                                        />
                                                        {photo.label ? (
                                                            <p className="line-clamp-2 text-[9px] text-muted-foreground">{photo.label}</p>
                                                        ) : null}
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ) : null}
                                </div>

                                <div className="flex shrink-0 items-center gap-1">
                                    {can('service-requests.update') ? (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1.5 rounded-[6px] bg-primary px-2.5 py-1.5 text-[11px] font-medium text-white transition-all duration-200 ease-out hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:outline-none"
                                                    aria-label={t('common.update')}
                                                >
                                                    <span>{t('common.update')}</span>
                                                    <ChevronDownIcon className="size-3.5" strokeWidth={1.8} />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="w-40">
                                                <DropdownMenuItem onSelect={() => {
                                                    router.patch(`/service-requests/failures/${report.id}/status`, { status: 'approved' }, {
                                                        preserveScroll: true,
                                                        preserveState: true,
                                                    });
                                                }}>
                                                    <CheckCircle2Icon className="size-4" strokeWidth={1.8} />
                                                    {t('status.approved')}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem variant="destructive" onSelect={() => {
                                                    router.patch(`/service-requests/failures/${report.id}/status`, { status: 'rejected' }, {
                                                        preserveScroll: true,
                                                        preserveState: true,
                                                    });
                                                }}>
                                                    <span className="flex size-4 items-center justify-center rounded-full border border-current text-[10px]">×</span>
                                                    {t('status.rejected')}
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    ) : null}

                                    {canDelete ? (
                                        <TableActionButton
                                            label={t('common.delete')}
                                            icon={Trash2Icon}
                                            tone="danger"
                                            onClick={() => setPendingIds([report.id])}
                                        />
                                    ) : null}
                                </div>
                            </article>
                        ))}
                    </div>
                </Card>
            </PageContent>
            <ConfirmDialog
                open={pendingIds.length === 1}
                onOpenChange={(open) => {
                    if (! open) {
                        setPendingIds([]);
                    }
                }}
                title={t('customers.delete_title')}
                description={t('customers.delete_description')}
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (pendingIds.length !== 1) {
                        return;
                    }

                    router.delete(`/service-requests/failures/${pendingIds[0]}`, {
                        onFinish: () => setPendingIds([]),
                    });
                }}
            />
        </>
    );
}
