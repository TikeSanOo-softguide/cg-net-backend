import { JSX, useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CalendarIcon, EyeIcon, HistoryIcon, UserIcon } from 'lucide-react';

import { DataTable } from '@/components/DataTable';
import { FormDialog } from '@/components/FormDialog';
import { SearchableSelect } from '@/components/SearchableSelect';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { TableActionButton } from '@/components/TableActionButton';
import { DatePicker } from '@/components/ui/date-picker';
import { FormField } from '@/components/ui/form-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlStateClass } from '@/lib/form-control';
import { formatDateTime, truncateText } from '@/lib/utils';

type ActivityLogRow = {
    id: number;
    username: string;
    description: string;
    subject: string;
    event: string | null;
    log_name: string | null;
    created_at: string | null;
    meta_data?: Record<string, unknown>;
    changes: ActivityChange[];
};

type ActivityChange = {
    field: string;
    old: unknown;
    new: unknown;
};

type Filters = {
    username: string;
    event: string;
    log: string;
    from: string;
    to: string;
    sort: string;
    direction: 'asc' | 'desc';
};

type ActivityLogProps = {
    logs: Paginated<ActivityLogRow>;
    filters: Filters;
    filterOptions: {
        admins: string[];
        event: string[];
        log: string[];
    };
};

