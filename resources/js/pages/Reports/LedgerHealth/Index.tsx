import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { CalendarClockIcon, CircleAlert, CircleCheck, LoaderCircle, RefreshCw } from 'lucide-react';

import { BackButton } from '@/components/BackButton';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { DataTable, type DataTableColumn } from '@/components/DataTable';
import { SearchableSelect } from '@/components/SearchableSelect';
import { StaffStatusSwitch } from '@/components/staff/StaffStatusSwitch';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DateTimePicker } from '@/components/ui/date-time-picker';
import { toast } from '@/hooks/use-toast';
import { useReturnTo } from '@/hooks/useReturnTo';
import { useTranslation } from '@/hooks/useTranslation';
import { TOP_UP_CARD_CURRENCY } from '@/lib/top-up-cards';
import type { PageProps, SupportedLocale } from '@/types';
import { formatDateTime } from '@/lib/utils';

type BalanceMismatch = {
    wallet_id: number;
    customer_id: number;
    customer: string | null;
    wallet_balance: number;
    ledger_balance: number | null;
};

type MissingEntry = {
    transaction_no: string;
    wallet_id: number;
    customer: string | null;
    type: string;
    amount: number;
    created_at: string | null;
};

type SourceMismatch = {
    id: string;
    source: string;
    source_label: string;
    transaction_no: string | null;
    customer_id: number | null;
    customer: string | null;
    issue: string;
    detail: string | null;
    created_at: string | null;
};

type SnapshotOption = {
    id: number;
    type: 'full' | 'daily' | 'manual';
    checked_at: string | null;
    window_start: string | null;
    window_end: string | null;
};

type Props = {
    scanType: 'full' | 'daily' | 'manual';
    dailyScanEnabled: boolean;
    defaultWindow: { start: string; end: string };
    snapshotId: number | null;
    snapshots: SnapshotOption[];
    snapshot: {
        checked_at: string | null;
        type?: 'full' | 'daily' | 'manual';
        window_start?: string | null;
        window_end?: string | null;
        health: {
            wallets: number;
            entries: number;
            balance_mismatches: number;
            completed_without_entry: number;
            unbalanced_transactions?: number;
            duplicate_entry_transactions: number;
            source_mismatches?: number;
        };
        balanceMismatches: BalanceMismatch[];
        missingEntries: MissingEntry[];
        unbalancedTransactions?: {
            transaction_no: string;
            wallet_id: number;
            debit_total: number;
            credit_total: number;
            created_at: string | null;
        }[];
        sourceMismatches?: SourceMismatch[];
    } | null;
    run: {
        id: number;
        type?: 'full' | 'daily' | 'manual';
        status: 'queued' | 'running' | 'completed' | 'failed';
        requested_at: string | null;
    } | null;
};

const localeTags: Record<SupportedLocale, string> = {
    en: 'en-US',
    my: 'my-MM',
    zh: 'zh-CN',
};

function amount(value: number, locale: SupportedLocale): string {
    return `${new Intl.NumberFormat(localeTags[locale]).format(value)} ${TOP_UP_CARD_CURRENCY}`;
}

function dateTime(value: string | null, locale: SupportedLocale): string {
    return value ? new Date(value).toLocaleString(localeTags[locale]) : '—';
}

function transactionHref(transactionNo: string): string {
    const query = new URLSearchParams({ search: transactionNo, open_transaction: transactionNo });

    return `/billing/transactions?${query.toString()}`;
}

