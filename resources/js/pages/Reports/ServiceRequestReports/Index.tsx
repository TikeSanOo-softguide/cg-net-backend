import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    BarChart3Icon,
    CalendarDays,
    CheckCheck,
    ClipboardList,
    Hourglass,
    RotateCcw,
    SlidersHorizontal,
    TrendingUpIcon,
} from 'lucide-react';
import { BarListChart, TrendChart } from '@/components/ui/charts';
import { BackButton } from '@/components/BackButton';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { FormControl } from '@/components/ui/form-control';
import { FormField } from '@/components/ui/form-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useReturnTo } from '@/hooks/useReturnTo';
import { useTranslation } from '@/hooks/useTranslation';

type Filters = {
    mode: 'yearly' | 'date_range';
    year: string;
    from: string;
    to: string;
    type: string;
    status: string;
};

type Props = {
    filters: Filters;
    years: { value: string; label: string }[];
    statuses: string[];
    summary: {
        total_requests: number;
        pending_requests: number;
        completed_requests: number;
    };
    request_volume: { label: string; requests: number }[];
    request_types: { type: string; label: string; count: number }[];
    request_statuses: { status: string; label: string; count: number }[];
};

const numberFormat = new Intl.NumberFormat();

const requestStatusColors: Record<string, string> = {
    approved: '#22C55E',
    cancelled: '#64748B',
    completed: '#22C55E',
    failed: '#E11D48',
    in_progress: '#3B82F6',
    pending: '#F59E0B',
    rejected: '#E11D48',
    under_review: '#F59E0B',
};

const titleCaseStatus = (status: string) =>
    status
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

