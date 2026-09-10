import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CalendarIcon, EyeIcon, HistoryIcon, ListFilterPlus, UserIcon } from 'lucide-react';

import { DataTable } from '@/components/DataTable';
import { FormDialog } from '@/components/FormDialog';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { TableActionButton } from '@/components/TableActionButton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { DatePicker } from '@/components/ui/date-picker';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
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
    const [showAdditionalFilters, setShowAdditionalFilters] = useState(Boolean(filters.event || filters.log));
    const [selectedLog, setSelectedLog] = useState<ActivityLogRow | null>(null);
    const debounce = useRef<number>(0);

    useEffect(() => setUsername(filters.username), [filters.username]);
    useEffect(() => setEvent(filters.event), [filters.event]);
    useEffect(() => setLog(filters.log), [filters.log]);
    useEffect(() => setFrom(filters.from), [filters.from]);
    useEffect(() => setTo(filters.to), [filters.to]);
    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const exportLogs = () => {
        const query = new URLSearchParams();

        Object.entries(filters).forEach(([key, value]) => {
            if (value) query.set(key, value);
        });

        window.location.href = `/activity-logs/export?${query.toString()}`;
    };

    const onSearchChange = (value: string) => {
        setUsername(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => visitIndex({ ...filters, username: value }), 300);
    };

    const onFilterSelect = (field: 'event' | 'log', value: string) => {
        const nextValue = value === 'all' ? '' : value;
        const nextFilters = { ...filters, [field]: nextValue };
        const hasAnyAdditionalFilter = Boolean(nextFilters.event || nextFilters.log);

        setShowAdditionalFilters(hasAnyAdditionalFilter);
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
                    search={username}
                    onSearchChange={onSearchChange}
                    searchPlaceholder={t('common.search')}
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
                                icon={UserIcon}
                                className="w-full shrink-0 sm:w-60 mr-3"
                            >
                                <Input
                                    id="activity-log-admin"
                                    value={username}
                                    onChange={(event) => onSearchChange(event.target.value)}
                                    placeholder={t('common.search')}
                                    aria-label={t('activity_logs.admin')}
                                    className={formControlStateClass('idle')}
                                />
                            </FormField>
                            <FormField
                                label={t('common.start_date')}
                                htmlFor="activity-log-from"
                                error={dateRangeError}
                                icon={CalendarIcon}
                                className="w-full shrink-0 sm:w-40 mr-3"
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
                            <div className="shrink-0">
                                <DropdownMenu open={showAdditionalFilters} onOpenChange={setShowAdditionalFilters}>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <DropdownMenuTrigger asChild>
                                                <button
                                                    type="button"
                                                    aria-label={t('activity_logs.additional_filters')}
                                                    className="inline-flex size-8 items-center justify-center rounded-[6px] bg-primary/12 text-primary transition-all duration-200 ease-out hover:scale-105 hover:bg-primary hover:text-primary-foreground hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:outline-none active:scale-95"
                                                >
                                                    <ListFilterPlus className="size-3.5" strokeWidth={1.8} />
                                                </button>
                                            </DropdownMenuTrigger>
                                        </TooltipTrigger>
                                        <TooltipContent side="top" className="bg-primary text-primary-foreground">
                                            {t('activity_logs.additional_filters')}
                                        </TooltipContent>
                                    </Tooltip>
                                    <DropdownMenuContent
                                        align="end"
                                        className="w-[min(92vw,250px)] p-3"
                                        onCloseAutoFocus={(event) => event.preventDefault()}
                                    >
                                        <div>
                                            <FormField
                                                label={t('activity_logs.event')}
                                                htmlFor="activity-log-event"
                                                className="w-full"
                                            >
                                                <Select
                                                    value={event || 'all'}
                                                    onValueChange={(value) => onFilterSelect('event', value)}
                                                >
                                                    <SelectTrigger
                                                        id="activity-log-event"
                                                        className="h-8 w-full text-sm"
                                                    >
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
                                                className="w-full"
                                            >
                                                <Select
                                                    value={log || 'all'}
                                                    onValueChange={(value) => onFilterSelect('log', value)}
                                                >
                                                    <SelectTrigger
                                                        id="activity-log-name"
                                                        className="h-8 w-full text-sm"
                                                    >
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
                                        </div>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
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
            title={t('activity_logs.details')}
            description={`${activity.subject || '-'} · ${activity.created_at || '-'}`}
            icon={HistoryIcon}
            size="lg"
        >
            <div className="space-y-3 overflow-y-auto p-4 sm:p-5">
                <div className="grid gap-3 sm:grid-cols-3">
                    <LogInfo label={t('activity_logs.admin')} value={activity.username} />
                    <LogInfo label={t('activity_logs.event')} value={activity.event || '-'} />
                    <LogInfo label={t('activity_logs.log')} value={activity.log_name || '-'} />
                </div>
                <div className="overflow-hidden rounded-xl border border-border/60 bg-muted/10">
                    <div className="grid grid-cols-[minmax(120px,0.8fr)_minmax(0,1fr)_minmax(0,1fr)] border-b border-border/60 bg-muted/20 px-3 py-2 text-xs font-semibold text-muted-foreground sm:px-4">
                        <span>{t('activity_logs.field')}</span>
                        <span>{t('activity_logs.old_value')}</span>
                        <span>{t('activity_logs.new_value')}</span>
                    </div>
                    {activity.changes.length > 0 ? (
                        activity.changes.map((change) => (
                            <div
                                key={change.field}
                                className="grid grid-cols-[minmax(120px,0.8fr)_minmax(0,1fr)_minmax(0,1fr)] border-b border-border/50 px-3 py-3 text-xs last:border-b-0 sm:px-4"
                            >
                                <span className="font-medium text-foreground">{change.field}</span>
                                <span className="min-w-0 break-words text-muted-foreground">
                                    {formatChangeValue(change.old)}
                                </span>
                                <span className="min-w-0 break-words text-foreground">
                                    {formatChangeValue(change.new)}
                                </span>
                            </div>
                        ))
                    ) : (
                        <p className="p-4 text-sm text-muted-foreground">{t('activity_logs.no_changes')}</p>
                    )}
                </div>
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
