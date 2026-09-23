import type { LucideIcon } from 'lucide-react';
import {
    ArrowDownLeftIcon,
    ArrowUpRightIcon,
    BanIcon,
    CalendarIcon,
    PhoneIcon,
    ReceiptTextIcon,
    RotateCcwIcon,
    SlidersHorizontalIcon,
    UserCheckIcon,
    WalletIcon,
    SquarePenIcon,
    WifiIcon,
    BanknoteArrowUpIcon,
    ArrowLeftRight,
} from 'lucide-react';
import type { ReactNode } from 'react';

import { CopyValueButton } from '@/components/CopyValueButton';
import { PhoneDisplay } from '@/components/customer/PhoneDisplay';
import { StaffListAvatar } from '@/components/staff/StaffListAvatar';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { formatTopUpNumber, TOP_UP_CARD_CURRENCY } from '@/lib/top-up-cards';
import { cn, formatDate } from '@/lib/utils';

type FactProps = {
    icon: LucideIcon;
    label: string;
    children: ReactNode;
    className?: string;
    iconClassName?: string;
};

function Fact({ icon: Icon, label, children, className, iconClassName }: FactProps) {
    return (
        <div className={cn('min-w-0 rounded-[8px] border border-border/60 bg-muted/25 px-3 py-2.5 m-2', className)}>
            <div className="mb-1 flex items-center gap-1.5 text-[11px] font-bold font-medium uppercase tracking-wide text-muted-foreground">
                <Icon className={cn('size-4 opacity-70', iconClassName)} strokeWidth={2.5} />
                <span className="truncate">{label}</span>
            </div>
            <div className="min-w-0 text-[13px] font-medium leading-snug text-foreground">{children}</div>
        </div>
    );
}

function TransactionValue({
    summary,
    color,
    t,
}: {
    summary: TransactionSummary;
    color: 'credit' | 'debit';
    t: (key: string) => string;
}) {
    return (
        <div className="flex flex-col gap-1">
            <span className="sm:col-span-1">
                <span
                    className={cn(
                        'font-heading text-[16px] font-semibold tabular-nums',
                        color === 'credit' ? 'text-emerald-600' : 'text-red-600',
                    )}
                >
                    {formatTopUpNumber(summary.amount)}
                </span>
                <span className="ml-1 text-[12px]">{TOP_UP_CARD_CURRENCY}</span>
            </span>
            <span className="text-[10px] uppercase tracking-[0.12em] text-muted-foreground">
                {summary.count} {t('common.transactions')}
            </span>
        </div>
    );
}

type TransactionSummary = {
    count: number;
    amount: string;
};

type TransactionOverview = {
    topup: TransactionSummary;
    transfer_in: TransactionSummary;
    transfer_out: TransactionSummary;
    ftth_bill: TransactionSummary;
    wifi_package: TransactionSummary;
    refund: TransactionSummary;
    adjustment: TransactionSummary;
};

type CustomerProfileCardProps = {
    name: string;
    phone: string;
    status: string;
    joined: string | null;
    walletBalance: string;
    transactionOverview: TransactionOverview;
    onEdit: () => void;
    onViewTransactions: () => void;
    onToggleStatus: () => void;
};

