import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCheck,
    ClipboardList,
    Hourglass,
    RotateCcw,
    SlidersHorizontal,
} from 'lucide-react';
import { Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { BackButton } from '@/components/BackButton';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
    resolution_analysis: {
        type: string;
        label: string;
        total: number;
        average_resolution_time: number | null;
    }[];
};

const numberFormat = new Intl.NumberFormat();

const formatDuration = (seconds: number | null, translate: (key: string) => string) => {
    if (seconds === null) return '—';

    const minutes = seconds / 60;
    if (minutes < 60)
        return `${Math.max(1, Math.round(minutes))} ${translate('service_request_report.duration.minutes')}`;

    const hours = minutes / 60;
    if (hours < 24) return `${hours.toFixed(1)} ${translate('service_request_report.duration.hours')}`;

    return `${(hours / 24).toFixed(1)} ${translate('service_request_report.duration.days')}`;
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
    resolution_analysis,
}: Props) {
    const { t, locale } = useTranslation();
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
                        <CardContent className="pt-5">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 xl:items-end">
                                <FormField
                                    label={t('service_request_report.filters.period')}
                                    htmlFor="service-request-mode"
                                    className="min-w-0"
                                >
                                    <FormControl icon={CalendarDays}>
                                        <Select
                                            value={filters.mode}
                                            onValueChange={(mode: Filters['mode']) => setFilters({ ...filters, mode })}
                                        >
                                            <SelectTrigger id="service-request-mode" className="w-full text-[11px]">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
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
                                    >
                                        <Select
                                            value={filters.year}
                                            onValueChange={(year) => setFilters({ ...filters, year })}
                                        >
                                            <SelectTrigger id="service-request-year" className="w-full text-[11px]">
                                                <SelectValue
                                                    placeholder={t('service_request_report.filters.select_year')}
                                                />
                                            </SelectTrigger>
                                            <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
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
                                            error={dateError}
                                            className="min-w-0"
                                        >
                                            <DatePicker
                                                id="service-request-from"
                                                value={filters.from}
                                                max={filters.to || undefined}
                                                onChange={(from) => {
                                                    setDateError(undefined);
                                                    setFilters({ ...filters, from });
                                                }}
                                            />
                                        </FormField>
                                        <FormField
                                            label={t('service_request_report.filters.end_date')}
                                            htmlFor="service-request-to"
                                            error={dateError}
                                            className="min-w-0"
                                        >
                                            <DatePicker
                                                id="service-request-to"
                                                value={filters.to}
                                                min={filters.from || undefined}
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
                                >
                                    <Select
                                        value={filters.type}
                                        onValueChange={(type) => setFilters({ ...filters, type })}
                                    >
                                        <SelectTrigger id="service-request-type" className="w-full text-[11px]">
                                            <SelectValue
                                                placeholder={t('service_request_report.filters.all_request_types')}
                                            />
                                        </SelectTrigger>
                                        <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
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
                                >
                                    <Select
                                        value={filters.status}
                                        onValueChange={(status) => setFilters({ ...filters, status })}
                                    >
                                        <SelectTrigger id="service-request-status" className="w-full text-[11px]">
                                            <SelectValue
                                                placeholder={t('service_request_report.filters.all_statuses')}
                                            />
                                        </SelectTrigger>
                                        <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
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
                                            className="w-full gap-2"
                                            onClick={resetFilters}
                                        >
                                            <RotateCcw className="size-4" />
                                            <span className={locale === 'my' ? 'text-[11px]' : 'text-sm'}>
                                                {t('service_request_report.actions.reset')}
                                            </span>
                                        </Button>

                                        <Button
                                            type="submit"
                                            id="billing-actions"
                                            className="w-full gap-2"
                                            disabled={isLoading}
                                        >
                                            <SlidersHorizontal className="size-4" />
                                            <span className={locale === 'my' ? 'text-[11px]' : 'text-sm'}>
                                                {isLoading
                                                    ? t('service_request_report.actions.loading')
                                                    : t('service_request_report.actions.apply')}
                                            </span>
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
                        <Card key={label} className="min-h-28 justify-center py-4">
                            <CardContent className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-[12px] text-muted-foreground">{label}</p>
                                    {isLoading ? (
                                        <div className="mt-2 h-7 w-24 animate-pulse rounded bg-muted" role="status" />
                                    ) : (
                                        <p className="mt-2 font-heading text-xl font-semibold">
                                            {typeof value === 'number' ? numberFormat.format(value) : value}
                                        </p>
                                    )}
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {!isLoading && !hasRequests
                                            ? t('service_request_report.summary.no_requests')
                                            : note}
                                    </p>
                                </div>
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                    <Icon className="size-4.5" aria-hidden="true" />
                                </span>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('service_request_report.volume.title')}</CardTitle>
                        <CardDescription>{t('service_request_report.volume.description')}</CardDescription>
                    </CardHeader>
                    {isLoading ? (
                        chartSkeleton
                    ) : hasRequests ? (
                        <CardContent>
                            <div className="h-64 w-full" aria-label={t('service_request_report.volume.title')}>
                                <ResponsiveContainer width="100%" height="100%">
                                    <LineChart
                                        data={request_volume}
                                        margin={{ top: 12, right: 12, bottom: 4, left: 0 }}
                                    >
                                        <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                        <XAxis dataKey="label" tick={{ fontSize: 11 }} minTickGap={12} />
                                        <YAxis allowDecimals={false} width={42} />
                                        <Tooltip
                                            labelFormatter={(label) =>
                                                `${t('service_request_report.chart.period')} ${label}`
                                            }
                                            formatter={(value) => [
                                                numberFormat.format(Number(value ?? 0)),
                                                t('service_request_report.chart.requests'),
                                            ]}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="requests"
                                            name={t('service_request_report.chart.requests')}
                                            stroke="hsl(var(--primary))"
                                            strokeWidth={2}
                                            dot={false}
                                            activeDot={{ r: 4 }}
                                        />
                                    </LineChart>
                                </ResponsiveContainer>
                            </div>
                        </CardContent>
                    ) : (
                        <div
                            className="flex h-64 items-center justify-center px-5 text-center text-sm text-muted-foreground"
                            role="status"
                        >
                            {t('service_request_report.volume.empty')}
                        </div>
                    )}
                </Card>

                <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
                    <Card className="min-h-[340px]">
                        <CardHeader>
                            <CardTitle>{t('service_request_report.types.title')}</CardTitle>
                            <CardDescription>{t('service_request_report.types.description')}</CardDescription>
                        </CardHeader>
                        {isLoading ? (
                            chartSkeleton
                        ) : hasRequests ? (
                            <CardContent>
                                <div className="h-64 w-full" aria-label={t('service_request_report.types.title')}>
                                    <ResponsiveContainer width="100%" height="100%">
                                        <BarChart
                                            data={request_types.map((row) => ({
                                                ...row,
                                                label: requestTypeLabel(row.type, row.label),
                                            }))}
                                            layout="vertical"
                                            margin={{ top: 4, right: 12, bottom: 4, left: 4 }}
                                        >
                                            <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                            <XAxis type="number" allowDecimals={false} />
                                            <YAxis
                                                type="category"
                                                dataKey="label"
                                                width={160}
                                                tick={{ fontSize: 11 }}
                                            />
                                            <Tooltip
                                                formatter={(value) => [
                                                    numberFormat.format(Number(value ?? 0)),
                                                    t('service_request_report.chart.requests'),
                                                ]}
                                            />
                                            <Bar
                                                dataKey="count"
                                                name={t('service_request_report.chart.requests')}
                                                fill="var(--primary)"
                                                radius={[0, 4, 4, 0]}
                                            />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        ) : (
                            <div
                                className="flex h-64 items-center justify-center px-5 text-center text-sm text-muted-foreground"
                                role="status"
                            >
                                {t('service_request_report.types.empty')}
                            </div>
                        )}
                    </Card>

                    <Card className="min-h-[340px]">
                        <CardHeader>
                            <CardTitle>{t('service_request_report.statuses.title')}</CardTitle>
                            <CardDescription>{t('service_request_report.statuses.description')}</CardDescription>
                        </CardHeader>
                        {isLoading ? (
                            chartSkeleton
                        ) : hasRequests ? (
                            <CardContent>
                                <div className="h-64 w-full" aria-label={t('service_request_report.statuses.title')}>
                                    <ResponsiveContainer width="100%" height="100%">
                                        <BarChart
                                            data={request_statuses.map((row) => ({
                                                ...row,
                                                label: statusLabel(row.status),
                                            }))}
                                            layout="vertical"
                                            margin={{ top: 4, right: 12, bottom: 4, left: 4 }}
                                        >
                                            <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                            <XAxis type="number" allowDecimals={false} />
                                            <YAxis
                                                type="category"
                                                dataKey="label"
                                                width={120}
                                                tick={{ fontSize: 11 }}
                                            />
                                            <Tooltip
                                                formatter={(value) => [
                                                    numberFormat.format(Number(value ?? 0)),
                                                    t('service_request_report.chart.requests'),
                                                ]}
                                            />
                                            <Bar
                                                dataKey="count"
                                                name={t('service_request_report.chart.requests')}
                                                fill="var(--success)"
                                                radius={[0, 4, 4, 0]}
                                            />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        ) : (
                            <div
                                className="flex h-64 items-center justify-center px-5 text-center text-sm text-muted-foreground"
                                role="status"
                            >
                                {t('service_request_report.statuses.empty')}
                            </div>
                        )}
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('service_request_report.resolution.title')}</CardTitle>
                        <CardDescription>{t('service_request_report.resolution.description')}</CardDescription>
                    </CardHeader>
                    {isLoading ? (
                        <div
                            className="space-y-3 px-5 pb-5"
                            role="status"
                            aria-label={t('service_request_report.resolution.loading')}
                        >
                            {[0, 1, 2, 3, 4].map((row) => (
                                <div key={row} className="h-8 animate-pulse rounded bg-muted" />
                            ))}
                        </div>
                    ) : hasRequests ? (
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[520px] border-collapse text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-xs text-muted-foreground">
                                            <th scope="col" className="px-3 py-3 font-medium">
                                                {t('service_request_report.resolution.request_type')}
                                            </th>
                                            <th scope="col" className="px-3 py-3 text-right font-medium">
                                                {t('service_request_report.resolution.total')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {resolution_analysis.map((row) => (
                                            <tr key={row.type} className="border-b last:border-0">
                                                <th scope="row" className="px-3 py-3 text-left font-medium">
                                                    {requestTypeLabel(row.type, row.label)}
                                                </th>
                                                <td className="px-3 py-3 text-right tabular-nums">
                                                    {numberFormat.format(row.total)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    ) : (
                        <div className="px-5 pb-5 text-center text-sm text-muted-foreground" role="status">
                            {t('service_request_report.resolution.empty')}
                        </div>
                    )}
                </Card>
            </PageContent>
        </>
    );
}
