import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarDays, CircleDollarSign, EyeIcon, FileCheck2, Wallet } from 'lucide-react';
import { ArrowDownToLine, ArrowUpFromLine, BookOpen, CircleCheck, CircleX, Clock3, Scale } from 'lucide-react';

import { BackButton } from '@/components/BackButton';
import { DataTable, type DataTableColumn } from '@/components/DataTable';
import { FormDialog } from '@/components/FormDialog';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import type { Paginated } from '@/components/Pagination';
import { TableActionButton } from '@/components/TableActionButton';
import { DatePicker } from '@/components/ui/date-picker';
import { Card, CardContent } from '@/components/ui/card';
import { FormField } from '@/components/ui/form-field';
import { useReturnTo } from '@/hooks/useReturnTo';
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

type LedgerEntryRow = {
    id: number;
    transaction_no: string;
    customer_id: number | null;
    customer: string | null;
    transaction_type: string;
    direction: 'credit' | 'debit';
    amount: number;
    balance_after: number;
    created_at: string;
    ledger_entries: LedgerLine[];
};

type LedgerLine = {
    id: number;
    line_no: number;
    created_at: string;
    account_code: string | null;
    account_name: string | null;
    customer: string | null;
    debit: number;
    credit: number;
    balance_after: number | null;
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
    entries: Paginated<LedgerEntryRow>;
};

const localeTags: Record<SupportedLocale, string> = {
    en: 'en-US',
    my: 'my-MM',
    zh: 'zh-CN',
};

function amount(value: number, locale: SupportedLocale): string {
    return `${new Intl.NumberFormat(localeTags[locale]).format(value)} ${TOP_UP_CARD_CURRENCY}`;
}

function failedTransactionsHref(date: string): string {
    const query = new URLSearchParams({ status: 'failed', from: date, to: date });

    return `/billing/transactions?${query.toString()}`;
}

function transactionHref(transactionNo: string): string {
    const query = new URLSearchParams({ search: transactionNo, open_transaction: transactionNo });

    return `/billing/transactions?${query.toString()}`;
}

