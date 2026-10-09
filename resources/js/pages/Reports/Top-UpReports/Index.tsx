import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    CalendarDays,
    CreditCard,
    PieChartIcon,
    RotateCcw,
    SlidersHorizontal,
    TicketCheck,
    TicketPlus,
    TrendingUpIcon,
} from 'lucide-react';
import { DonutChart, TrendChart } from '@/components/ui/charts';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { FormControl } from '@/components/ui/form-control';
import { FormField } from '@/components/ui/form-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

type Filters = {
    mode: 'yearly' | 'date_range';
    year: string;
    from: string;
    to: string;
    office: string;
    amount: string;
};

type Props = {
    filters: Filters;
    years: { value: string; label: string }[];
    summary: { total_cards: number; used_cards: number; left_cards: number };
    trend: { label: string; generated: number; redeemed: number }[];
    hasCards: boolean;
    officeDistribution: { name: string | null; code: string | null; cards: number; percentage: number }[];
    offices: { value: string; label: string; code: string | null }[];
    amounts: string[];
};

const numberFormat = new Intl.NumberFormat();
const chartColors = ['#EC4899', '#3B82F6', '#F97316', '#22C55E', '#8B5CF6', '#06B6D4'];

export default function TopUpReportsIndex({
    filters: reportFilters,
    years,
    summary,
    trend,
    hasCards,
    officeDistribution,
    offices,
    amounts,
}: Props) {
    const { t } = useTranslation();
    const [filters, setFilters] = useState(reportFilters);
    const [dateError, setDateError] = useState<string>();
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        setFilters(reportFilters);
        setDateError(undefined);
    }, [reportFilters]);

    const resetFilters = () => router.get('/reports/top-ups', {}, { preserveState: false, replace: true });

    const applyFilters = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (filters.mode === 'date_range' && filters.from && filters.to && filters.to < filters.from) {
            setDateError(t('top_up_report.invalid_date_range'));
            return;
        }

        setDateError(undefined);
        router.get(
            '/reports/top-ups',
            {
                mode: filters.mode,
                year: filters.mode === 'yearly' ? filters.year : undefined,
                from: filters.mode === 'date_range' ? filters.from : undefined,
                to: filters.mode === 'date_range' ? filters.to : undefined,
                office: filters.office === 'all' ? undefined : filters.office,
                amount: filters.amount === 'all' ? undefined : filters.amount,
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

    const summaryCards = [
        {
            label: t('top_up_report.total_cards'),
            value: summary.total_cards,
            icon: TicketPlus,
        },
        {
            label: t('top_up_report.used_cards'),
            value: summary.used_cards,
            icon: TicketCheck,
        },
        {
            label: t('top_up_report.left_cards'),
            value: summary.left_cards,
            icon: CreditCard,
        },
    ];
    const pieData = officeDistribution.map((office) => ({
        ...office,
        name: office.name ? `${office.name}${office.code ? ` (${office.code})` : ''}` : t('top_up_report.unassigned'),
    }));

    return (
        <>
            <Head title={t('menu.top_up_reports')} />

            <PageContent className="gap-3 lg:gap-3.5">
                <div className="flex items-center justify-between gap-3">
                    <PageHeader title={t('menu.top_up_reports')} description={t('menu.top_up_reports_description')} />
                    <Button type="button" size="sm" className="gap-1.5" onClick={() => router.visit('/reports')}>
                        <ArrowLeftIcon className="size-3.5" strokeWidth={1.9} />
                        {t('common.back')}
                    </Button>
                </div>

                <form onSubmit={applyFilters}>
                    <Card>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 xl:items-end">
                                <FormField
                                    label={t('top_up_report.reporting_period')}
                                    htmlFor="top-up-mode"
                                    className="min-w-0"
                                    labelClassName="text-[13px]"
                                >
                                    <FormControl icon={CalendarDays}>
                                        <Select
                                            value={filters.mode}
                                            onValueChange={(mode: Filters['mode']) => setFilters({ ...filters, mode })}
                                        >
                                            <SelectTrigger id="top-up-mode" className="h-10 w-full text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent className="[&_[data-slot=select-item]]:text-xs">
                                                <SelectItem value="yearly">{t('top_up_report.yearly')}</SelectItem>
                                                <SelectItem value="date_range">
                                                    {t('top_up_report.date_range')}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </FormControl>
                                </FormField>

                                {filters.mode === 'yearly' ? (
                                    <FormField
                                        label={t('top_up_report.year')}
                                        htmlFor="top-up-year"
                                        className="min-w-0"
                                        labelClassName="text-[13px]"
                                    >
                                        <Select
                                            value={filters.year}
                                            onValueChange={(year) => setFilters({ ...filters, year })}
                                        >
                                            <SelectTrigger id="top-up-year" className="h-10 w-full text-xs">
                                                <SelectValue placeholder={t('top_up_report.select_year')} />
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
                                            htmlFor="top-up-from"
                                            icon={CalendarDays}
                                            error={dateError}
                                            className="min-w-0"
                                            labelClassName="text-[13px]"
                                        >
                                            <DatePicker
                                                id="top-up-from"
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
                                            label={t('common.end_date')}
                                            htmlFor="top-up-to"
                                            icon={CalendarDays}
                                            error={dateError}
                                            className="min-w-0"
                                            labelClassName="text-[13px]"
                                        >
                                            <DatePicker
                                                id="top-up-to"
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
                                    label={t('top_up_report.office')}
                                    htmlFor="top-up-office"
                                    className="min-w-0"
                                    labelClassName="text-[13px]"
                                >
                                    <Select
                                        value={filters.office}
                                        onValueChange={(office) => setFilters({ ...filters, office })}
                                    >
                                        <SelectTrigger id="top-up-office" className="h-10 w-full text-xs">
                                            <SelectValue placeholder={t('top_up_report.office')} />
                                        </SelectTrigger>
                                        <SelectContent className="[&_[data-slot=select-item]]:text-xs">
                                            <SelectItem value="all">{t('top_up_report.all_offices')}</SelectItem>
                                            {offices.map((office) => (
                                                <SelectItem key={office.value} value={office.value}>
                                                    {office.label}
                                                    {office.code ? ` (${office.code})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>

                                <FormField
                                    label={t('top_up_report.denomination')}
                                    htmlFor="top-up-amount"
                                    className="min-w-0"
                                    labelClassName="text-[13px]"
                                >
                                    <Select
                                        value={filters.amount}
                                        onValueChange={(amount) => setFilters({ ...filters, amount })}
                                    >
                                        <SelectTrigger id="top-up-amount" className="h-10 w-full text-xs">
                                            <SelectValue placeholder={t('top_up_report.denomination')} />
                                        </SelectTrigger>
                                        <SelectContent className="[&_[data-slot=select-item]]:text-xs">
                                            <SelectItem value="all">{t('top_up_report.all_denominations')}</SelectItem>
                                            {amounts.map((amount) => (
                                                <SelectItem key={amount} value={amount}>
                                                    {numberFormat.format(Number(amount))}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>

                                <FormField
                                    label={'\u00a0'}
                                    htmlFor="top-up-report-actions"
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
                                            {t('top_up_report.reset')}
                                        </Button>
                                        <Button
                                            type="submit"
                                            id="top-up-report-actions"
                                            size="sm"
                                            className="h-10 min-h-10 w-full gap-2"
                                            disabled={isLoading}
                                        >
                                            <SlidersHorizontal className="size-4" />
                                            {isLoading ? t('top_up_report.loading') : t('top_up_report.apply_filters')}
                                        </Button>
                                    </div>
                                </FormField>
                            </div>
                        </CardContent>
                    </Card>
                </form>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {summaryCards.map(({ label, value, icon: Icon }) => (
                        <Card key={label} className="gap-2 py-4">
                            <CardContent className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-xs text-muted-foreground">{label}</p>
                                    <p className="mt-2 break-words font-heading text-xl font-semibold tabular-nums">
                                        {isLoading ? '—' : numberFormat.format(value)}
                                    </p>
                                </div>
                                <Icon className="size-4 shrink-0 text-primary" aria-hidden="true" />
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid grid-cols-1 gap-3 xl:grid-cols-[1.45fr_1fr]">
                    <TrendChart
                        data={trend.map((point) => ({
                            date: point.label,
                            generated: point.generated,
                            redeemed: point.redeemed,
                        }))}
                        title={t('top_up_report.activity_title')}
                        description={t('top_up_report.activity_description')}
                        icon={<TrendingUpIcon className="size-3.5" strokeWidth={1.85} />}
                        showLegend
                        series={[
                            {
                                dataKey: 'generated',
                                label: t('top_up_report.generated'),
                                color: '#4F46E5',
                            },
                            {
                                dataKey: 'redeemed',
                                label: t('top_up_report.redeemed'),
                                color: '#14B8A6',
                            },
                        ]}
                    />

                    <DonutChart
                        data={pieData.map((office, index) => ({
                            name: office.name ?? t('top_up_report.unassigned'),
                            value: office.cards,
                            color: chartColors[index % chartColors.length],
                        }))}
                        title={t('top_up_report.office_distribution')}
                        description={t('top_up_report.office_distribution_description')}
                        icon={<PieChartIcon className="size-3.5" strokeWidth={1.85} />}
                        total={summary.total_cards}
                        totalLabel={t('top_up_report.total_cards')}
                    />
                </div>
            </PageContent>
        </>
    );
}
