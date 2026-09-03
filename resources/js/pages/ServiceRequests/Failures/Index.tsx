import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { AlertTriangleIcon, CheckCircle2Icon, CircleDotIcon, EyeIcon, FilterIcon, SquarePenIcon, Trash2Icon, XIcon } from 'lucide-react';

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
    const [viewingReport, setViewingReport] = useState<FailureReportRow | null>(null);
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
                                    {can('service-requests.view') ? (
                                        <TableActionButton
                                            label={t('common.view')}
                                            icon={EyeIcon}
                                            tone="edit"
                                            onClick={() => setViewingReport(report)}
                                        />
                                    ) : null}
                                </div>
                            </article>
                        ))}
                    </div>

                    <Pagination
                        meta={{
                            from: reports.from,
                            to: reports.to,
                            total: reports.total,
                            links: reports.links,
                        }}
                        summary={t('common.showing')
                            .replace(':from', String(reports.from ?? 0))
                            .replace(':to', String(reports.to ?? 0))
                            .replace(':total', String(reports.total))}
                    />
                </Card>
            </PageContent>

            <FailureReportDetailDialog
                open={Boolean(viewingReport)}
                onOpenChange={(open) => {
                    if (! open) {
                        setViewingReport(null);
                    }
                }}
                report={viewingReport}
            />
        </>
    );
}

function FailureReportDetailDialog({
    open,
    onOpenChange,
    report,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    report: FailureReportRow | null;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [selectedStatus, setSelectedStatus] = useState(report?.status ?? 'under_review');

    useEffect(() => {
        if (report) {
            setSelectedStatus(report.status ?? 'under_review');
        }
    }, [report]);

    if (! report) {
        return null;
    }

    const updateStatusOptions = statusOptions;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={report.customer_name}
            description={report.account_number}
            icon={AlertTriangleIcon}
            size="lg"
        >
            <div className="flex min-h-0 flex-1 flex-col">
                <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="rounded-[12px] border border-border/70 bg-white p-3 dark:bg-[#122326]">
                            <p className="text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">Customer</p>
                            <p className="mt-1 text-[14px] font-semibold text-foreground">{report.customer_name}</p>
                        </div>
                        <div className="rounded-[12px] border border-border/70 bg-white p-3 dark:bg-[#122326]">
                            <p className="text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">Account</p>
                            <p className="mt-1 text-[14px] font-semibold text-foreground">{report.account_number}</p>
                        </div>
                        <div className="rounded-[12px] border border-border/70 bg-white p-3 dark:bg-[#122326]">
                            <p className="text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">Contact</p>
                            <p className="mt-1 text-[14px] font-semibold text-foreground">{report.contact_name} / {report.contact_phone}</p>
                        </div>
                        <div className="rounded-[12px] border border-border/70 bg-white p-3 dark:bg-[#122326]">
                            <p className="text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">Customer Phone</p>
                            <p className="mt-1 text-[14px] font-semibold text-foreground">{report.customer_phone}</p>
                        </div>
                        <div className="rounded-[12px] border border-border/70 bg-white p-3 dark:bg-[#122326]">
                            <p className="text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">Failure Type</p>
                            <p className="mt-1 text-[14px] font-semibold text-foreground">
                                {failureTypeOptions.find((item) => item.value === report.failure_type)?.label ?? report.failure_type}
                            </p>
                        </div>
                        <div className="rounded-[12px] border border-border/70 bg-white p-3 dark:bg-[#122326]">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">Status</p>
                                <StatusBadge status={report.status} />
                            </div>

                            <div className="mt-2 space-y-2">
                                <p className="text-[10px] font-medium uppercase tracking-[0.08em] text-muted-foreground">Previous state</p>
                                <Select value={selectedStatus} onValueChange={setSelectedStatus}>
                                    <SelectTrigger className="h-9 w-full text-[12px]">
                                        <SelectValue placeholder={t('common.status')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {updateStatusOptions.map((item) => (
                                            <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 rounded-[12px] border border-border/70 bg-white p-3 dark:bg-[#122326]">
                        <p className="text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">Description</p>
                        <p className="mt-2 whitespace-pre-wrap text-[13px] leading-6 text-foreground">{report.description}</p>
                    </div>

                    <div className="mt-4 rounded-[12px] border border-border/70 bg-white p-3 dark:bg-[#122326]">
                        <p className="text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">Evidence Photos</p>
                        {report.photos.length > 0 ? (
                            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                {report.photos.map((photo) => (
                                    <div key={photo.id} className="space-y-1">
                                        <img
                                            src={photo.image_url}
                                            alt={photo.label ?? 'Failure report evidence'}
                                            className="h-32 w-full rounded-md border border-border object-cover"
                                        />
                                        {photo.label ? (
                                            <p className="text-[10px] text-muted-foreground">{photo.label}</p>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="mt-2 text-[12px] text-muted-foreground">No evidence photos available.</p>
                        )}
                    </div>
                </div>

                <div className={formActionBarClass}>
                    <Button type="button" size="sm" variant="ghost" className={formActionButtonClass} onClick={() => onOpenChange(false)}>
                        <XIcon className="size-3.5" strokeWidth={1.85} />
                        {t('common.close')}
                    </Button>
                    {can('service-requests.update') ? (
                        <Button type="button" size="sm" className={formActionSubmitClass} onClick={() => {
                            router.patch(`/service-requests/failures/${report.id}/edit`, { status: selectedStatus }, {
                                preserveScroll: true,
                                preserveState: true,
                                onSuccess: () => onOpenChange(false),
                            });
                        }}>
                            <CheckCircle2Icon className="size-3.5" strokeWidth={1.85} />
                            {t('common.update')}
                        </Button>
                    ) : null}
                </div>
            </div>
        </FormDialog>
    );
}