export function CustomerProfileCard({
    name,
    phone,
    status,
    joined,
    walletBalance,
    transactionOverview,
    onEdit,
    onViewTransactions,
    onToggleStatus,
}: CustomerProfileCardProps) {
    const { t } = useTranslation();
    const isActive = status === 'active';

    return (
        <section
            className={cn(
                'overflow-hidden border border-border/80 bg-[#FFFFFF]',
                'shadow-[0_8px_24px_rgb(23_50_54/0.08),0_20px_48px_rgb(23_50_54/0.12)]',
                'rounded-none sm:rounded-[12px]',
                'dark:bg-card dark:shadow-[0_8px_24px_rgb(0_0_0/0.28),0_20px_48px_rgb(0_0_0/0.32)]',
            )}
        >
            <div className="flex flex-col gap-4 border-b border-border/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div className="flex min-w-0 items-center gap-3">
                    <StaffListAvatar username={name} className="size-12 text-sm" />
                    <div className="min-w-0">
                        <div className="flex flex-col items-start gap-1">
                            <h2 className="truncate text-[15px] font-semibold leading-[1.7] text-foreground">{name}</h2>
                            <StatusBadge status={status} />
                        </div>
                    </div>
                </div>

                <div className="flex shrink-0 flex-wrap items-center gap-1.5">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-8 gap-1 rounded-[6px] px-2.5 text-[11px]"
                        onClick={onViewTransactions}
                    >
                        <ArrowLeftRight className="size-3.5" strokeWidth={1.85} />
                        {t('common.view_all_transactions')}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-8 gap-1 rounded-[6px] px-2.5 text-[11px]"
                        onClick={onEdit}
                    >
                        <SquarePenIcon className="size-3.5" strokeWidth={1.85} />
                        {t('common.edit')}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant={isActive ? 'destructive' : 'primary'}
                        className="h-8 gap-1 rounded-[6px] px-2.5 text-[11px]"
                        onClick={onToggleStatus}
                    >
                        {isActive ? (
                            <BanIcon className="size-3.5" strokeWidth={1.85} />
                        ) : (
                            <UserCheckIcon className="size-3.5" strokeWidth={1.85} />
                        )}
                        {isActive ? t('customers.suspend') : t('customers.reactivate')}
                    </Button>
                </div>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2">
                <Fact icon={PhoneIcon} label={t('customers.phone')}>
                    <div className="flex items-center gap-1.5">
                        <PhoneDisplay phone={phone} />
                        <CopyValueButton value={phone} label={t('customers.phone')} />
                    </div>
                </Fact>
                <Fact icon={CalendarIcon} label={t('customers.joined')}>
                    <span className="font-mono text-[14px] tabular-nums text-muted-foreground">
                        {joined ? formatDate(joined) : '—'}
                    </span>
                </Fact>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4">
                <Fact
                    icon={WalletIcon}
                    label={t('customers.wallet_balance')}
                    iconClassName="text-primary"
                    className="sm:col-span-1"
                >
                    <span className="font-heading text-[16px] font-semibold tabular-nums text-primary">
                        {formatTopUpNumber(walletBalance)}
                    </span>
                    <span className="ml-1 text-[12px]">{TOP_UP_CARD_CURRENCY}</span>
                </Fact>
                <Fact icon={BanknoteArrowUpIcon} label={t('wallet.topup')} iconClassName="text-emerald-600 size-4.5">
                    <TransactionValue summary={transactionOverview.topup} color="credit" t={t} />
                </Fact>
                <Fact icon={ArrowDownLeftIcon} label={t('wallet.transfer_in')} iconClassName="text-emerald-600">
                    <TransactionValue summary={transactionOverview.transfer_in} color="credit" t={t} />
                </Fact>
                <Fact icon={ArrowUpRightIcon} label={t('wallet.transfer_out')} iconClassName="text-red-600">
                    <TransactionValue summary={transactionOverview.transfer_out} color="debit" t={t} />
                </Fact>
                <Fact icon={ReceiptTextIcon} label={t('wallet.ftth_bill')} iconClassName="text-red-600">
                    <TransactionValue summary={transactionOverview.ftth_bill} color="debit" t={t} />
                </Fact>
                <Fact icon={WifiIcon} label={t('wallet.wifi_package')} iconClassName="text-red-600">
                    <TransactionValue summary={transactionOverview.wifi_package} color="debit" t={t} />
                </Fact>
                <Fact icon={RotateCcwIcon} label={t('wallet.refund')} iconClassName="text-emerald-600">
                    <TransactionValue summary={transactionOverview.refund} color="credit" t={t} />
                </Fact>
                <Fact icon={SlidersHorizontalIcon} label={t('wallet.adjustment')} iconClassName="text-success">
                    <TransactionValue summary={transactionOverview.adjustment} color="credit" t={t} />
                </Fact>
            </div>
        </section>
    );
}