function visitIndex(filters: Filters) {
    router.get(
        '/activity-logs',
        {
            username: filters.username || undefined,
            event: filters.event || undefined,
            log: filters.log || undefined,
            from: filters.from || undefined,
            to: filters.to || undefined,
            sort: filters.sort,
            direction: filters.direction,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

export default function ActivityLogIndex({ logs, filters, filterOptions }: ActivityLogProps) {
    const { t } = useTranslation();
    const [username, setUsername] = useState(filters.username);
    const [event, setEvent] = useState(filters.event);
    const [log, setLog] = useState(filters.log);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [dateRangeError, setDateRangeError] = useState<string>();
    const [selectedLog, setSelectedLog] = useState<ActivityLogRow | null>(null);
    useEffect(() => setUsername(filters.username), [filters.username]);
    useEffect(() => setEvent(filters.event), [filters.event]);
    useEffect(() => setLog(filters.log), [filters.log]);
    useEffect(() => setFrom(filters.from), [filters.from]);
    useEffect(() => setTo(filters.to), [filters.to]);
    const exportLogs = () => {
        const query = new URLSearchParams();

        Object.entries(filters).forEach(([key, value]) => {
            if (value) query.set(key, value);
        });

        window.location.href = `/activity-logs/export?${query.toString()}`;
    };

    const onAdminSelect = (value: string) => {
        setUsername(value);
        visitIndex({ ...filters, username: value });
    };

    const onFilterSelect = (field: 'event' | 'log', value: string) => {
        const nextValue = value === 'all' ? '' : value;
        const nextFilters = { ...filters, [field]: nextValue };
        visitIndex(nextFilters);

        if (field === 'event') {
            setEvent(nextValue);
        } else {
            setLog(nextValue);
        }
    };

    const onDateChange = (field: 'from' | 'to', value: string) => {
        const nextFilters = { ...filters, [field]: value };
        const nextFrom = field === 'from' ? value : from;
        const nextTo = field === 'to' ? value : to;

        if (nextFrom && nextTo && nextTo < nextFrom) {
            setDateRangeError(t('activity_logs.invalid_date_range'));

            return;
        }

        setDateRangeError(undefined);
        field === 'from' ? setFrom(value) : setTo(value);
        visitIndex(nextFilters);
    };

    return (
        <>
            <Head title={t('menu.activity_logs')} />
            <PageContent>
                <PageHeader />
                <DataTable
                    data={logs.data}
                    getRowId={(row) => String(row.id)}
                    showSearch={false}
                    showExport
                    onExport={exportLogs}
                    directActions
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
                                label={t('activity_logs.admin')}
                                htmlFor="activity-log-admin"
                                className="w-full shrink-0 sm:w-60 mr-3"
                                labelClassName="text-[13px]"
                            >
                                <SearchableSelect
                                    value={username}
                                    onValueChange={onAdminSelect}
                                    options={[
                                        { value: '', label: t('common.all') },
                                        ...filterOptions.admins.map((admin) => ({ value: admin, label: admin })),
                                    ]}
                                    placeholder={t('common.all')}
                                    searchPlaceholder={t('common.search')}
                                    triggerClassName="text-[12px]"
                                />
                            </FormField>
                            <FormField
                                label={t('activity_logs.event')}
                                htmlFor="activity-log-event"
                                className="w-full shrink-0 sm:w-40 mr-3"
                                labelClassName="text-[13px]"
                            >
                                <Select
                                    value={event || 'all'}
                                    onValueChange={(value) => onFilterSelect('event', value)}
                                >
                                    <SelectTrigger id="activity-log-event" className="h-8 w-full text-sm">
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
                                label={t('activity_logs.log')}
                                htmlFor="activity-log-name"
                                className="w-full shrink-0 sm:w-40 mr-3"
                                labelClassName="text-[13px]"
                            >
                                <Select value={log || 'all'} onValueChange={(value) => onFilterSelect('log', value)}>
                                    <SelectTrigger id="activity-log-name" className="h-8 w-full text-sm">
                                        <SelectValue placeholder={t('common.all')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {filterOptions.log.map((option) => (
                                            <SelectItem key={option} value={option}>
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label={t('common.start_date')}
                                htmlFor="activity-log-from"
                                error={dateRangeError}
                                icon={CalendarIcon}
                                className="w-full shrink-0 sm:w-40 mr-3"
                                labelClassName="text-[13px]"
                            >
                                <DatePicker
                                    id="activity-log-from"
                                    value={from}
                                    max={to || undefined}
                                    aria-invalid={Boolean(dateRangeError)}
                                    className={formControlStateClass(dateRangeError ? 'error' : 'idle')}
                                    onChange={(value) => onDateChange('from', value)}
                                />
                            </FormField>
                            <FormField
                                label={t('common.end_date')}
                                htmlFor="activity-log-to"
                                error={dateRangeError}
                                icon={CalendarIcon}
                                className="w-full shrink-0 sm:w-40 mr-3"
                                labelClassName="text-[13px]"
                            >
                                <DatePicker
                                    id="activity-log-to"
                                    value={to}
                                    min={from || undefined}
                                    aria-invalid={Boolean(dateRangeError)}
                                    className={formControlStateClass(dateRangeError ? 'error' : 'idle')}
                                    onChange={(value) => onDateChange('to', value)}
                                />
                            </FormField>
                        </>
                    }
                    columns={[
                        {
                            id: 'username',
                            header: t('activity_logs.admin'),
                            className: 'font-medium',
                            mobile: 'title',
                            sortable: true,
                            cell: (row) => row.username,
                        },
                        {
                            id: 'description',
                            header: t('activity_logs.description'),
                            mobile: 'meta',
                            cell: (row) => row.description,
                        },
                        {
                            id: 'subject',
                            header: t('activity_logs.subject'),
                            mobile: 'subtitle',
                            cell: (row) => row.subject || '-',
                        },
                        {
                            id: 'event',
                            header: t('activity_logs.event'),
                            cell: (row) => row.event || '-',
                        },
                        {
                            id: 'log_name',
                            header: t('activity_logs.log'),
                            sortable: true,
                            cell: (row) => row.log_name || '-',
                        },
                        {
                            id: 'created_at',
                            header: t('activity_logs.occurred_at'),
                            className: 'text-muted-foreground',
                            sortable: true,
                            cell: (row) => (row.created_at ? formatDateTime(row.created_at) : '-'),
                        },
                    ]}
                />
            </PageContent>
            <ActivityLogDetailDialog
                activity={selectedLog}
                open={selectedLog !== null}
                onOpenChange={(open) => !open && setSelectedLog(null)}
            />
        </>
    );
}

function ActivityLogDetailDialog({
    activity,
    open,
    onOpenChange,
}: {
    activity: ActivityLogRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    if (!activity) return null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={activity.subject || t('activity_logs.details')}
            description={`${activity.created_at ? formatDateTime(activity.created_at) : '-'}`}
            icon={HistoryIcon}
            size="lg"
        >
            <div className="space-y-3 overflow-y-auto p-4 sm:p-5">
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <LogInfo label={t('activity_logs.admin')} value={activity.username} />
                    <LogInfo label={t('activity_logs.description')} value={activity.description || '-'} />
                    <LogInfo label={t('activity_logs.subject')} value={activity.subject || '-'} />
                    <LogInfo label={t('activity_logs.event')} value={activity.event || '-'} />
                    <LogInfo label={t('activity_logs.log')} value={activity.log_name || '-'} />
                    <LogInfo
                        label={t('activity_logs.occurred_at')}
                        value={activity.created_at ? formatDateTime(activity.created_at) : '-'}
                    />
                </div>

                {activity.meta_data && Object.keys(activity.meta_data).length > 0 ? (
                    <div className="overflow-hidden rounded-xl border border-border/60 bg-muted/10">
                        <div className="border-b border-border/60 bg-muted/20 px-3 py-2 text-xs font-semibold text-muted-foreground sm:px-4">
                            Meta Data
                        </div>
                        <div className="divide-y divide-border/50">{renderMetaRows(activity.meta_data)}</div>
                    </div>
                ) : null}
            </div>
        </FormDialog>
    );
}

function LogInfo({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-1 truncate text-sm font-semibold text-foreground">{value}</p>
        </div>
    );
}

function formatChangeValue(value: unknown): string {
    if (value === null || value === undefined || value === '') return '-';
    if (typeof value === 'object') return JSON.stringify(value);

    return String(value);
}

function renderMetaRows(value: unknown): JSX.Element[] {
    if (value === null || value === undefined) {
        return [
            <div
                key="meta-null"
                className="grid grid-cols-[minmax(140px,0.8fr)_minmax(0,1fr)] gap-3 px-3 py-3 text-xs sm:px-4"
            >
                <span className="font-medium text-foreground">value</span>
                <span className="min-w-0 break-words text-foreground">-</span>
            </div>,
        ];
    }

    if (Array.isArray(value)) {
        return value.map((item, index) => (
            <div
                key={`meta-array-${index}`}
                className="grid grid-cols-[minmax(140px,0.8fr)_minmax(0,1fr)] gap-3 px-3 py-3 text-xs sm:px-4"
            >
                <span className="font-medium text-foreground">[{index}]</span>
                <span className="min-w-0 break-words text-foreground">{formatMetaValue(item)}</span>
            </div>
        ));
    }

    if (typeof value === 'object') {
        return Object.entries(value as Record<string, unknown>).map(([key, child]) => (
            <div
                key={key}
                className="grid grid-cols-[minmax(140px,0.8fr)_minmax(0,1fr)] gap-3 px-3 py-3 text-xs sm:px-4"
            >
                <span className="font-medium text-foreground">{key}</span>
                <span className="min-w-0 break-words text-foreground">{formatMetaValue(child)}</span>
            </div>
        ));
    }

    return [
        <div
            key="meta-scalar"
            className="grid grid-cols-[minmax(140px,0.8fr)_minmax(0,1fr)] gap-3 px-3 py-3 text-xs sm:px-4"
        >
            <span className="font-medium text-foreground">value</span>
            <span className="min-w-0 break-words text-foreground">{formatMetaValue(value)}</span>
        </div>,
    ];
}

function formatMetaValue(value: unknown): string {
    if (value === null || value === undefined || value === '') return '-';
    if (typeof value === 'object') return JSON.stringify(value);

    return String(value);
}