export default function ServiceRequestReportsIndex({
    filters: reportFilters,
    years,
    statuses,
    summary,
    request_volume,
    request_types,
    request_statuses,
}: Props) {
    const { t } = useTranslation();
    const returnTo = useReturnTo('/reports');
    const [filters, setFilters] = useState(reportFilters);
    const [dateError, setDateError] = useState<string>();
    const [reportError, setReportError] = useState<string>();
    const [isLoading, setIsLoading] = useState(false);
    const statusLabel = (status: string) => {
        const key = `status.${status}`;
        const translated = t(key);

        return translated === key ? titleCaseStatus(status) : translated;
    };
    const requestTypeLabel = (type: string, fallback: string) => {
        const key = `service_request_report.types.${type}`;
        const translated = t(key);

        return translated === key ? fallback : translated;
    };

    useEffect(() => {
        setFilters(reportFilters);
        setDateError(undefined);
    }, [reportFilters]);

    const resetFilters = () => router.get('/reports/service-requests', {}, { preserveState: false, replace: true });

    const applyFilters = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (filters.mode === 'date_range' && filters.from && filters.to && filters.to < filters.from) {
            setDateError(t('service_request_report.errors.invalid_date_range'));
            return;
        }

        setDateError(undefined);
        setReportError(undefined);
        router.get(
            '/reports/service-requests',
            {
                mode: filters.mode,
                year: filters.mode === 'yearly' ? filters.year : undefined,
                from: filters.mode === 'date_range' ? filters.from : undefined,
                to: filters.mode === 'date_range' ? filters.to : undefined,
                type: filters.type === 'all' ? undefined : filters.type,
                status: filters.status === 'all' ? undefined : filters.status,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setIsLoading(true),
                onError: (errors) => {
                    setReportError(Object.values(errors)[0] ?? t('service_request_report.errors.load'));
                },
                onHttpException: () => {
                    setReportError(t('service_request_report.errors.http'));
                    return false;
                },
                onNetworkError: () => {
                    setReportError(t('service_request_report.errors.network'));
                    return false;
                },
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const summaryCards = [
        {
            label: t('service_request_report.summary.total_requests'),
            value: summary.total_requests,
            note: t('service_request_report.summary.total_note'),
            icon: ClipboardList,
        },
        {
            label: t('service_request_report.summary.pending_requests'),
            value: summary.pending_requests,
            note: t('service_request_report.summary.pending_note'),
            icon: Hourglass,
        },
        {
            label: t('service_request_report.summary.completed_requests'),
            value: summary.completed_requests,
            note: t('service_request_report.summary.completed_note'),
            icon: CheckCheck,
        },
    ];

    const hasRequests = summary.total_requests > 0;
    const chartSkeleton = (
        <div
            className="flex h-64 items-end gap-3 px-5 pb-5"
            aria-label={t('service_request_report.loading_chart')}
            role="status"
        >
            {[42, 68, 53, 84, 61, 75, 48, 90, 57, 72, 46, 65].map((height, index) => (
                <div key={index} className="flex-1 animate-pulse rounded-t bg-muted" style={{ height: `${height}%` }} />
            ))}
        </div>
    );

    return (
        <>
            <Head title={t('menu.service_request_reports')} />

            <PageContent className="gap-3 lg:gap-3.5">
                <div className="flex items-center justify-between gap-3">
                    <PageHeader
                        title={t('menu.service_request_reports')}
                        description={t('menu.service_request_reports_description')}
                    />
                    <BackButton href={returnTo} />
                </div>

                <form onSubmit={applyFilters}>
                    <Card>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 xl:items-end">
                                <FormField
                                    label={t('service_request_report.filters.period')}
                                    htmlFor="service-request-mode"
                                    className="min-w-0"
                                    labelClassName="text-[13px]"
                                >
                                    <FormControl icon={CalendarDays}>
                                        <Select
                                            value={filters.mode}
                                            onValueChange={(mode: Filters['mode']) => setFilters({ ...filters, mode })}
                                        >
                                            <SelectTrigger id="service-request-mode" className="h-10 w-full text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent className="[&_[data-slot=select-item]]:text-xs">
                                                <SelectItem value="yearly">
                                                    {t('service_request_report.filters.yearly')}
                                                </SelectItem>
                                                <SelectItem value="date_range">
                                                    {t('service_request_report.filters.date_range')}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </FormControl>
                                </FormField>

                                {filters.mode === 'yearly' ? (
                                    <FormField
                                        label={t('service_request_report.filters.year')}
                                        htmlFor="service-request-year"
                                        className="min-w-0"
                                        labelClassName="text-[13px]"
                                    >
                                        <Select
                                            value={filters.year}
                                            onValueChange={(year) => setFilters({ ...filters, year })}
                                        >
                                            <SelectTrigger id="service-request-year" className="h-10 w-full text-xs">
                                                <SelectValue
                                                    placeholder={t('service_request_report.filters.select_year')}
                                                />
                                            </SelectTrigger>
                                            <SelectContent className="[&_[data-slot=select-item]]:text-xs">
                                                {years.map((year) => (
                                                    <SelectItem key={year.value} value={year.value}>
                                                        {year.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </FormField>
                                ) : (
                                    <>
                                        <FormField
                                            label={t('service_request_report.filters.start_date')}
                                            htmlFor="service-request-from"
                                            icon={CalendarDays}
                                            error={dateError}
                                            className="min-w-0"
                                            labelClassName="text-[13px]"
                                        >
                                            <DatePicker
                                                id="service-request-from"
                                                value={filters.from}
                                                max={filters.to || undefined}
                                                className="text-xs"
                                                onChange={(from) => {
                                                    setDateError(undefined);
                                                    setFilters({ ...filters, from });
                                                }}
                                            />
                                        </FormField>
                                        <FormField
                                            label={t('service_request_report.filters.end_date')}
                                            htmlFor="service-request-to"
                                            icon={CalendarDays}
                                            error={dateError}
                                            className="min-w-0"
                                            labelClassName="text-[13px]"
                                        >
                                            <DatePicker
                                                id="service-request-to"
                                                value={filters.to}
                                                min={filters.from || undefined}
                                                className="text-xs"
                                                onChange={(to) => {
                                                    setDateError(undefined);
                                                    setFilters({ ...filters, to });
                                                }}
                                            />
                                        </FormField>
                                    </>
                                )}

                                <FormField
                                    label={t('service_request_report.filters.request_type')}
                                    htmlFor="service-request-type"
                                    className="min-w-0"
                                    labelClassName="text-[13px]"
                                >
                                    <Select
                                        value={filters.type}
                                        onValueChange={(type) => setFilters({ ...filters, type })}
                                    >
                                        <SelectTrigger id="service-request-type" className="h-10 w-full text-xs">
                                            <SelectValue
                                                placeholder={t('service_request_report.filters.all_request_types')}
                                            />
                                        </SelectTrigger>
                                        <SelectContent className="[&_[data-slot=select-item]]:text-xs">
                                            <SelectItem value="all">
                                                {t('service_request_report.actions.all')}
                                            </SelectItem>
                                            {request_types.map(({ type, label }) => (
                                                <SelectItem key={type} value={type}>
                                                    {requestTypeLabel(type, label)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>

                                <FormField
                                    label={t('service_request_report.filters.status')}
                                    htmlFor="service-request-status"
                                    className="min-w-0"
                                    labelClassName="text-[13px]"
                                >
                                    <Select
                                        value={filters.status}
                                        onValueChange={(status) => setFilters({ ...filters, status })}
                                    >
                                        <SelectTrigger id="service-request-status" className="h-10 w-full text-xs">
                                            <SelectValue
                                                placeholder={t('service_request_report.filters.all_statuses')}
                                            />
                                        </SelectTrigger>
                                        <SelectContent className="[&_[data-slot=select-item]]:text-xs">
                                            <SelectItem value="all">
                                                {t('service_request_report.actions.all')}
                                            </SelectItem>
                                            {statuses.map((status) => (
                                                <SelectItem key={status} value={status}>
                                                    {statusLabel(status)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>

                                <FormField
                                    label={'\u00a0'}
                                    htmlFor="service-request-report-actions"
                                    className="w-full min-w-0 gap-2"
                                    labelClassName="text-[13px]"
                                >
                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-10 min-h-10 w-full gap-2"
                                            onClick={resetFilters}
                                        >
                                            <RotateCcw className="size-4" />
                                            {t('service_request_report.actions.reset')}
                                        </Button>

                                        <Button
                                            type="submit"
                                            id="billing-actions"
                                            size="sm"
                                            className="h-10 min-h-10 w-full gap-2"
                                            disabled={isLoading}
                                        >
                                            <SlidersHorizontal className="size-4" />
                                            {isLoading
                                                ? t('service_request_report.actions.loading')
                                                : t('service_request_report.actions.apply')}
                                        </Button>
                                    </div>
                                </FormField>
                            </div>
                            {reportError ? (
                                <p className="mt-3 text-sm text-destructive" role="alert">
                                    {reportError}
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>
                </form>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {summaryCards.map(({ label, value, note, icon: Icon }) => (
                        <Card key={label} className="gap-2 py-4">
                            <CardContent className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-xs text-muted-foreground">{label}</p>
                                    {isLoading ? (
                                        <div className="mt-2 h-7 w-24 animate-pulse rounded bg-muted" role="status" />
                                    ) : (
                                        <p className="mt-2 break-words font-heading text-xl font-semibold tabular-nums">
                                            {typeof value === 'number' ? numberFormat.format(value) : value}
                                        </p>
                                    )}
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {!isLoading && !hasRequests
                                            ? t('service_request_report.summary.no_requests')
                                            : note}
                                    </p>
                                </div>
                                <Icon className="size-4 shrink-0 text-primary" aria-hidden="true" />
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <TrendChart
                    data={request_volume.map((row) => ({
                        date: row.label,
                        requests: row.requests,
                    }))}
                    title={t('service_request_report.volume.title')}
                    description={t('service_request_report.volume.description')}
                    icon={<TrendingUpIcon className="size-3.5" strokeWidth={1.85} />}
                    showLegend
                    series={[
                        {
                            dataKey: 'requests',
                            label: t('service_request_report.chart.requests'),
                            color: '#4F46E5',
                        },
                    ]}
                />

                <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
                    <BarListChart
                        data={request_types.map((row, index) => ({
                            label: requestTypeLabel(row.type, row.label),
                            value: row.count,
                            color: ['#4F46E5', '#E11D48', '#0EA5E9', '#14B8A6', '#F59E0B'][index % 5],
                        }))}
                        title={t('service_request_report.types.title')}
                        description={t('service_request_report.types.description')}
                        icon={<BarChart3Icon className="size-3.5" strokeWidth={1.85} />}
                        total={request_types.reduce((sum, row) => sum + row.count, 0)}
                    />

                    <BarListChart
                        data={request_statuses.map((row, index) => ({
                            label: statusLabel(row.status),
                            value: row.count,
                            color: requestStatusColors[row.status] ?? ['#4F46E5', '#0EA5E9', '#8B5CF6'][index % 3],
                        }))}
                        title={t('service_request_report.statuses.title')}
                        description={t('service_request_report.statuses.description')}
                        icon={<BarChart3Icon className="size-3.5" strokeWidth={1.85} />}
                        total={request_statuses.reduce((sum, row) => sum + row.count, 0)}
                    />
                </div>
            </PageContent>
        </>
    );
}
