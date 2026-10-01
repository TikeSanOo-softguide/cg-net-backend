import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    CalendarDays,
    CreditCard,
    RotateCcw,
    SlidersHorizontal,
    TicketCheck,
    TicketPlus,
} from 'lucide-react';
import {
    Cell,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

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
const chartColors = [
    'var(--primary)',
    'var(--success)',
    'var(--warning)',
    'var(--info)',
    'var(--destructive)',
    'var(--chart-1)',
];

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
    const { t, locale } = useTranslation();
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
                        <CardContent className="pt-5">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 xl:items-end">
                                <FormField
                                    label={t('top_up_report.reporting_period')}
                                    htmlFor="top-up-mode"
                                    className="min-w-0"
                                >
                                    <FormControl icon={CalendarDays}>
                                        <Select
                                            value={filters.mode}
                                            onValueChange={(mode: Filters['mode']) => setFilters({ ...filters, mode })}
                                        >
                                            <SelectTrigger id="top-up-mode" className="w-full text-[11px]">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
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
                                    >
                                        <Select
                                            value={filters.year}
                                            onValueChange={(year) => setFilters({ ...filters, year })}
                                        >
                                            <SelectTrigger id="top-up-year" className="w-full text-[11px]">
                                                <SelectValue placeholder={t('top_up_report.select_year')} />
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
                                            htmlFor="top-up-from"
                                            error={dateError}
                                            className="min-w-0"
                                        >
                                            <DatePicker
                                                id="top-up-from"
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
                                            htmlFor="top-up-to"
                                            error={dateError}
                                            className="min-w-0"
                                        >
                                            <DatePicker
                                                id="top-up-to"
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
                                    label={t('top_up_report.office')}
                                    htmlFor="top-up-office"
                                    className="min-w-0"
                                >
                                    <Select
                                        value={filters.office}
                                        onValueChange={(office) => setFilters({ ...filters, office })}
                                    >
                                        <SelectTrigger id="top-up-office" className="w-full text-[11px]">
                                            <SelectValue placeholder={t('top_up_report.office')} />
                                        </SelectTrigger>
                                        <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
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
                                >
                                    <Select
                                        value={filters.amount}
                                        onValueChange={(amount) => setFilters({ ...filters, amount })}
                                    >
                                        <SelectTrigger id="top-up-amount" className="w-full text-[11px]">
                                            <SelectValue placeholder={t('top_up_report.denomination')} />
                                        </SelectTrigger>
                                        <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                            <SelectItem value="all">{t('top_up_report.all_denominations')}</SelectItem>
                                            {amounts.map((amount) => (
                                                <SelectItem key={amount} value={amount}>
                                                    {numberFormat.format(Number(amount))}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>

                                <FormField label={'\u00a0'} htmlFor="top-up-report-actions" className="w-full min-w-0">
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
                                                {t('top_up_report.reset')}
                                            </span>
                                        </Button>
                                        <Button
                                            type="submit"
                                            id="top-up-report-actions"
                                            className="w-full gap-2"
                                            disabled={isLoading}
                                        >
                                            <SlidersHorizontal className="size-4" />
                                            <span className={locale === 'my' ? 'text-[11px]' : 'text-sm'}>
                                                {isLoading
                                                    ? t('top_up_report.loading')
                                                    : t('top_up_report.apply_filters')}
                                            </span>
                                        </Button>
                                    </div>
                                </FormField>
                            </div>
                        </CardContent>
                    </Card>
                </form>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {summaryCards.map(({ label, value, icon: Icon }) => (
                        <Card key={label} className="min-h-28 justify-center py-4">
                            <CardContent className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-[12px] text-muted-foreground">{label}</p>
                                    <p className="mt-2 font-heading text-xl font-semibold">
                                        {isLoading ? '—' : numberFormat.format(value)}
                                    </p>
                                </div>
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                    <Icon className="size-4.5" aria-hidden="true" />
                                </span>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid grid-cols-1 gap-3 xl:grid-cols-[1.45fr_1fr]">
                    <Card className="min-h-[340px]">
                        <CardHeader>
                            <CardTitle>{t('top_up_report.activity_title')}</CardTitle>
                            <CardDescription>{t('top_up_report.activity_description')}</CardDescription>
                        </CardHeader>
                        {isLoading ? (
                            <div className="h-64 animate-pulse bg-muted/50" role="status" />
                        ) : hasCards ? (
                            <CardContent>
                                <div className="h-64 w-full">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <LineChart data={trend} margin={{ top: 12, right: 12, bottom: 4, left: 0 }}>
                                            <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                            <XAxis dataKey="label" tick={{ fontSize: 11 }} minTickGap={12} />
                                            <YAxis allowDecimals={false} width={42} />
                                            <Tooltip
                                                formatter={(value, name) => [
                                                    numberFormat.format(Number(value ?? 0)),
                                                    name === 'generated'
                                                        ? t('top_up_report.generated')
                                                        : t('top_up_report.redeemed'),
                                                ]}
                                            />
                                            <Legend
                                                formatter={(value) =>
                                                    value === 'generated'
                                                        ? t('top_up_report.generated')
                                                        : t('top_up_report.redeemed')
                                                }
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="generated"
                                                name="generated"
                                                stroke="var(--primary)"
                                                strokeWidth={2}
                                                dot={false}
                                                activeDot={{ r: 4 }}
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="redeemed"
                                                name="redeemed"
                                                stroke="var(--success)"
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
                                {t('top_up_report.no_data')}
                            </div>
                        )}
                    </Card>

                    <Card className="min-h-[340px]">
                        <CardHeader>
                            <CardTitle>{t('top_up_report.office_distribution')}</CardTitle>
                            <CardDescription>{t('top_up_report.office_distribution_description')}</CardDescription>
                        </CardHeader>
                        {isLoading ? (
                            <div className="h-64 animate-pulse bg-muted/50" role="status" />
                        ) : hasCards ? (
                            <CardContent>
                                <div className="h-64 w-full">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <PieChart>
                                            <Pie
                                                data={pieData}
                                                dataKey="cards"
                                                nameKey="name"
                                                cx="50%"
                                                cy="46%"
                                                innerRadius={58}
                                                outerRadius={92}
                                                paddingAngle={2}
                                            >
                                                {pieData.map((office, index) => (
                                                    <Cell
                                                        key={`${office.name}-${index}`}
                                                        fill={chartColors[index % chartColors.length]}
                                                    />
                                                ))}
                                            </Pie>
                                            <Tooltip
                                                formatter={(value, _name, item) => [
                                                    `${numberFormat.format(Number(value ?? 0))} · ${item.payload.percentage}%`,
                                                    t('top_up_report.cards'),
                                                ]}
                                            />
                                            <Legend verticalAlign="bottom" height={36} />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        ) : (
                            <div
                                className="flex h-64 items-center justify-center px-5 text-center text-sm text-muted-foreground"
                                role="status"
                            >
                                {t('top_up_report.no_data')}
                            </div>
                        )}
                    </Card>
                </div>
            </PageContent>
        </>
    );
}
