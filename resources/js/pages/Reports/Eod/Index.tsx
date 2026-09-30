import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, CircleDollarSign, FileCheck2, Wallet } from 'lucide-react';

import { BackButton } from '@/components/BackButton';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { DatePicker } from '@/components/ui/date-picker';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormField } from '@/components/ui/form-field';
import { useTranslation } from '@/hooks/useTranslation';
import { TOP_UP_CARD_CURRENCY } from '@/lib/top-up-cards';
import type { SupportedLocale } from '@/types';
import { formatDateTime } from '@/lib/utils';

type EntryBreakdown = {
    type: string;
    entries: number;
    credits: number;
    debits: number;
};

type RecentEntry = {
    id: number;
    transaction_no: string;
    customer_id: number | null;
    customer: string | null;
    transaction_type: string;
    direction: 'credit' | 'debit';
    amount: number;
    balance_after: number;
    created_at: string;
};

type Props = {
    date: string;
    summary: {
        entries: number;
        credits: number;
        debits: number;
        net: number;
        completed_transactions: number;
        pending_transactions: number;
        failed_transactions: number;
    };
    breakdown: EntryBreakdown[];
    recentEntries: RecentEntry[];
};

const localeTags: Record<SupportedLocale, string> = {
    en: 'en-US',
    my: 'my-MM',
    zh: 'zh-CN',
};

function amount(value: number, locale: SupportedLocale): string {
    return `${new Intl.NumberFormat(localeTags[locale]).format(value)} ${TOP_UP_CARD_CURRENCY}`;
}

function transactionHref(transactionNo: string): string {
    const query = new URLSearchParams({ search: transactionNo, open_transaction: transactionNo });

    return `/billing/transactions?${query.toString()}`;
}

export default function EodReportIndex({ date, summary, breakdown, recentEntries }: Props) {
    const { t, locale } = useTranslation();
    const numberFormat = new Intl.NumberFormat(localeTags[locale]);
    const metrics = [
        {
            label: t('eod_report.ledger_entries'),
            value: numberFormat.format(summary.entries),
            icon: FileCheck2,
        },
        { label: t('eod_report.credits'), value: amount(summary.credits, locale), icon: CircleDollarSign },
        { label: t('eod_report.debits'), value: amount(summary.debits, locale), icon: Wallet },
        { label: t('eod_report.net_movement'), value: amount(summary.net, locale), icon: CircleDollarSign },
        {
            label: t('eod_report.completed_transactions'),
            value: numberFormat.format(summary.completed_transactions),
            icon: FileCheck2,
        },
        {
            label: t('eod_report.pending_transactions'),
            value: numberFormat.format(summary.pending_transactions),
            icon: CalendarDays,
        },
        {
            label: t('eod_report.failed_transactions'),
            value: numberFormat.format(summary.failed_transactions),
            icon: CalendarDays,
        },
    ];

    const transactionTypeLabel = (type: string) => {
        const key = `transactions.types.${type}`;
        const translated = t(key);

        return translated === key ? type.replaceAll('_', ' ') : translated;
    };

    return (
        <>
            <Head title={t('menu.eod_reports')} />
            <PageContent className="gap-3 lg:gap-3.5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <PageHeader title={t('menu.eod_reports')} description={t('menu.eod_reports_description')} />
                    <BackButton href="/reports" />
                </div>

                <div className="flex flex-wrap items-end justify-between gap-3">
                    <FormField label={t('eod_report.date')} htmlFor="eod-date" className="w-full sm:w-64">
                        <DatePicker
                            id="eod-date"
                            value={date}
                            onChange={(value) =>
                                router.get('/reports/eod', { date: value }, { preserveScroll: true, replace: true })
                            }
                        />
                    </FormField>
                    <p className="text-xs text-muted-foreground">{t('eod_report.latest_entries')}</p>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {metrics.map(({ label, value, icon: Icon }) => (
                        <Card key={label} className="gap-2 py-4">
                            <CardContent className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-xs text-muted-foreground">{label}</p>
                                    <p className="mt-2 break-words font-heading text-xl font-semibold tabular-nums">
                                        {value}
                                    </p>
                                </div>
                                <Icon className="size-4 shrink-0 text-muted-foreground" />
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('eod_report.breakdown')}</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        {breakdown.length ? (
                            <table className="w-full min-w-[600px] text-left text-sm">
                                <thead className="border-b border-border text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">{t('eod_report.transaction_type')}</th>
                                        <th className="px-3 py-2 text-right font-medium">{t('eod_report.entries')}</th>
                                        <th className="px-3 py-2 text-right font-medium">{t('eod_report.credits')}</th>
                                        <th className="px-3 py-2 text-right font-medium">{t('eod_report.debits')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {breakdown.map((item) => (
                                        <tr key={item.type} className="border-b border-border/70 last:border-0">
                                            <td className="px-3 py-2.5">{transactionTypeLabel(item.type)}</td>
                                            <td className="px-3 py-2.5 text-right tabular-nums">
                                                {numberFormat.format(item.entries)}
                                            </td>
                                            <td className="px-3 py-2.5 text-right tabular-nums">
                                                {amount(item.credits, locale)}
                                            </td>
                                            <td className="px-3 py-2.5 text-right tabular-nums">
                                                {amount(item.debits, locale)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <p className="py-5 text-sm text-muted-foreground">{t('eod_report.no_breakdown')}</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('eod_report.recent_entries')}</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        {recentEntries.length ? (
                            <table className="w-full min-w-[760px] text-left text-sm">
                                <thead className="border-b border-border text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">{t('eod_report.transaction_no')}</th>
                                        <th className="px-3 py-2 font-medium">{t('eod_report.customer')}</th>
                                        <th className="px-3 py-2 font-medium">{t('eod_report.transaction_type')}</th>
                                        <th className="px-3 py-2 font-medium">{t('eod_report.direction')}</th>
                                        <th className="px-3 py-2 text-right font-medium">{t('eod_report.amount')}</th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            {t('eod_report.balance_after')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">{t('eod_report.created_at')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentEntries.map((entry) => (
                                        <tr key={entry.id} className="border-b border-border/70 last:border-0">
                                            <td className="px-3 py-2.5 font-mono text-xs">
                                                <Link
                                                    href={transactionHref(entry.transaction_no)}
                                                    className="text-primary underline-offset-4 hover:underline"
                                                >
                                                    {entry.transaction_no}
                                                </Link>
                                            </td>
                                            <td className="px-3 py-2.5">
                                                {entry.customer && entry.customer_id ? (
                                                    <Link
                                                        href={`/customers/${entry.customer_id}`}
                                                        className="text-primary underline-offset-4 hover:underline"
                                                    >
                                                        {entry.customer}
                                                    </Link>
                                                ) : (
                                                    (entry.customer ?? '—')
                                                )}
                                            </td>
                                            <td className="px-3 py-2.5">
                                                {transactionTypeLabel(entry.transaction_type)}
                                            </td>
                                            <td className="px-3 py-2.5">{t(`eod_report.${entry.direction}`)}</td>
                                            <td className="px-3 py-2.5 text-right tabular-nums">
                                                {amount(entry.amount, locale)}
                                            </td>
                                            <td className="px-3 py-2.5 text-right tabular-nums">
                                                {amount(entry.balance_after, locale)}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2.5 text-xs text-muted-foreground">
                                                {formatDateTime(entry.created_at)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <p className="py-5 text-sm text-muted-foreground">{t('eod_report.no_entries')}</p>
                        )}
                    </CardContent>
                </Card>
            </PageContent>
        </>
    );
}