export default function LedgerHealthReportIndex({
    snapshot,
    snapshotId,
    snapshots,
    run,
    scanType,
    dailyScanEnabled,
    defaultWindow,
}: Props) {
    const { t } = useTranslation();
    const locale = usePage<PageProps>().props.locale;
    const returnTo = useReturnTo('/reports');
    const numberFormat = new Intl.NumberFormat(localeTags[locale]);
    const pollInFlight = useRef(false);
    const activeRunId = useRef<number | null>(null);
    const notifiedRunId = useRef<number | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [togglingDaily, setTogglingDaily] = useState(false);
    const [manualFrom, setManualFrom] = useState(defaultWindow.start);
    const [manualTo, setManualTo] = useState(defaultWindow.end);
    const hasActiveRun = run?.status === 'queued' || run?.status === 'running';
    const isRunning = submitting || hasActiveRun;

    useEffect(() => {
        if (scanType !== 'manual') {
            return;
        }

        setManualFrom(defaultWindow.start);
        setManualTo(defaultWindow.end);
    }, [scanType, defaultWindow.start, defaultWindow.end]);

    const health = snapshot?.health;
    const balanceMismatches = snapshot?.balanceMismatches ?? [];
    const missingEntries = snapshot?.missingEntries ?? [];
    const sourceMismatches = snapshot?.sourceMismatches ?? [];
    const isHealthy =
        health !== undefined &&
        health.balance_mismatches === 0 &&
        health.completed_without_entry === 0 &&
        (health.unbalanced_transactions ?? 0) === 0 &&
        health.duplicate_entry_transactions === 0 &&
        (health.source_mismatches ?? 0) === 0;
    const statusKey = isRunning
        ? run?.status === 'queued'
            ? 'ledger_health_report.status_queued'
            : 'ledger_health_report.status_running'
        : run?.status === 'failed'
          ? 'ledger_health_report.check_failed'
          : !snapshot
            ? 'ledger_health_report.not_checked'
            : isHealthy
              ? 'ledger_health_report.healthy'
              : 'ledger_health_report.needs_attention';

    const scanTypeOptions = [
        { value: 'daily', label: t('ledger_health_report.scan_types.daily') },
        { value: 'full', label: t('ledger_health_report.scan_types.full') },
        { value: 'manual', label: t('ledger_health_report.scan_types.manual') },
    ];

    const snapshotLabel = (item: SnapshotOption): string => formatDateTime(item.checked_at);

    useEffect(() => {
        if (!run) return;

        if (run.status === 'queued' || run.status === 'running') {
            activeRunId.current = run.id;
            return;
        }

        if (activeRunId.current !== run.id || notifiedRunId.current === run.id) return;

        toast({
            variant: run.status === 'failed' ? 'error' : 'success',
            title: t(run.status === 'failed' ? 'toast.error' : 'toast.success'),
            description: t(
                run.status === 'failed' ? 'ledger_health_report.check_failed' : 'ledger_health_report.check_completed',
            ),
        });
        notifiedRunId.current = run.id;
        activeRunId.current = null;
    }, [run?.id, run?.status, t]);

    const metrics = [
        { label: t('ledger_health_report.wallets'), value: health?.wallets, isIssue: false },
        { label: t('ledger_health_report.entries'), value: health?.entries, isIssue: false },
        { label: t('ledger_health_report.balance_mismatches'), value: health?.balance_mismatches, isIssue: true },
        {
            label: t('ledger_health_report.completed_without_entry'),
            value: health?.completed_without_entry,
            isIssue: true,
        },
        {
            label: t('ledger_health_report.unbalanced_transactions'),
            value: health == null ? undefined : (health.unbalanced_transactions ?? 0),
            isIssue: true,
        },
        {
            label: t('ledger_health_report.duplicate_entry_transactions'),
            value: health?.duplicate_entry_transactions,
            isIssue: true,
        },
        {
            label: t('ledger_health_report.source_mismatches'),
            value: health == null ? undefined : (health.source_mismatches ?? 0),
            isIssue: true,
        },
    ];
    const snapshotOptions = snapshots.map((item) => ({
        value: item.id.toString(),
        label: snapshotLabel(item),
    }));
    const balanceMismatchColumns: DataTableColumn<BalanceMismatch>[] = [
        {
            id: 'customer',
            header: t('ledger_health_report.customer'),
            cell: (row) =>
                row.customer ? (
                    <Link
                        href={`/customers/${row.customer_id}`}
                        className="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        <span className="tabular-nums">{row.customer}</span>
                    </Link>
                ) : (
                    <span>{t('ledger_health_report.unknown_customer')}</span>
                ),
            searchValue: (row) => row.customer ?? '',
        },
        {
            id: 'wallet_id',
            header: t('ledger_health_report.wallet_id'),
            cell: (row) => row.wallet_id,
            searchValue: (row) => String(row.wallet_id),
        },
        {
            id: 'wallet_balance',
            header: t('ledger_health_report.wallet_balance'),
            cell: (row) => amount(row.wallet_balance, locale),
            className: 'tabular-nums',
            searchValue: (row) => String(row.wallet_balance),
        },
        {
            id: 'ledger_balance',
            header: t('ledger_health_report.latest_ledger_balance'),
            cell: (row) => (row.ledger_balance === null ? '—' : amount(row.ledger_balance, locale)),
            className: 'tabular-nums',
            searchValue: (row) => (row.ledger_balance === null ? '' : String(row.ledger_balance)),
        },
        {
            id: 'difference',
            header: t('ledger_health_report.difference'),
            cell: (row) => amount(row.wallet_balance - (row.ledger_balance ?? 0), locale),
            className: 'tabular-nums',
            searchValue: (row) => String(row.wallet_balance - (row.ledger_balance ?? 0)),
        },
    ];
    const missingEntryColumns: DataTableColumn<MissingEntry>[] = [
        {
            id: 'transaction_no',
            header: t('ledger_health_report.transaction_no'),
            cell: (row) => (
                <Link
                    href={transactionHref(row.transaction_no)}
                    className="font-medium text-primary underline-offset-4 hover:underline"
                >
                    {row.transaction_no}
                </Link>
            ),
            searchValue: (row) => row.transaction_no,
        },
        {
            id: 'wallet_id',
            header: t('ledger_health_report.wallet_id'),
            cell: (row) => <span className="tabular-nums">{row.wallet_id}</span>,
            searchValue: (row) => String(row.wallet_id),
        },
        {
            id: 'customer',
            header: t('ledger_health_report.customer'),
            cell: (row) => row.customer ?? t('ledger_health_report.unknown_customer'),
            searchValue: (row) => row.customer ?? '',
        },
        {
            id: 'type',
            header: t('ledger_health_report.transaction_type'),
            cell: (row) => t(`transactions.types.${row.type}`),
            searchValue: (row) => t(`transactions.types.${row.type}`),
        },
        {
            id: 'amount',
            header: t('ledger_health_report.amount'),
            cell: (row) => amount(row.amount, locale),
            className: 'tabular-nums',
            searchValue: (row) => String(row.amount),
        },
        {
            id: 'created_at',
            header: t('ledger_health_report.created_at'),
            cell: (row) => formatDateTime(row.created_at),
            className: 'whitespace-nowrap text-muted-foreground',
            searchValue: (row) => formatDateTime(row.created_at),
        },
    ];
    const sourceMismatchColumns: DataTableColumn<SourceMismatch>[] = [
        {
            id: 'source',
            header: t('ledger_health_report.source'),
            cell: (row) => t(`ledger_health_report.sources.${row.source}`),
            searchValue: (row) => t(`ledger_health_report.sources.${row.source}`),
        },
        {
            id: 'source_label',
            header: t('ledger_health_report.source_ref'),
            cell: (row) => <span className="font-mono text-xs">{row.source_label}</span>,
            searchValue: (row) => row.source_label,
        },
        {
            id: 'transaction_no',
            header: t('ledger_health_report.transaction_no'),
            cell: (row) =>
                row.transaction_no ? (
                    <Link
                        href={transactionHref(row.transaction_no)}
                        className="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        {row.transaction_no}
                    </Link>
                ) : (
                    '—'
                ),
            searchValue: (row) => row.transaction_no ?? '',
        },
        {
            id: 'customer',
            header: t('ledger_health_report.customer'),
            cell: (row) =>
                row.customer && row.customer_id ? (
                    <Link
                        href={`/customers/${row.customer_id}`}
                        className="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        {row.customer}
                    </Link>
                ) : (
                    (row.customer ?? t('ledger_health_report.unknown_customer'))
                ),
            searchValue: (row) => row.customer ?? '',
        },
        {
            id: 'issue',
            header: t('ledger_health_report.issue'),
            cell: (row) => t(`ledger_health_report.issues.${row.issue}`),
            searchValue: (row) => t(`ledger_health_report.issues.${row.issue}`),
        },
        {
            id: 'detail',
            header: t('ledger_health_report.detail'),
            cell: (row) => row.detail ?? '—',
            searchValue: (row) => row.detail ?? '',
        },
        {
            id: 'created_at',
            header: t('ledger_health_report.created_at'),
            cell: (row) => formatDateTime(row.created_at),
            className: 'whitespace-nowrap text-muted-foreground',
            searchValue: (row) => formatDateTime(row.created_at),
        },
    ];

    useEffect(() => {
        if (!hasActiveRun) {
            return;
        }

        const interval = window.setInterval(() => {
            if (document.hidden || pollInFlight.current) {
                return;
            }

            pollInFlight.current = true;
            router.reload({
                only: ['snapshot', 'snapshotId', 'snapshots', 'run', 'scanType', 'dailyScanEnabled'],
                onFinish: () => {
                    pollInFlight.current = false;
                },
            });
        }, 3000);

        return () => {
            window.clearInterval(interval);
            pollInFlight.current = false;
        };
    }, [hasActiveRun]);

    return (
        <>
            <Head title={t('ledger_health_report.title')} />
            <PageContent className="gap-3 lg:gap-3.5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <PageHeader
                        title={t('ledger_health_report.title')}
                        description={t('ledger_health_report.description')}
                    />
                    <BackButton href={returnTo} fallback="/reports" />
                </div>

                <div
                    role="status"
                    className={`flex w-full items-center gap-2 rounded-md ring-1 ring-inset px-3 py-2.5 text-sm font-semibold ${
                        isHealthy
                            ? 'ring-success/30 bg-success/5 text-success'
                            : run?.status === 'failed'
                              ? 'ring-danger bg-danger/10 text-danger'
                              : snapshot
                                ? 'ring-danger bg-danger/10 text-danger'
                                : 'ring-border bg-muted/40 text-muted-foreground'
                    }`}
                >
                    {isRunning ? (
                        <LoaderCircle className="size-4 shrink-0 animate-spin" />
                    ) : isHealthy ? (
                        <CircleCheck className="size-4 shrink-0" />
                    ) : (
                        <CircleAlert className="size-4 shrink-0" />
                    )}
                    <span>{t(statusKey)}</span>
                    {snapshot?.checked_at ? (
                        <span className="ml-auto whitespace-nowrap text-xs">
                            {t('ledger_health_report.last_checked')}: {formatDateTime(snapshot.checked_at)}
                        </span>
                    ) : null}
                </div>

                <Card className="w-full">
                    <CardContent className="flex flex-wrap items-start justify-start gap-2 p-3">
                        <SearchableSelect
                            value={snapshotId?.toString() ?? ''}
                            options={snapshotOptions}
                            placeholder={t('ledger_health_report.select_snapshot')}
                            searchPlaceholder={t('ledger_health_report.search_snapshot')}
                            noResultsMessage={t('ledger_health_report.no_snapshot_matches')}
                            className="w-full shrink-0 sm:w-[280px]"
                            triggerClassName="h-10 text-xs"
                            onValueChange={(value) =>
                                router.get(
                                    '/reports/ledger-health',
                                    { type: scanType, snapshot: value },
                                    { preserveScroll: true, preserveState: true, replace: true },
                                )
                            }
                        />
                        <SearchableSelect
                            value={scanType}
                            options={scanTypeOptions}
                            placeholder={t('ledger_health_report.select_scan_type')}
                            searchPlaceholder={t('ledger_health_report.search_scan_type')}
                            noResultsMessage={t('ledger_health_report.no_scan_type_matches')}
                            className="w-full shrink-0 sm:w-[220px]"
                            triggerClassName="h-10 text-xs"
                            onValueChange={(value) =>
                                router.get(
                                    '/reports/ledger-health',
                                    { type: value },
                                    { preserveScroll: true, preserveState: true, replace: true },
                                )
                            }
                        />
                        {scanType === 'daily' ? (
                            <div className="flex h-10 shrink-0 items-center gap-2 rounded-md ring-1 ring-inset ring-border px-3">
                                <span className="whitespace-nowrap text-xs text-muted-foreground">
                                    {t('ledger_health_report.automatic_scan')}
                                </span>
                                <StaffStatusSwitch
                                    id="ledger-health-daily-scan"
                                    value={dailyScanEnabled ? 'active' : 'inactive'}
                                    onChange={(value) => {
                                        setTogglingDaily(true);
                                        router.post(
                                            '/reports/ledger-health/daily-scan',
                                            { enabled: value === 'active' },
                                            {
                                                preserveScroll: true,
                                                onFinish: () => setTogglingDaily(false),
                                            },
                                        );
                                    }}
                                    readOnly={togglingDaily || isRunning}
                                />
                            </div>
                        ) : null}
                        {scanType === 'full' ? (
                            <Button
                                type="button"
                                size="sm"
                                aria-busy={isRunning}
                                disabled={isRunning}
                                className="h-10 min-h-10 min-w-[148px] shrink-0 disabled:border-transparent disabled:bg-primary/70 disabled:text-primary-foreground"
                                onClick={() => {
                                    setSubmitting(true);
                                    router.post(
                                        '/reports/ledger-health/check',
                                        { type: 'full' },
                                        {
                                            preserveScroll: true,
                                            onFinish: () => setSubmitting(false),
                                        },
                                    );
                                }}
                            >
                                {isRunning ? <LoaderCircle className="animate-spin" /> : <RefreshCw />}
                                {t(isRunning ? 'ledger_health_report.checking' : 'ledger_health_report.full_scan')}
                            </Button>
                        ) : null}
                        {scanType === 'manual' ? (
                            <>
                                <div className="relative w-full min-w-0 sm:w-[240px]">
                                    <label htmlFor="ledger-health-from" className="sr-only">
                                        {t('common.start_date_time')}
                                    </label>
                                    <CalendarClockIcon
                                        aria-hidden="true"
                                        className="pointer-events-none absolute left-3 top-1/2 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <DateTimePicker
                                        id="ledger-health-from"
                                        value={manualFrom}
                                        max={manualTo || undefined}
                                        onChange={setManualFrom}
                                        placeholder={t('ledger_health_report.select_start_date_time')}
                                        className="w-full pl-9"
                                        clearable
                                    />
                                </div>
                                <div className="relative w-full min-w-0 sm:w-[240px]">
                                    <label htmlFor="ledger-health-to" className="sr-only">
                                        {t('common.end_date_time')}
                                    </label>
                                    <CalendarClockIcon
                                        aria-hidden="true"
                                        className="pointer-events-none absolute left-3 top-1/2 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <DateTimePicker
                                        id="ledger-health-to"
                                        value={manualTo}
                                        min={manualFrom || undefined}
                                        onChange={setManualTo}
                                        placeholder={t('ledger_health_report.select_end_date_time')}
                                        className="w-full pl-9"
                                        clearable
                                    />
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    aria-busy={isRunning}
                                    disabled={isRunning || !manualFrom || !manualTo}
                                    className="h-10 min-h-10 min-w-[148px] shrink-0 disabled:border-transparent disabled:bg-primary/70 disabled:text-primary-foreground"
                                    onClick={() => {
                                        setSubmitting(true);
                                        router.post(
                                            '/reports/ledger-health/check',
                                            { type: 'manual', from: manualFrom, to: manualTo },
                                            {
                                                preserveScroll: true,
                                                onFinish: () => setSubmitting(false),
                                            },
                                        );
                                    }}
                                >
                                    {isRunning ? <LoaderCircle className="animate-spin" /> : <RefreshCw />}
                                    {t(
                                        isRunning
                                            ? 'ledger_health_report.checking'
                                            : 'ledger_health_report.manual_scan',
                                    )}
                                </Button>
                            </>
                        ) : null}
                    </CardContent>
                </Card>

                {(scanType === 'daily' || scanType === 'manual') && snapshot?.window_start && snapshot?.window_end ? (
                    <p className="text-xs text-muted-foreground">
                        {t('ledger_health_report.scan_window')}: {formatDateTime(snapshot.window_start)} →{' '}
                        {formatDateTime(snapshot.window_end)}
                    </p>
                ) : null}

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {metrics.map((metric) => (
                        <Card key={metric.label} className="gap-2 py-4">
                            <CardContent>
                                <p className="text-xs text-muted-foreground">{metric.label}</p>
                                <p
                                    className={`mt-2 font-heading text-2xl font-semibold tabular-nums ${
                                        metric.isIssue && (metric.value ?? 0) > 0 ? 'text-danger' : ''
                                    }`}
                                >
                                    {metric.value === undefined ? '—' : numberFormat.format(metric.value)}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <DataTable
                    key={`balance-${snapshotId ?? 'no-snapshot'}`}
                    title={t('ledger_health_report.balance_mismatch_details')}
                    description={t('ledger_health_report.balance_mismatches_help')}
                    data={balanceMismatches}
                    columns={balanceMismatchColumns}
                    getRowId={(row) => String(row.wallet_id)}
                    emptyLabel={t(
                        snapshot ? 'ledger_health_report.no_balance_mismatches' : 'ledger_health_report.run_to_view',
                    )}
                    numbered={false}
                    showSearch={false}
                    clientPagination
                    clientPageSize={10}
                    className="h-auto min-h-0"
                />

                <DataTable
                    key={snapshotId ?? 'no-snapshot'}
                    title={t('ledger_health_report.missing_entry_details')}
                    description={t('ledger_health_report.completed_without_entry_help')}
                    data={missingEntries}
                    columns={missingEntryColumns}
                    getRowId={(row) => row.transaction_no}
                    emptyLabel={t(
                        snapshot ? 'ledger_health_report.no_missing_entries' : 'ledger_health_report.run_to_view',
                    )}
                    numbered={false}
                    showSearch={false}
                    clientPagination
                    clientPageSize={10}
                    className="h-auto min-h-0"
                />

                <DataTable
                    key={`source-${snapshotId ?? 'no-snapshot'}`}
                    title={t('ledger_health_report.source_mismatch_details')}
                    description={t('ledger_health_report.source_mismatches_help')}
                    data={sourceMismatches}
                    columns={sourceMismatchColumns}
                    getRowId={(row) => row.id}
                    emptyLabel={t(
                        snapshot ? 'ledger_health_report.no_source_mismatches' : 'ledger_health_report.run_to_view',
                    )}
                    numbered={false}
                    showSearch={false}
                    clientPagination
                    clientPageSize={10}
                    className="h-auto min-h-0"
                />
            </PageContent>
        </>
    );
}
