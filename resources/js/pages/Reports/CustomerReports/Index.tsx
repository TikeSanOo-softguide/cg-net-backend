import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    PieChartIcon,
    RotateCcw,
    SlidersHorizontal,
    Users,
    UserRoundX,
} from 'lucide-react';
import { DonutChart } from '@/components/ui/charts';
import { BackButton } from '@/components/BackButton';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { FormField } from '@/components/ui/form-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useReturnTo } from '@/hooks/useReturnTo';
import { useTranslation } from '@/hooks/useTranslation';
import { FormControl } from '@/components/ui/form-control';

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
    const { t } = useTranslation();
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
        { name: t('status.active'), value: summary.active_users, color: '#22C55E' },
        { name: t('status.suspended'), value: summary.suspended_users, color: '#E11D48' },
    ];
    const broadbandSegments = [
        { name: t('customers.connected'), value: summary.connected_users, color: '#0EA5E9' },
        { name: t('customers.not_connected'), value: summary.not_connected_users, color: '#F59E0B' },
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
                        <CardContent>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 xl:items-end">
                                <FormField
                                    label={t('billing_report.reporting_period')}
                                    htmlFor="customer-report-mode"
                                    labelClassName="text-[13px]"
                                >
                                    <FormControl icon={CalendarDays}>
                                        <Select
                                            value={filters.mode}
                                            onValueChange={(mode: Filters['mode']) => setFilters({ ...filters, mode })}
                                        >
                                            <SelectTrigger id="customer-report-mode" className="h-10 w-full text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent className="[&_[data-slot=select-item]]:text-xs">
                                                <SelectItem value="yearly">{t('billing_report.yearly')}</SelectItem>
                                                <SelectItem value="date_range">
                                                    {t('billing_report.date_range')}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </FormControl>
                                </FormField>
                                {filters.mode === 'yearly' ? (
                                    <FormField
                                        label={t('billing_report.year')}
                                        htmlFor="customer-report-year"
                                        labelClassName="text-[13px]"
                                    >
                                        <Select
                                            value={filters.year}
                                            onValueChange={(year) => setFilters({ ...filters, year })}
                                        >
                                            <SelectTrigger id="customer-report-year" className="h-10 w-full text-xs">
                                                <SelectValue placeholder={t('billing_report.select_year')} />
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
                                            label={t('common.start_date')}
                                            htmlFor="customer-report-from"
                                            icon={CalendarDays}
                                            error={dateError}
                                            labelClassName="text-[13px]"
                                        >
                                            <DatePicker
                                                id="customer-report-from"
                                                value={filters.from}
                                                max={filters.to || undefined}
                                                className="h-10 text-xs"
                                                onChange={(from) => setFilters({ ...filters, from })}
                                            />
                                        </FormField>
                                        <FormField
                                            label={t('common.end_date')}
                                            htmlFor="customer-report-to"
                                            icon={CalendarDays}
                                            error={dateError}
                                            labelClassName="text-[13px]"
                                        >
                                            <DatePicker
                                                id="customer-report-to"
                                                value={filters.to}
                                                min={filters.from || undefined}
                                                className="h-10 text-xs"
                                                onChange={(to) => setFilters({ ...filters, to })}
                                            />
                                        </FormField>
                                    </>
                                )}
                                <FormField
                                    label={'\u00a0'}
                                    htmlFor="customer-report-actions"
                                    className="w-full min-w-0"
                                    labelClassName="text-[13px]"
                                >
                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-10 min-h-10 w-full gap-2"
                                            onClick={resetFilters}
                                            disabled={isLoading}
                                        >
                                            <RotateCcw className="size-4" />
                                            {t('customer_report.reset')}
                                        </Button>
                                        <Button
                                            type="submit"
                                            id="customer-report-actions"
                                            size="sm"
                                            className="h-10 min-h-10 w-full gap-2"
                                            disabled={isLoading}
                                        >
                                            <SlidersHorizontal className="size-4" />
                                            {isLoading
                                                ? t('billing_report.loading')
                                                : t('customer_report.apply_filters')}
                                        </Button>
                                    </div>
                                </FormField>
                            </div>
                        </CardContent>
                    </Card>
                </form>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    {cards.map(({ label, value, icon: Icon }) => (
                        <Card key={label} className="gap-2 py-4">
                            <CardContent className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-xs text-muted-foreground">{label}</p>
                                    <p className="mt-2 break-words font-heading text-xl font-semibold tabular-nums">
                                        {isLoading ? '—' : value.toLocaleString()}
                                    </p>
                                </div>
                                <Icon className="size-4 shrink-0 text-primary" aria-hidden="true" />
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <section className="grid gap-3 lg:grid-cols-2" aria-label={t('menu.customer_reports')}>
                    <DonutChart
                        data={statusSegments}
                        title={t('customer_report.user_status_breakdown')}
                        description={t('customer_report.total_users')}
                        icon={<PieChartIcon className="size-3.5" strokeWidth={1.85} />}
                        total={summary.total_users}
                        totalLabel={t('customer_report.total_users')}
                    />
                    <DonutChart
                        data={broadbandSegments}
                        title={t('customer_report.broadband_breakdown')}
                        description={t('customer_report.total_users')}
                        icon={<PieChartIcon className="size-3.5" strokeWidth={1.85} />}
                        total={summary.total_users}
                        totalLabel={t('customer_report.total_users')}
                    />
                </section>
            </PageContent>
        </>
    );
}
