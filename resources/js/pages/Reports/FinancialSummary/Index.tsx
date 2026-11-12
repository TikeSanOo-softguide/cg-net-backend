import { Head, router } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    Banknote,
    CircleAlert,
    CircleCheck,
    CreditCard,
    LoaderCircle,
    RefreshCw,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';

import { BackButton } from '@/components/BackButton';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { SearchableSelect } from '@/components/SearchableSelect';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { toast } from '@/hooks/use-toast';
import { useReturnTo } from '@/hooks/useReturnTo';
import { useTranslation } from '@/hooks/useTranslation';
import { TOP_UP_CARD_CURRENCY } from '@/lib/top-up-cards';
import type { SupportedLocale } from '@/types';
import { formatDateTime } from '@/lib/utils';

type CountedAmount = {
    count: number;
    amount: string;
};

type SystemMoneyAccount = {
    code: string;
    name: string;
    balance: string;
};

type Summary = {
    checked_at: string | null;
    top_up_cards: {
        activated: CountedAmount;
        active: CountedAmount;
        redeemed: CountedAmount;
        blocked?: CountedAmount;
        expired?: CountedAmount;
    };
    system_money: {
        accounts: SystemMoneyAccount[];
        wallet_balance?: string;
        internet_service_revenue?: {
            ftth: string;
            cg: string;
        };
        adjustments?: {
            debits: string;
            credits: string;
        };
    };
    adjustments: {
        debits: string;
        credits: string;
        net_expense: string;
    };
};

type SnapshotOption = {
    id: number;
    checked_at: string | null;
    notification_unread: boolean;
};

type Props = {
    snapshot: Summary | null;
    snapshotId: number | null;
    snapshots: SnapshotOption[];
    run: {
        id: number;
        status: 'queued' | 'running' | 'completed' | 'failed';
        requested_at: string | null;
    } | null;
};

const localeTags: Record<SupportedLocale, string> = {
    en: 'en-US',
    my: 'my-MM',
    zh: 'zh-CN',
};

function formatAmount(value: string | undefined, locale: SupportedLocale): string {
    if (value === undefined) {
        return '—';
    }

    return `${new Intl.NumberFormat(localeTags[locale], {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value))} ${TOP_UP_CARD_CURRENCY}`;
}

function MetricCard({
    label,
    value,
    count,
    icon: Icon,
    locale,
    countLabel,
}: {
    label: string;
    value: string | undefined;
    count?: number;
    icon: LucideIcon;
    locale: SupportedLocale;
    countLabel?: string;
}) {
    const numberFormat = new Intl.NumberFormat(localeTags[locale]);

    return (
        <Card className="gap-2 py-4">
            <CardContent className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-xs text-muted-foreground">{label}</p>
                    <p className="mt-2 break-words font-heading text-xl font-semibold tabular-nums">
                        {formatAmount(value, locale)}
                    </p>
                    {count !== undefined && countLabel ? (
                        <p className="mt-1 text-xs text-muted-foreground">
                            {numberFormat.format(count)} {countLabel}
                        </p>
                    ) : null}
                </div>
                <Icon className="size-4 shrink-0 text-primary" aria-hidden="true" />
            </CardContent>
        </Card>
    );
}

function MetricSection({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="grid gap-3 py-2">
            <div>
                <h2 className="font-heading text-base font-semibold">{title}</h2>
            </div>
            {children}
        </section>
    );
}

