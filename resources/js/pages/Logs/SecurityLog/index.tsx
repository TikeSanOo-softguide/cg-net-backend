import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CalendarIcon, EyeIcon, HistoryIcon } from 'lucide-react';

import { DataTable } from '@/components/DataTable';
import { FormDialog } from '@/components/FormDialog';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { TableActionButton } from '@/components/TableActionButton';
import { DatePicker } from '@/components/ui/date-picker';
import { FormField } from '@/components/ui/form-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { useCan } from '@/hooks/useCan';
import { formControlStateClass } from '@/lib/form-control';
import { formatDateTime } from '@/lib/utils';

type SecurityLogRow = {
    id: number;
    actor: {
        id: number;
        type: string;
        name: string;
    } | null;
    event: string;
    ip_address: string | null;
    user_agent: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
};

type Filters = {
    event: string;
    from: string;
    to: string;
    sort: string;
    direction: 'asc' | 'desc';
};

type SecurityLogProps = {
    logs: Paginated<SecurityLogRow>;
    filters: Filters;
    filterOptions: {
        event: string[];
    };
};

function visitIndex(filters: Filters) {
    router.get(
        '/logs/security',
        {
            event: filters.event || undefined,
            from: filters.from || undefined,
            to: filters.to || undefined,
            sort: filters.sort,
            direction: filters.direction,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

export default function SecurityLogIndex({ logs, filters, filterOptions }: SecurityLogProps) {
    const { t } = useTranslation();
    const can = useCan();
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [event, setEvent] = useState(filters.event);
    const [dateRangeError, setDateRangeError] = useState<string>();
    const [selectedLog, setSelectedLog] = useState<SecurityLogRow | null>(null);

    useEffect(() => setFrom(filters.from), [filters.from]);
    useEffect(() => setTo(filters.to), [filters.to]);
    useEffect(() => setEvent(filters.event), [filters.event]);

    const onEventSelect = (value: string) => {
        const nextEvent = value === 'all' ? '' : value;
        setEvent(nextEvent);
        visitIndex({ ...filters, event: nextEvent });
    };

    const exportLogs = () => {
        const query = new URLSearchParams();

        Object.entries(filters).forEach(([key, value]) => {
            if (value) query.set(key, value);
        });

        window.location.href = `/logs/security/export?${query.toString()}`;
    };

    const onDateChange = (field: 'from' | 'to', value: string) => {
        const nextFilters = { ...filters, [field]: value };
        const nextFrom = field === 'from' ? value : from;
        const nextTo = field === 'to' ? value : to;

        if (nextFrom && nextTo && nextTo < nextFrom) {
            setDateRangeError(t('security_logs.invalid_date_range'));
            return;
        }

        setDateRangeError(undefined);
        field === 'from' ? setFrom(value) : setTo(value);
        visitIndex(nextFilters);
    };

    return (
        <>
            <Head title={t('menu.security_logs')} />
            <PageContent>
                <PageHeader title={t('menu.security_logs')} />
                <DataTable
                    data={logs.data}
                    getRowId={(row) => String(row.id)}
                    showSearch={false}
                    showExport={can('system.export')}
                    onExport={can('system.export') ? exportLogs : undefined}
                    directActions
                    pagination={logs}
                    sort={filters.sort}
                    direction={filters.direction}
                    onSort={(column) =>
                        visitIndex({
                            ...filters,
                            sort: column,
                            direction: filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc',
                        })
                    }
                    filters={
                        <>
                            <FormField
                                label={t('security_logs.event')}
                                htmlFor="security-log-event"
                                className="mr-3 w-full shrink-0 sm:w-40"
                                labelClassName="text-[13px]"
                            >
                                <Select value={event || 'all'} onValueChange={onEventSelect}>
                                    <SelectTrigger id="security-log-event" className="h-8 w-full text-sm">
                                        <SelectValue placeholder={t('common.all')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {filterOptions.event.map((option) => (
                                            <SelectItem key={option} value={option}>
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label={t('common.start_date')}
                                htmlFor="security-log-from"
                                error={dateRangeError}
                                icon={CalendarIcon}
                                className="mr-3 w-full shrink-0 sm:w-40"
                                labelClassName="text-[13px]"
                            >
                                <DatePicker
                                    id="security-log-from"
                                    value={from}
                                    max={to || undefined}
                                    aria-invalid={Boolean(dateRangeError)}
                                    className={formControlStateClass(dateRangeError ? 'error' : 'idle')}
                                    onChange={(value) => onDateChange('from', value)}
                                />
                            </FormField>
                            <FormField
                                label={t('common.end_date')}
                                htmlFor="security-log-to"
                                error={dateRangeError}
                                icon={CalendarIcon}
                                className="mr-3 w-full shrink-0 sm:w-40"
                                labelClassName="text-[13px]"
                            >
                                <DatePicker
                                    id="security-log-to"
                                    value={to}
                                    min={from || undefined}
                                    aria-invalid={Boolean(dateRangeError)}
                                    className={formControlStateClass(dateRangeError ? 'error' : 'idle')}
                                    onChange={(value) => onDateChange('to', value)}
                                />
                            </FormField>
                        </>
                    }
                    actions={(row) => (
                        <TableActionButton
                            label={t('common.view')}
                            icon={EyeIcon}
                            onClick={(event) => {
                                event.stopPropagation();
                                setSelectedLog(row);
                            }}
                        />
                    )}
                    columns={[
                        {
                            id: 'actor',
                            header: t('security_logs.actor'),
                            cell: (row) => {
                                return row.actor?.name || 'User';
                            },
                        },

                        {
                            id: 'event',
                            header: t('security_logs.event'),
                            className: 'font-medium',
                            sortable: true,
                            cell: (row) => row.event,
                        },
                        {
                            id: 'ip_address',
                            header: t('security_logs.ip_address'),
                            sortable: true,
                            cell: (row) => row.ip_address || '-',
                        },
                        {
                            id: 'created_at',
                            header: t('security_logs.date'),
                            className: 'text-muted-foreground',
                            sortable: true,
                            cell: (row) => (row.created_at ? formatDateTime(row.created_at) : '-'),
                        },
                    ]}
                />
            </PageContent>

            <SecurityLogDetailDialog
                log={selectedLog}
                open={selectedLog !== null}
                onOpenChange={(open) => !open && setSelectedLog(null)}
            />
        </>
    );
}

function SecurityLogDetailDialog({
    log,
    open,
    onOpenChange,
}: {
    log: SecurityLogRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    if (!log) return null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('security_logs.details')}
            description={log.created_at ? formatDateTime(log.created_at) : '-'}
            icon={HistoryIcon}
            size="lg"
        >
            <div className="space-y-3 overflow-y-auto p-4 sm:p-5">
                <div className="grid gap-3 sm:grid-cols-2">
                    <LogInfo label={t('security_logs.event')} value={log.event || '-'} />
                    <LogInfo label={t('security_logs.actor')} value={log.actor?.name || t('security_logs.system')} />
                    <LogInfo label={t('security_logs.actor_type')} value={log.actor?.type || '-'} />
                    <LogInfo label={t('security_logs.ip_address')} value={log.ip_address || '-'} />
                    <LogInfo
                        label={t('security_logs.date')}
                        value={log.created_at ? formatDateTime(log.created_at) : '-'}
                    />
                </div>

                <LogInfo label={t('security_logs.user_agent')} value={log.user_agent || '-'} />

                {log.metadata && Object.keys(log.metadata).length > 0 ? (
                    <div className="mt-4 overflow-hidden rounded-lg border border-border/60 bg-muted/10">
                        <div className="border-b border-border/60 bg-muted/20 px-3 py-2 text-xs font-semibold text-muted-foreground sm:px-4">
                            {t('security_logs.metadata')}
                        </div>
                        <div className="divide-y divide-border/50">
                            {Object.entries(log.metadata).map(([key, value]) => (
                                <div
                                    key={key}
                                    className="grid grid-cols-[minmax(140px,0.8fr)_minmax(0,1fr)] gap-3 px-3 py-3 text-xs sm:px-4"
                                >
                                    <span className="font-medium text-foreground">{key}</span>
                                    <span className="min-w-0 break-words text-foreground">
                                        {formatMetadataValue(value)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}
            </div>
        </FormDialog>
    );
}

function formatMetadataValue(value: unknown): string {
    if (value === null || value === undefined || value === '') return '-';
    if (typeof value === 'object') return JSON.stringify(value);

    return String(value);
}

function LogInfo({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-border/60 bg-muted/20 p-3.5">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-1 break-words text-sm font-semibold text-foreground">{value}</p>
        </div>
    );
}