export default function EodReportIndex({ date, summary, breakdown, entries }: Props) {
    const { t, locale } = useTranslation();
    const returnTo = useReturnTo('/reports');
    const numberFormat = new Intl.NumberFormat(localeTags[locale]);
    const [selectedEntry, setSelectedEntry] = useState<LedgerEntryRow | null>(null);
    const metrics = [
        {
            label: t('eod_report.ledger_entries'),
            value: numberFormat.format(summary.entries),
            icon: BookOpen,
        },
        { label: t('eod_report.wallet_inflow'), value: amount(summary.credits, locale), icon: ArrowDownToLine },
        { label: t('eod_report.wallet_outflow'), value: amount(summary.debits, locale), icon: ArrowUpFromLine },
        { label: t('eod_report.net_movement'), value: amount(summary.net, locale), icon: Scale },
        {
            label: t('eod_report.completed_transactions'),
            value: numberFormat.format(summary.completed_transactions),
            icon: CircleCheck,
            statusColor: 'text-success',
        },
        {
            label: t('eod_report.pending_transactions'),
            value: numberFormat.format(summary.pending_transactions),
            icon: Clock3,
            statusColor: 'text-warning',
        },
        {
            label: t('eod_report.failed_transactions'),
            value: numberFormat.format(summary.failed_transactions),
            icon: CircleX,
            statusColor: 'text-danger',
            href: failedTransactionsHref(date),
        },
    ];

    const transactionTypeLabel = (type: string) => {
        const key = `transactions.types.${type}`;
        const translated = t(key);

        return translated === key ? type.replaceAll('_', ' ') : translated;
    };
    const entryColumns: DataTableColumn<LedgerEntryRow>[] = [
        {
            id: 'transaction_no',
            header: t('eod_report.transaction_no'),
            mobile: 'title',
            cell: (entry) => (
                <Link
                    href={transactionHref(entry.transaction_no)}
                    className="font-mono text-primary underline-offset-4 hover:underline"
                >
                    {entry.transaction_no}
                </Link>
            ),
        },
        {
            id: 'customer',
            header: t('eod_report.customer'),
            mobile: 'subtitle',
            cell: (entry) =>
                entry.customer && entry.customer_id ? (
                    <Link
                        href={`/customers/${entry.customer_id}`}
                        className="text-primary underline-offset-4 hover:underline"
                    >
                        {entry.customer}
                    </Link>
                ) : (
                    (entry.customer ?? '—')
                ),
        },
        {
            id: 'transaction_type',
            header: t('eod_report.transaction_type'),
            cell: (entry) => transactionTypeLabel(entry.transaction_type),
        },
        {
            id: 'direction',
            header: t('eod_report.direction'),
            cell: (entry) => t(`eod_report.${entry.direction}`),
        },
        {
            id: 'amount',
            header: t('eod_report.amount'),
            mobile: 'badge',
            className: 'tabular-nums',
            cell: (entry) => amount(entry.amount, locale),
        },
        {
            id: 'balance_after',
            header: t('eod_report.balance_after'),
            className: 'tabular-nums',
            cell: (entry) => amount(entry.balance_after, locale),
        },
        {
            id: 'created_at',
            header: t('eod_report.created_at'),
            mobile: 'meta',
            className: 'whitespace-nowrap text-xs text-muted-foreground',
            cell: (entry) => formatDateTime(entry.created_at),
        },
    ];
    const breakdownColumns: DataTableColumn<EntryBreakdown>[] = [
        {
            id: 'type',
            header: t('eod_report.transaction_type'),
            cell: (item) => transactionTypeLabel(item.type),
        },
        {
            id: 'entries',
            header: t('eod_report.entries'),
            className: 'tabular-nums',
            cell: (item) => numberFormat.format(item.entries),
        },
        {
            id: 'credits',
            header: t('eod_report.credits'),
            className: 'tabular-nums',
            cell: (item) => amount(item.credits, locale),
        },
        {
            id: 'debits',
            header: t('eod_report.debits'),
            className: 'tabular-nums',
            cell: (item) => amount(item.debits, locale),
        },
    ];
    const ledgerLineColumns: DataTableColumn<LedgerLine>[] = [
        {
            id: 'line_no',
            header: t('eod_report.line_no'),
            mobile: 'title',
            className: 'tabular-nums',
            cell: (line) => line.line_no,
        },
        {
            id: 'created_at',
            header: t('eod_report.entry_date'),
            mobile: 'meta',
            className: 'text-xs text-muted-foreground sm:whitespace-nowrap',
            cell: (line) => (
                <>
                    <span className="hidden sm:inline">{formatDateTime(line.created_at)}</span>
                    <span className="sm:hidden">
                        {formatDateTime(line.created_at)} · {t('eod_report.balance_after')}:{' '}
                        {line.balance_after === null ? '—' : amount(line.balance_after, locale)}
                    </span>
                </>
            ),
        },
        {
            id: 'account',
            header: t('eod_report.account'),
            cell: (line) => (
                <>
                    <span className="font-medium">{line.account_name ?? '—'}</span>
                    {line.account_code ? (
                        <span className="ml-1 text-muted-foreground">({line.account_code})</span>
                    ) : null}
                </>
            ),
        },
        {
            id: 'customer',
            header: t('eod_report.customer'),
            mobile: 'subtitle',
            cell: (line) => (
                <>
                    <span className="sm:hidden">
                        {line.account_name ?? '—'}
                        {line.account_code ? ` (${line.account_code})` : ''}
                        {' · '}
                    </span>
                    {line.customer ?? '—'}
                </>
            ),
        },
        {
            id: 'debit',
            header: t('eod_report.debit'),
            mobile: 'badge',
            className: 'tabular-nums',
            cell: (line) => (
                <>
                    <span className="hidden sm:inline">{line.debit > 0 ? amount(line.debit, locale) : '—'}</span>
                    <span className="sm:hidden">
                        {t('eod_report.debit')}: {line.debit > 0 ? amount(line.debit, locale) : '—'} ·{' '}
                        {t('eod_report.credit')}: {line.credit > 0 ? amount(line.credit, locale) : '—'}
                    </span>
                </>
            ),
        },
        {
            id: 'credit',
            header: t('eod_report.credit'),
            className: 'tabular-nums',
            cell: (line) => (line.credit > 0 ? amount(line.credit, locale) : '—'),
        },
        {
            id: 'balance_after',
            header: t('eod_report.balance_after'),
            className: 'tabular-nums',
            cell: (line) => (line.balance_after === null ? '—' : amount(line.balance_after, locale)),
        },
    ];

    return (
        <>
            <Head title={t('menu.eod_reports')} />
            <PageContent className="gap-3 lg:gap-3.5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <PageHeader title={t('menu.eod_reports')} description={t('menu.eod_reports_description')} />
                    <BackButton href={returnTo} />
                </div>

                <div className="flex flex-wrap items-end justify-between gap-3">
                    <FormField
                        label={t('eod_report.date')}
                        htmlFor="eod-date"
                        icon={CalendarDays}
                        className="w-full sm:w-64 [&>div:last-child]:hidden"
                        labelClassName="text-[13px]"
                    >
                        <DatePicker
                            id="eod-date"
                            value={date}
                            onChange={(value) =>
                                router.get('/reports/eod', { date: value }, { preserveScroll: true, replace: true })
                            }
                        />
                    </FormField>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {metrics.map(({ label, value, icon: Icon, href, statusColor }) => (
                        <Card key={label} className="gap-2 py-4">
                            <CardContent className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-xs text-muted-foreground">{label}</p>
                                    <p
                                        className={`mt-2 break-words font-heading text-xl font-semibold tabular-nums ${statusColor ?? ''}`}
                                    >
                                        {href ? (
                                            <Link
                                                href={href}
                                                className="underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            >
                                                {value}
                                            </Link>
                                        ) : (
                                            value
                                        )}
                                    </p>
                                </div>
                                <Icon className={`size-4 shrink-0 ${statusColor ?? 'text-primary'}`} />
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <DataTable
                    title={t('eod_report.breakdown')}
                    data={breakdown}
                    columns={breakdownColumns}
                    getRowId={(item) => item.type}
                    emptyLabel={t('eod_report.no_breakdown')}
                    showSearch={false}
                    numbered={false}
                />

                <DataTable
                    title={t('eod_report.day_entries')}
                    data={entries.data}
                    columns={entryColumns}
                    getRowId={(entry) => String(entry.id)}
                    pagination={entries}
                    paginationSummary={t('common.showing')
                        .replace(':from', String(entries.from ?? 0))
                        .replace(':to', String(entries.to ?? 0))
                        .replace(':total', String(entries.total))}
                    emptyLabel={t('eod_report.no_entries')}
                    showSearch={false}
                    numbered={false}
                    directActions
                    actions={(entry) => (
                        <TableActionButton
                            label={t('eod_report.view_double_entries')}
                            icon={EyeIcon}
                            onClick={() => setSelectedEntry(entry)}
                            size="sm"
                        />
                    )}
                />

                <FormDialog
                    open={selectedEntry !== null}
                    onOpenChange={(open) => {
                        if (!open) setSelectedEntry(null);
                    }}
                    title={t('eod_report.double_entry_details')}
                    description={selectedEntry?.transaction_no}
                    icon={BookOpen}
                    size="2xl"
                >
                    {selectedEntry ? (
                        <div className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">
                            <div className="mb-3 grid gap-3 sm:grid-cols-2">
                                <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        {t('eod_report.debit_total')}
                                    </p>
                                    <p className="mt-1 text-sm font-semibold tabular-nums text-foreground">
                                        {amount(
                                            selectedEntry.ledger_entries.reduce((total, line) => total + line.debit, 0),
                                            locale,
                                        )}
                                    </p>
                                </div>
                                <div className="rounded-xl border border-border/60 bg-muted/20 p-3.5">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        {t('eod_report.credit_total')}
                                    </p>
                                    <p className="mt-1 text-sm font-semibold tabular-nums text-foreground">
                                        {amount(
                                            selectedEntry.ledger_entries.reduce(
                                                (total, line) => total + line.credit,
                                                0,
                                            ),
                                            locale,
                                        )}
                                    </p>
                                </div>
                            </div>
                            <DataTable
                                data={selectedEntry.ledger_entries}
                                columns={ledgerLineColumns}
                                getRowId={(line) => String(line.id)}
                                emptyLabel={t('eod_report.no_entries')}
                                showSearch={false}
                                numbered={false}
                                className="h-auto min-h-0 shadow-none"
                            />
                        </div>
                    ) : null}
                </FormDialog>
            </PageContent>
        </>
    );
}