export default function FinancialSummaryIndex({ snapshot, snapshotId, snapshots, run }: Props) {
    const { t, locale, timezone } = useTranslation();
    const returnTo = useReturnTo('/reports');
    const pollInFlight = useRef(false);
    const activeRunId = useRef<number | null>(null);
    const notifiedRunId = useRef<number | null>(null);
    const [readSnapshotIds, setReadSnapshotIds] = useState<Set<number>>(() => new Set());
    const [submitting, setSubmitting] = useState(false);
    const hasActiveRun = run?.status === 'queued' || run?.status === 'running';
    const isRunning = submitting || hasActiveRun;

    useEffect(() => {
        if (snapshotId === null) {
            return;
        }

        router.put(
            `/dashboard/notifications/reference/financial_summary_snapshot/${snapshotId}/read`,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    setReadSnapshotIds((current) => new Set(current).add(snapshotId));
                },
            },
        );
    }, [snapshotId]);

    useEffect(() => {
        if (!run) {
            return;
        }

        if (run.status === 'queued' || run.status === 'running') {
            activeRunId.current = run.id;

            return;
        }

        if (activeRunId.current !== run.id || notifiedRunId.current === run.id) {
            return;
        }

        toast({
            variant: run.status === 'failed' ? 'error' : 'success',
            title: t(run.status === 'failed' ? 'toast.error' : 'toast.success'),
            description: t(
                run.status === 'failed'
                    ? 'financial_summary_report.check_failed'
                    : 'financial_summary_report.check_completed',
            ),
        });
        notifiedRunId.current = run.id;
        activeRunId.current = null;
    }, [run?.id, run?.status, t]);

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
                only: ['snapshot', 'snapshotId', 'snapshots', 'run'],
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

    const statusKey = isRunning
        ? run?.status === 'queued'
            ? 'financial_summary_report.status_queued'
            : 'financial_summary_report.status_running'
        : run?.status === 'failed'
          ? 'financial_summary_report.failed'
          : snapshot
            ? 'financial_summary_report.completed'
            : null;
    const snapshotOptions = snapshots.map((item) => ({
        value: item.id.toString(),
        label: formatDateTime(item.checked_at, false, timezone),
        unread: item.notification_unread && !readSnapshotIds.has(item.id),
        unreadLabel:
            item.notification_unread && !readSnapshotIds.has(item.id) ? t('admin_notifications.unread') : undefined,
    }));
    const topUpClearingAccount = {
        code: 'CASH_TOPUP',
        label: t('financial_summary_report.cash_topup'),
    };
    const cgNetRevenueAccount = {
        code: 'PKG_REVENUE',
        label: t('financial_summary_report.package_revenue'),
    };
    const adjustmentTotals = snapshot?.system_money.adjustments ?? snapshot?.adjustments;
    const internetServiceRevenue = snapshot?.system_money.internet_service_revenue;
    const legacyInternetServiceRevenue = snapshot?.system_money.accounts.find(
        (account) => account.code === 'INET_SVC_REVENUE',
    )?.balance;

    const handleCheck = () => {
        setSubmitting(true);
        router.post(
            '/reports/financial-summary/check',
            {},
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <>
            <Head title={t('financial_summary_report.title')} />
            <PageContent className="gap-3 lg:gap-3.5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <PageHeader
                        title={t('financial_summary_report.title')}
                        description={t('financial_summary_report.description')}
                    />
                    <BackButton href={returnTo} fallback="/reports" />
                </div>

                {run || snapshot ? (
                    <div
                        role="status"
                        className={`flex w-full items-center gap-2 rounded-md ring-1 ring-inset px-3 py-2.5 text-sm font-semibold ${
                            run?.status === 'failed'
                                ? 'ring-danger bg-danger/10 text-danger'
                                : snapshot
                                  ? 'ring-success/30 bg-success/5 text-success'
                                  : 'ring-border bg-muted/40 text-muted-foreground'
                        }`}
                    >
                        {isRunning ? (
                            <LoaderCircle className="size-4 shrink-0 animate-spin" />
                        ) : run?.status === 'failed' ? (
                            <CircleAlert className="size-4 shrink-0" />
                        ) : snapshot ? (
                            <CircleCheck className="size-4 shrink-0" />
                        ) : (
                            <Banknote className="size-4 shrink-0" />
                        )}
                        {statusKey ? <span>{t(statusKey)}</span> : null}
                        {snapshot?.checked_at ? (
                            <span className="ml-auto whitespace-nowrap text-xs">
                                {t('financial_summary_report.last_checked')}:{' '}
                                {formatDateTime(snapshot.checked_at, false, timezone)}
                            </span>
                        ) : null}
                    </div>
                ) : null}

                <Card className="w-full">
                    <CardContent className="flex flex-wrap items-start gap-2 p-3">
                        <SearchableSelect
                            value={snapshotId?.toString() ?? ''}
                            options={snapshotOptions}
                            placeholder={t('financial_summary_report.select_snapshot')}
                            noResultsMessage={t('financial_summary_report.no_snapshots')}
                            className="w-full shrink-0 sm:w-[280px]"
                            triggerClassName="h-10 text-xs"
                            onValueChange={(value) =>
                                router.get(
                                    '/reports/financial-summary',
                                    { snapshot: value },
                                    { preserveScroll: true, preserveState: true, replace: true },
                                )
                            }
                        />
                        <Button
                            type="button"
                            size="sm"
                            aria-busy={isRunning}
                            disabled={isRunning}
                            className="h-10 min-h-10 min-w-[148px] shrink-0"
                            onClick={handleCheck}
                        >
                            {isRunning ? <LoaderCircle className="animate-spin" /> : <RefreshCw />}
                            {t(isRunning ? 'financial_summary_report.checking' : 'financial_summary_report.check_now')}
                        </Button>
                    </CardContent>
                </Card>

                <MetricSection title={t('financial_summary_report.system_money')}>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <MetricCard
                            label={topUpClearingAccount.label}
                            value={
                                snapshot?.system_money.accounts.find(
                                    (account) => account.code === topUpClearingAccount.code,
                                )?.balance
                            }
                            icon={Banknote}
                            locale={locale}
                        />
                        <MetricCard
                            label={t('financial_summary_report.wallet_amount_added')}
                            value={adjustmentTotals?.debits}
                            icon={ArrowDownToLine}
                            locale={locale}
                        />
                        <MetricCard
                            label={t('financial_summary_report.wallet_amount_deducted')}
                            value={adjustmentTotals?.credits}
                            icon={ArrowUpFromLine}
                            locale={locale}
                        />
                        {internetServiceRevenue ? (
                            <>
                                <MetricCard
                                    label={t('financial_summary_report.ftth_service_revenue')}
                                    value={internetServiceRevenue.ftth}
                                    icon={Banknote}
                                    locale={locale}
                                />
                                <MetricCard
                                    label={t('financial_summary_report.cg_service_revenue')}
                                    value={internetServiceRevenue.cg}
                                    icon={Banknote}
                                    locale={locale}
                                />
                            </>
                        ) : legacyInternetServiceRevenue !== undefined ? (
                            <MetricCard
                                label={t('financial_summary_report.internet_service_revenue')}
                                value={legacyInternetServiceRevenue}
                                icon={Banknote}
                                locale={locale}
                            />
                        ) : (
                            <>
                                <MetricCard
                                    label={t('financial_summary_report.ftth_service_revenue')}
                                    value={undefined}
                                    icon={Banknote}
                                    locale={locale}
                                />
                                <MetricCard
                                    label={t('financial_summary_report.cg_service_revenue')}
                                    value={undefined}
                                    icon={Banknote}
                                    locale={locale}
                                />
                            </>
                        )}
                        <MetricCard
                            label={cgNetRevenueAccount.label}
                            value={
                                snapshot?.system_money.accounts.find(
                                    (account) => account.code === cgNetRevenueAccount.code,
                                )?.balance
                            }
                            icon={Banknote}
                            locale={locale}
                        />
                        <MetricCard
                            label={t('financial_summary_report.wallet_balance')}
                            value={snapshot?.system_money.wallet_balance}
                            icon={Wallet}
                            locale={locale}
                        />
                    </div>
                </MetricSection>

                <MetricSection title={t('financial_summary_report.top_up_cards')}>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <MetricCard
                            label={t('financial_summary_report.activated_cards')}
                            value={snapshot?.top_up_cards.activated.amount}
                            count={snapshot?.top_up_cards.activated.count}
                            countLabel={t('financial_summary_report.card_count')}
                            icon={CreditCard}
                            locale={locale}
                        />
                        <MetricCard
                            label={t('financial_summary_report.active_cards')}
                            value={snapshot?.top_up_cards.active.amount}
                            count={snapshot?.top_up_cards.active.count}
                            countLabel={t('financial_summary_report.card_count')}
                            icon={CreditCard}
                            locale={locale}
                        />
                        <MetricCard
                            label={t('financial_summary_report.used_cards')}
                            value={snapshot?.top_up_cards.redeemed.amount}
                            count={snapshot?.top_up_cards.redeemed.count}
                            countLabel={t('financial_summary_report.card_count')}
                            icon={CreditCard}
                            locale={locale}
                        />
                        <MetricCard
                            label={t('financial_summary_report.blocked_cards')}
                            value={snapshot?.top_up_cards.blocked?.amount}
                            count={snapshot?.top_up_cards.blocked?.count}
                            countLabel={t('financial_summary_report.card_count')}
                            icon={CreditCard}
                            locale={locale}
                        />
                        <MetricCard
                            label={t('financial_summary_report.expired_cards')}
                            value={snapshot?.top_up_cards.expired?.amount}
                            count={snapshot?.top_up_cards.expired?.count}
                            countLabel={t('financial_summary_report.card_count')}
                            icon={CreditCard}
                            locale={locale}
                        />
                    </div>
                </MetricSection>
            </PageContent>
        </>
    );
}
