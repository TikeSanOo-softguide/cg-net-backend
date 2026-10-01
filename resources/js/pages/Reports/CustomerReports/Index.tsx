import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, RotateCcw, SlidersHorizontal, Users, UserRoundX } from 'lucide-react';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

import { BackButton } from '@/components/BackButton';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
};

type Props = {
    filters: Filters;
    years: { value: string; label: string }[];
    summary: {
        total_users: number;
        active_users: number;
        suspended_users: number;
        connected_users: number;
        not_connected_users: number;
        total_wallet_points: number;
    };
};

type DonutSegment = {
    name: string;
    value: number;
    color: string;
};

export default function CustomerReportsIndex({ filters: reportFilters, years, summary }: Props) {
    const { t, locale } = useTranslation();
    const returnTo = useReturnTo('/reports');
    const [filters, setFilters] = useState(reportFilters);
    const [dateError, setDateError] = useState<string>();
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        setFilters(reportFilters);
        setDateError(undefined);
    }, [reportFilters]);

    const applyFilters = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (filters.mode === 'date_range' && filters.from && filters.to && filters.to < filters.from) {
            setDateError(t('customer_report.invalid_date_range'));
            return;
        }

        setDateError(undefined);
        router.get(
            '/reports/customers',
            {
                mode: filters.mode,
                year: filters.mode === 'yearly' ? filters.year : undefined,
                from: filters.mode === 'date_range' ? filters.from : undefined,
                to: filters.mode === 'date_range' ? filters.to : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setIsLoading(true),
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const resetFilters = () => {
        setDateError(undefined);
        router.get(
            '/reports/customers',
            {},
            {
                preserveState: false,
                replace: true,
                onStart: () => setIsLoading(true),
                onFinish: () => setIsLoading(false),
            },
        );
    };
    const cards = [
        { label: t('customer_report.total_users'), value: summary.total_users, icon: Users },
        { label: t('customer_report.active_users'), value: summary.active_users, icon: CheckCircle2 },
        { label: t('customer_report.suspended_users'), value: summary.suspended_users, icon: UserRoundX },
        { label: t('customer_report.connected_users'), value: summary.connected_users, icon: CheckCircle2 },
        {
            label: t('customer_report.total_wallet_points'),
            value: summary.total_wallet_points,
            icon: Users,
        },
    ];
    const statusSegments = [
        { name: t('status.active'), value: summary.active_users, color: '#16825d' },
        { name: t('status.suspended'), value: summary.suspended_users, color: '#c2415d' },
    ];
    const broadbandSegments = [
        { name: t('customers.connected'), value: summary.connected_users, color: '#087f8c' },
        { name: t('customers.not_connected'), value: summary.not_connected_users, color: '#d97706' },
    ];

    return (
        <>
            <Head title={t('menu.customer_reports')} />
            <PageContent className="gap-3 lg:gap-3.5">
                <div className="flex items-center justify-between gap-3">
                    <PageHeader
                        title={t('menu.customer_reports')}
                        description={t('menu.customer_reports_description')}
                    />
                    <BackButton href={returnTo} />
                </div>

                <form onSubmit={applyFilters}>
                    <Card>
                        <CardContent className="pt-5">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 xl:items-end">
                                <FormField label={t('billing_report.reporting_period')} htmlFor="customer-report-mode">
                                    <FormControl icon={CalendarDays}>
                                        <Select
                                            value={filters.mode}
                                            onValueChange={(mode: Filters['mode']) => setFilters({ ...filters, mode })}
                                        >
                                            <SelectTrigger id="customer-report-mode" className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="yearly">{t('billing_report.yearly')}</SelectItem>
                                                <SelectItem value="date_range">
                                                    {t('billing_report.date_range')}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </FormControl>
                                </FormField>
                                {filters.mode === 'yearly' ? (
                                    <FormField label={t('billing_report.year')} htmlFor="customer-report-year">
                                        <Select
                                            value={filters.year}
                                            onValueChange={(year) => setFilters({ ...filters, year })}
                                        >
                                            <SelectTrigger id="customer-report-year" className="w-full">
                                                <SelectValue placeholder={t('billing_report.select_year')} />
                                            </SelectTrigger>
                                            <SelectContent>
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
                                            label={t('common.start_date')}
                                            htmlFor="customer-report-from"
                                            error={dateError}
                                        >
                                            <FormControl icon={CalendarDays}>
                                                <DatePicker
                                                    id="customer-report-from"
                                                    value={filters.from}
                                                    max={filters.to || undefined}
                                                    onChange={(from) => setFilters({ ...filters, from })}
                                                />
                                            </FormControl>
                                        </FormField>
                                        <FormField
                                            label={t('common.end_date')}
                                            htmlFor="customer-report-to"
                                            error={dateError}
                                        >
                                            <FormControl icon={CalendarDays}>
                                                <DatePicker
                                                    id="customer-report-to"
                                                    value={filters.to}
                                                    min={filters.from || undefined}
                                                    onChange={(to) => setFilters({ ...filters, to })}
                                                />
                                            </FormControl>
                                        </FormField>
                                    </>
                                )}
                                <FormField
                                    label={'\u00a0'}
                                    htmlFor="customer-report-actions"
                                    className="w-full min-w-0"
                                >
                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="w-full gap-2"
                                            onClick={resetFilters}
                                            disabled={isLoading}
                                        >
                                            <RotateCcw className="size-4" />
                                            <span className={locale === 'my' ? 'text-[11px]' : 'text-sm'}>
                                                {t('customer_report.reset')}
                                            </span>
                                        </Button>
                                        <Button
                                            type="submit"
                                            id="customer-report-actions"
                                            className="w-full gap-2"
                                            disabled={isLoading}
                                        >
                                            <SlidersHorizontal className="size-4" />
                                            <span className={locale === 'my' ? 'text-[11px]' : 'text-sm'}>
                                                {isLoading
                                                    ? t('billing_report.loading')
                                                    : t('customer_report.apply_filters')}
                                            </span>
                                        </Button>
                                    </div>
                                </FormField>
                            </div>
                        </CardContent>
                    </Card>
                </form>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    {cards.map(({ label, value, icon: Icon }) => (
                        <Card key={label}>
                            <CardContent className="flex items-center justify-between gap-3 py-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">{label}</p>
                                    <p className="mt-1 text-2xl font-semibold tabular-nums">{value.toLocaleString()}</p>
                                </div>
                                <Icon className="size-5 text-muted-foreground" aria-hidden="true" />
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <section className="grid gap-3 lg:grid-cols-2" aria-label={t('menu.customer_reports')}>
                    <CustomerDonutChart
                        title={t('customer_report.user_status_breakdown')}
                        total={summary.total_users}
                        totalLabel={t('customer_report.total_users')}
                        segments={statusSegments}
                    />
                    <CustomerDonutChart
                        title={t('customer_report.broadband_breakdown')}
                        total={summary.total_users}
                        totalLabel={t('customer_report.total_users')}
                        segments={broadbandSegments}
                    />
                </section>
            </PageContent>
        </>
    );
}

function CustomerDonutChart({
    title,
    total,
    totalLabel,
    segments,
}: {
    title: string;
    total: number;
    totalLabel: string;
    segments: DonutSegment[];
}) {
    const chartSegments = total > 0 ? segments : [{ name: '', value: 1, color: 'var(--muted)' }];

    return (
        <Card className="min-w-0">
            <CardHeader className="pb-0">
                <CardTitle className="text-sm font-semibold">{title}</CardTitle>
            </CardHeader>
            <CardContent className="grid items-center gap-3 sm:grid-cols-2">
                <div className="relative h-56 min-w-0">
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            {total > 0 ? <Tooltip /> : null}
                            <Pie
                                data={chartSegments}
                                dataKey="value"
                                nameKey="name"
                                innerRadius="62%"
                                outerRadius="84%"
                                paddingAngle={total > 0 ? 3 : 0}
                                stroke="var(--background)"
                                strokeWidth={3}
                            >
                                {chartSegments.map((segment, index) => (
                                    <Cell key={`${segment.name}-${index}`} fill={segment.color} />
                                ))}
                            </Pie>
                        </PieChart>
                    </ResponsiveContainer>
                    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span className="text-2xl font-semibold tabular-nums">{total.toLocaleString()}</span>
                        <span className="text-xs text-muted-foreground">{totalLabel}</span>
                    </div>
                </div>
                <ul className="grid gap-3">
                    {segments.map((segment) => (
                        <li key={segment.name} className="flex items-center justify-between gap-3 text-sm">
                            <span className="flex min-w-0 items-center gap-2">
                                <span
                                    aria-hidden="true"
                                    className="size-2.5 shrink-0 rounded-full"
                                    style={{ backgroundColor: segment.color }}
                                />
                                <span className="truncate text-muted-foreground">{segment.name}</span>
                            </span>
                            <span className="font-semibold tabular-nums">{segment.value.toLocaleString()}</span>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
