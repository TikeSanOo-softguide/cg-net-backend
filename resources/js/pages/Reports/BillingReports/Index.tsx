import { BackButton } from '@/components/BackButton';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { FormField } from '@/components/ui/form-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useReturnTo } from '@/hooks/useReturnTo';
import { useTranslation } from '@/hooks/useTranslation';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    CalendarDays,
    CircleAlert,
    CircleDotIcon,
    Coins,
    CreditCard,
    Receipt,
    RotateCcw,
    SlidersHorizontal,
    WalletCards,
} from 'lucide-react';
import { FormControl } from '@/components/ui/form-control';

type Filters = {
    mode: 'yearly' | 'date_range';
    year: string;
    from: string;
    to: string;
    status: string;
    method: string;
    package: string;
};

type Props = {
    filters: Filters;
    years: { value: string; label: string }[];
    summary: {
        collected_points: number;
        outstanding_points: number;
        successful_payments: number;
        failed_payments: number;
        refunded_points: number;
    };
    trend: { label: string; points: number; percentage: number }[];
    trendStatus: string;
    trendHasData: boolean;
    methods: { name: string; points: number; percentage: number }[];
    paymentMethods: { value: string; label: string }[];
    packages: { value: string; label: string }[];
};

const formatPoints = (points: number, pointsLabel: string) =>
    `${new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(points)} ${pointsLabel}`;

export default function BillingReportsIndex({
    filters: reportFilters,
    years,
    summary,
    trend,
    trendStatus,
    trendHasData,
    methods,
    paymentMethods,
    packages,
}: Props) {
    const { t, locale } = useTranslation();
    const returnTo = useReturnTo('/reports');
    const [dateError, setDateError] = useState<string>();
    const [isLoading, setIsLoading] = useState(false);
    const [filters, setFilters] = useState<Filters>(reportFilters);

    useEffect(() => {
        setDateError(undefined);
        setFilters({
            mode: reportFilters.mode,
            year: reportFilters.year,
            from: reportFilters.from,
            to: reportFilters.to,
            status: reportFilters.status ?? 'all',
            method: reportFilters.method ?? 'all',
            package: reportFilters.package ?? 'all',
        });
    }, [reportFilters]);

    const applyFilters = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (filters.mode === 'date_range' && filters.from && filters.to && filters.to < filters.from) {
            setDateError(t('billing_report.invalid_date_range'));
            return;
        }

        setDateError(undefined);
        const { mode, year, from, to, status, method, package: packageId } = filters;
        router.get(
            '/reports/billing',
            {
                mode,
                year: mode === 'yearly' ? year : undefined,
                from: mode === 'date_range' ? from : undefined,
                to: mode === 'date_range' ? to : undefined,
                status: status === 'all' ? undefined : status,
                method: method === 'all' ? undefined : method,
                package: packageId === 'all' ? undefined : packageId,
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

    const resetFilters = () => router.get('/reports/billing', {}, { preserveState: false, replace: true });

    const rangeLabel = `${reportFilters.from} – ${reportFilters.to}`;

    const summaryCards = [
        {
            label: t('billing_report.collected_points'),
            value: formatPoints(summary.collected_points, t('billing_report.points')),
            icon: Coins,
        },
        {
            label: t('billing_report.outstanding_balance'),
            value: formatPoints(summary.outstanding_points, t('billing_report.points')),
            icon: WalletCards,
        },
        {
            label: t('billing_report.successful_payments'),
            value: summary.successful_payments.toLocaleString(),
            icon: CreditCard,
        },
        {
            label: t('billing_report.failed_payments'),
            value: summary.failed_payments.toLocaleString(),
            icon: CircleAlert,
        },
        {
            label: t('billing_report.refunds_issued'),
            value: formatPoints(summary.refunded_points, t('billing_report.points')),
            icon: Receipt,
        },
    ];

    const methodColors = ['bg-primary', 'bg-success', 'bg-warning', 'bg-info'];
    const trendStatusLabels: Record<string, string> = {
        successful: t('billing_report.successful_payment_points'),
        pending: t('billing_report.pending_payment_points'),
        failed: t('billing_report.failed_payment_points'),
        refunded: t('billing_report.refunded_points'),
    };

    return (
        <>
            <Head title={t('menu.billing_reports')} />

            <PageContent className="gap-3 lg:gap-3.5">
                <div className="flex items-center justify-between gap-3">
                    <PageHeader title={t('menu.billing_reports')} description={t('menu.billing_reports_description')} />
                    <BackButton href={returnTo} />
                </div>

                <form onSubmit={applyFilters}>
                    <Card>
                        <CardContent className="">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 xl:items-end">
                                <FormField
                                    label={t('billing_report.reporting_period')}
                                    htmlFor="billing-mode"
                                    className="w-full min-w-0"
                                    labelClassName="text-[13px]"
                                >
                                    <FormControl icon={CalendarDays} compact>
                                        <Select
                                            value={filters.mode}
                                            onValueChange={(mode: Filters['mode']) => {
                                                setDateError(undefined);
                                                setFilters({ ...filters, mode });
                                            }}
                                        >
                                            <SelectTrigger id="billing-mode" className="w-full text-[11px]">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
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
                                        htmlFor="billing-year"
                                        className="w-full min-w-0"
                                        labelClassName="text-[13px]"
                                    >
                                        <Select
                                            value={filters.year}
                                            onValueChange={(year) => setFilters({ ...filters, year })}
                                        >
                                            <SelectTrigger id="billing-year" className="w-full text-[11px]">
                                                <SelectValue placeholder={t('billing_report.select_year')} />
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
                                            label={t('common.start_date')}
                                            htmlFor="billing-from"
                                            icon={CalendarDays}
                                            error={dateError}
                                            className="w-full min-w-0"
                                            labelClassName="text-[13px]"
                                        >
                                            <DatePicker
                                                id="billing-from"
                                                value={filters.from}
                                                max={filters.to || undefined}
                                                onChange={(from) => {
                                                    setDateError(undefined);
                                                    setFilters({ ...filters, from });
                                                }}
                                            />
                                        </FormField>

                                        <FormField
                                            label={t('common.end_date')}
                                            htmlFor="billing-to"
                                            icon={CalendarDays}
                                            error={dateError}
                                            className="w-full min-w-0"
                                            labelClassName="text-[13px]"
                                        >
                                            <DatePicker
                                                id="billing-to"
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
                                    label={t('billing_report.payment_method')}
                                    htmlFor="billing-method"
                                    className="w-full min-w-0"
                                    labelClassName="text-[13px]"
                                >
                                    <FormControl icon={CircleDotIcon} compact className="w-full">
                                        <Select
                                            value={filters.method}
                                            onValueChange={(method) => setFilters({ ...filters, method })}
                                        >
                                            <SelectTrigger className="w-full text-[11px]">
                                                <SelectValue placeholder={t('billing_report.payment_method')} />
                                            </SelectTrigger>

                                            <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                                <SelectItem value="all">{t('common.all')}</SelectItem>

                                                {paymentMethods.map((method) => (
                                                    <SelectItem key={method.value} value={method.value}>
                                                        {method.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </FormControl>
                                </FormField>

                                <FormField
                                    label={t('billing_report.package')}
                                    htmlFor="billing-package"
                                    className="w-full min-w-0"
                                    labelClassName="text-[13px]"
                                >
                                    <FormControl icon={CircleDotIcon} compact className="w-full">
                                        <Select
                                            value={filters.package}
                                            onValueChange={(value) => setFilters({ ...filters, package: value })}
                                        >
                                            <SelectTrigger className="w-full text-[11px]">
                                                <SelectValue placeholder={t('billing_report.package')} />
                                            </SelectTrigger>

                                            <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                                <SelectItem value="all">{t('common.all')}</SelectItem>

                                                {packages.map((item) => (
                                                    <SelectItem key={item.value} value={item.value}>
                                                        {item.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </FormControl>
                                </FormField>

                                <FormField
                                    label={'\u00a0'}
                                    htmlFor="billing-actions"
                                    className="w-full min-w-0"
                                    labelClassName="text-[13px]"
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
                                                {t('billing_report.reset')}
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
                                                    ? t('billing_report.loading')
                                                    : t('billing_report.apply_filters')}
                                            </span>
                                        </Button>
                                    </div>
                                </FormField>
                            </div>
                        </CardContent>
                    </Card>
                </form>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    {summaryCards.map(({ label, value, icon: Icon }) => (
                        <Card key={label} className="gap-3 py-4">
                            <CardContent className="flex items-start justify-between">
                                <div>
                                    <p className="text-[12px] text-muted-foreground">{label}</p>
                                    <p className="mt-2 font-heading text-xl font-semibold tracking-tight">{value}</p>
                                    <span className="mt-2 inline-flex text-[12px] text-muted-foreground">
                                        {rangeLabel}
                                    </span>
                                </div>
                                <span className="flex size-9 items-center justify-center rounded-md bg-primary/10 text-primary">
                                    <Icon className="size-4.5" />
                                </span>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid grid-cols-1 gap-4 xl:grid-cols-[1.45fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('billing_report.collection_trend')}</CardTitle>
                            <CardDescription>
                                {trendStatusLabels[trendStatus] ?? trendStatusLabels.successful}{' '}
                                {t('billing_report.trend_period')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {trendHasData ? (
                                <div className="overflow-x-auto">
                                    <div className="flex h-56 min-w-max items-end gap-3 border-b border-border px-2">
                                        {trend.map((item, index) => (
                                            <div
                                                key={`${item.label}-${index}`}
                                                title={`${item.label}: ${formatPoints(item.points, t('billing_report.points'))}`}
                                                className="flex h-full w-fit min-w-20 max-w-32 shrink-0 flex-col items-center justify-end gap-2"
                                            >
                                                <span className="w-full whitespace-normal break-words text-center text-[10px] text-muted-foreground">
                                                    {formatPoints(item.points, t('billing_report.points'))}
                                                </span>
                                                <div className="flex h-36 w-full items-end justify-center rounded-t-md bg-primary/15">
                                                    <div
                                                        className="w-full max-w-17 rounded-t-md bg-primary transition-all hover:bg-primary/80"
                                                        style={{ height: `${item.percentage}%` }}
                                                    />
                                                </div>
                                                <span className="max-w-full whitespace-normal break-words pb-2 text-center text-[11px] text-muted-foreground">
                                                    {item.label}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ) : (
                                <div
                                    className="flex h-56 items-center justify-center text-center text-sm text-muted-foreground"
                                    role="status"
                                >
                                    {t('billing_report.no_data')}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('billing_report.payment_methods')}</CardTitle>
                            <CardDescription>{t('billing_report.payment_methods_description')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {methods.map((method, index) => (
                                <div key={method.name}>
                                    <div className="mb-1.5 flex items-center justify-between gap-3 text-[12px]">
                                        <span className="text-muted-foreground">{method.name}</span>
                                        <span className="font-medium">
                                            {formatPoints(method.points, t('billing_report.points'))} ·{' '}
                                            {method.percentage}%
                                        </span>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className={`h-full rounded-full ${methodColors[index % methodColors.length]}`}
                                            style={{ width: `${method.percentage}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </PageContent>
        </>
    );
}
