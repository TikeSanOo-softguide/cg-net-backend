import { useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    BanIcon,
    HashIcon,
    HistoryIcon,
    Link2Icon,
    TicketIcon,
    UnlinkIcon,
    UserCheckIcon,
    WifiIcon,
    WalletIcon,
} from 'lucide-react';

import { BackButton } from '@/components/BackButton';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { CustomerFormDialog } from '@/components/customer/CustomerFormDialog';
import { CustomerProfileCard } from '@/components/customer/CustomerProfileCard';
import { DetailSection } from '@/components/customer/DetailSection';
import { WalletAdjustDialog } from '@/components/customer/WalletAdjustDialog';
import { TransactionsTable, type Filters as TransactionFilters, type TransactionRow } from '@/pages/Transactions/Index';
import { formatPhoneLocal } from '@/lib/phone';
import { formatTopUpNumber, TOP_UP_CARD_CURRENCY } from '@/lib/top-up-cards';
import { DataTable } from '@/components/DataTable';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { FormControl } from '@/components/ui/form-control';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { useCan } from '@/hooks/useCan';
import { useReturnTo } from '@/hooks/useReturnTo';
import { navigationPopHeaders } from '@/lib/navigation-stack';
import { useTranslation } from '@/hooks/useTranslation';
import { cn, formatDate, formatDateTime } from '@/lib/utils';

type Customer = {
    id: number;
    name: string;
    phone: string;
    status: string;
    created_at: string | null;
};

type LocalizedText = {
    en: string | null;
    my: string | null;
    zh: string | null;
};

type AccountBinding = {
    account_number: string;
    status: string;
    package_name: LocalizedText | null;
};

type PackageRow = {
    id: number;
    package_name: LocalizedText | null;
    account_number: string | null;
    start_date: string | null;
    expiry_date: string | null;
    status: string;
};

type TopUpRow = {
    id: number;
    serial_no: string;
    amount: string;
    status: string;
    redeemed_at: string | null;
};

type WalletTransactionRow = {
    id: number;
    transaction_no: string;
    type: string;
    status: string;
    amount: string;
    created_at: string | null;
};

type TransactionSummary = {
    count: number;
    amount: string;
};

type TransactionOverview = {
    topup: TransactionSummary;
    ftth_bill: TransactionSummary;
    wifi_package: TransactionSummary;
    refund: TransactionSummary;
    adjustment: {
        credit: TransactionSummary;
        debit: TransactionSummary;
    };
};

type CustomersShowProps = {
    customer: Customer;
    accountBinding: AccountBinding | null;
    packageHistory: PackageRow[];
    wallet: {
        balance: string;
        transaction_overview: TransactionOverview;
        transactions: WalletTransactionRow[];
    };
    topUpHistory: Paginated<TopUpRow>;
    transactionPage: Paginated<TransactionRow> | null;
    transactionFilters: TransactionFilters;
    transactionFilterOptions: {
        actor_types: string[];
        types: string[];
        statuses: string[];
    };
};

export default function CustomersShow({
    customer,
    accountBinding,
    packageHistory,
    wallet,
    topUpHistory,
    transactionPage,
    transactionFilters,
    transactionFilterOptions,
}: CustomersShowProps) {
    const { t, locale } = useTranslation();
    const can = useCan();
    const canUpdate = can('customers.update');
    const page = usePage<{ errors?: Record<string, string | undefined> }>();
    const errors = page.props.errors ?? {};

    const [packageTab, setPackageTab] = useState<'active' | 'expired'>('active');
    const showAllTransactions = transactionPage !== null;
    const returnTo = useReturnTo('/customers');
    const goBackToCustomerList = () => {
        if (showAllTransactions) {
            router.visit(`/customers/${customer.id}`);
            return;
        }

        router.visit(returnTo, { headers: navigationPopHeaders });
    };

    const localizePackageName = (value: LocalizedText | null | undefined): string => {
        if (!value) {
            return '—';
        }

        return value[locale] ?? value.en ?? value.my ?? value.zh ?? '—';
    };
    const [statusOpen, setStatusOpen] = useState(false);
    const [formOpen, setFormOpen] = useState(false);
    const [adjustOpen, setAdjustOpen] = useState(false);
    const [unbindAccount, setUnbindAccount] = useState(false);
    const [statusProcessing, setStatusProcessing] = useState(false);
    const [unbindProcessing, setUnbindProcessing] = useState(false);
    const [bindTouched, setBindTouched] = useState(false);

    const bindForm = useForm({ account_number: '' });

    const getBindValidationError = (value: string) => {
        if (!value.trim()) {
            return t('customers.account_number_required');
        }

        if (Array.from(value).length > 32) {
            return t('customers.account_number_max');
        }

        return undefined;
    };

    const bindValidationError = bindTouched ? getBindValidationError(bindForm.data.account_number) : undefined;

    const bindServerError = bindForm.errors.account_number ? t(bindForm.errors.account_number) : undefined;

    const bindError = bindValidationError ?? bindServerError ?? (bindTouched ? undefined : errors.account_number);

    const updateAccountNumber = (value: string) => {
        bindForm.clearErrors('account_number');
        bindForm.setData('account_number', value);
        setBindTouched(true);
    };

    const nextStatus = customer.status === 'active' ? 'suspended' : 'active';
    const filteredHistory = useMemo(
        () => packageHistory.filter((row) => row.status === packageTab),
        [packageTab, packageHistory],
    );

    const bindFilters = (
        <form
            className="hidden items-start gap-1.5 sm:flex"
            onSubmit={(event) => {
                event.preventDefault();
                setBindTouched(true);

                if (getBindValidationError(bindForm.data.account_number)) {
                    return;
                }

                bindForm.post(`/customers/${customer.id}/account`, {
                    preserveScroll: true,
                    onSuccess: () => bindForm.reset(),
                });
            }}
        >
            <div className="flex flex-col">
                <FormControl icon={HashIcon} compact className="w-80">
                    <Input
                        value={bindForm.data.account_number}
                        onChange={(event) => updateAccountNumber(event.target.value)}
                        placeholder={t('customers.account_number')}
                        className="h-8 text-[12px] placeholder:text-[12px]"
                        aria-invalid={Boolean(bindError)}
                    />
                </FormControl>

                <p className={`mt-1 min-h-4 px-1 text-[11px] leading-4 ${bindError ? 'text-danger' : 'invisible'}`}>
                    {bindError || '\u00A0'}
                </p>
            </div>

            <Button
                type="submit"
                size="sm"
                className="h-8 shrink-0 gap-1 px-2.5 text-[11px]"
                disabled={bindForm.processing}
            >
                {bindForm.processing ? (
                    <Spinner size="xs" className="text-current" />
                ) : (
                    <Link2Icon className="size-3.5" strokeWidth={1.85} />
                )}

                {t('customers.bind_account')}
            </Button>
        </form>
    );

    const packageTabs = (
        <div className="flex h-7 items-center rounded-[4px] border border-input bg-muted/40 p-0.5">
            {(['active', 'expired'] as const).map((tab) => (
                <button
                    key={tab}
                    type="button"
                    onClick={() => setPackageTab(tab)}
                    className={cn(
                        'h-full rounded-[3px] px-2.5 text-[10px] font-medium transition-colors duration-200',
                        packageTab === tab
                            ? 'bg-primary/12 text-primary'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {t(`status.${tab}`)}
                </button>
            ))}
        </div>
    );

    return (
        <>
            <Head title={customer.name} />
            <PageContent className="gap-4 pb-24 sm:pb-8">
                <div className="flex items-center justify-between gap-3">
                    <PageHeader title={customer.name} description={formatPhoneLocal(customer.phone)} />
                    <BackButton onClick={goBackToCustomerList} fallback={returnTo} />
                </div>

                {!showAllTransactions ? (
                    <CustomerProfileCard
                        name={customer.name}
                        phone={customer.phone}
                        status={customer.status}
                        canUpdate={canUpdate}
                        joined={customer.created_at}
                        walletBalance={wallet.balance}
                        transactionOverview={wallet.transaction_overview}
                        onEdit={can('customers.update') ? () => setFormOpen(true) : undefined}
                        onViewTransactions={() =>
                            router.get(`/customers/${customer.id}`, { transactions: 'all' }, { preserveScroll: true })
                        }
                        onToggleStatus={can('customers.update') ? () => setStatusOpen(true) : undefined}
                        onAdjustWallet={can('customers.update') ? () => setAdjustOpen(true) : undefined}
                    />
                ) : null}

                {!showAllTransactions ? (
                    <>
                        <DetailSection
                            icon={WifiIcon}
                            title={t('customers.broadband_accounts')}
                            description={t('customers.broadband_hint')}
                            actions={can('customers.update') ? (!accountBinding ? bindFilters : undefined) : null}
                        >
                            <DataTable
                                data={accountBinding ? [accountBinding] : []}
                                getRowId={(row) => row.account_number}
                                emptyLabel={t('customers.no_accounts')}
                                numbered={false}
                                showSearch={false}
                                directActions
                                className="shadow-none"
                                actions={(row) =>
                                    can('customers.update') ? (
                                        <TableActionButton
                                            label={t('customers.remove_account')}
                                            icon={UnlinkIcon}
                                            tone="danger"
                                            size="sm"
                                            onClick={() => setUnbindAccount(true)}
                                        />
                                    ) : null
                                }
                                columns={[
                                    {
                                        id: 'account_number',
                                        header: t('customers.account_number'),
                                        className: 'font-mono text-[12px]',
                                        mobile: 'title',
                                        searchValue: (row) => row.account_number,
                                        cell: (row) => <span>{row.account_number}</span>,
                                    },
                                    {
                                        id: 'package',
                                        header: t('customers.package'),
                                        mobile: 'subtitle',
                                        searchValue: (row) => localizePackageName(row.package_name),
                                        cell: (row) => {
                                            const packageName = localizePackageName(row.package_name);

                                            return (
                                                <span className={!packageName ? 'text-muted-foreground' : undefined}>
                                                    {packageName || '-'}
                                                </span>
                                            );
                                        },
                                    },
                                    {
                                        id: 'status',
                                        header: t('common.status'),
                                        mobile: 'badge',
                                        searchValue: (row) => t(`status.${row.status}`),
                                        cell: (row) => <StatusBadge status={row.status} />,
                                    },
                                ]}
                            />
                        </DetailSection>

                        <DetailSection
                            icon={HistoryIcon}
                            title={t('customers.package_history')}
                            description={t('customers.package_history_hint')}
                            actions={packageTabs}
                        >
                            <DataTable
                                data={filteredHistory}
                                getRowId={(row) => String(row.id)}
                                emptyLabel={t('customers.no_packages')}
                                numbered={false}
                                showSearch={false}
                                className="shadow-none"
                                columns={[
                                    {
                                        id: 'package_name',
                                        header: t('customers.package'),
                                        mobile: 'title',
                                        searchValue: (row) => localizePackageName(row.package_name),
                                        cell: (row) => (
                                            <span
                                                className={
                                                    row.status === 'expired' ? 'text-muted-foreground' : undefined
                                                }
                                            >
                                                {localizePackageName(row.package_name)}
                                            </span>
                                        ),
                                    },
                                    {
                                        id: 'account_number',
                                        header: t('customers.account_number'),
                                        className: 'font-mono text-[12px]',
                                        mobile: 'subtitle',
                                        searchValue: (row) => row.account_number ?? '',
                                        cell: (row) => row.account_number ?? '—',
                                    },
                                    {
                                        id: 'dates',
                                        header: t('customers.validity'),
                                        mobile: 'meta',
                                        searchValue: (row) => `${row.start_date ?? ''} ${row.expiry_date ?? ''}`,
                                        cell: (row) => (
                                            <span className="text-[12px]">
                                                {row.start_date ? formatDate(row.start_date) : '—'} →{' '}
                                                {row.expiry_date ? formatDate(row.expiry_date) : '—'}
                                            </span>
                                        ),
                                    },
                                    {
                                        id: 'status',
                                        header: t('common.status'),
                                        mobile: 'badge',
                                        searchValue: (row) => t(`status.${row.status}`),
                                        cell: (row) => <StatusBadge status={row.status} />,
                                    },
                                ]}
                            />
                        </DetailSection>

                        <DetailSection
                            icon={TicketIcon}
                            title={t('customers.top_up_history')}
                            description={t('customers.top_up_history_hint')}
                        >
                            <DataTable
                                data={topUpHistory.data}
                                getRowId={(row) => String(row.id)}
                                emptyLabel={t('customers.no_top_ups')}
                                numbered={false}
                                showSearch={false}
                                className="shadow-none"
                                pagination={topUpHistory}
                                columns={[
                                    {
                                        id: 'serial_no',
                                        header: t('customers.serial_no'),
                                        mobile: 'title',
                                        searchValue: (row) => row.serial_no,
                                        cell: (row) => <span className="font-mono text-[12px]">{row.serial_no}</span>,
                                    },
                                    {
                                        id: 'amount',
                                        header: t('customers.amount'),
                                        className: 'tabular-nums',
                                        mobile: 'subtitle',
                                        searchValue: (row) => row.amount,
                                        cell: (row) => (
                                            <span className="font-heading">
                                                <span className="tabular-nums text-primary text-[14px]">
                                                    {formatTopUpNumber(row.amount)}
                                                </span>
                                                <span className="ml-1 text-[12px]">{TOP_UP_CARD_CURRENCY}</span>
                                            </span>
                                        ),
                                    },
                                    {
                                        id: 'status',
                                        header: t('common.status'),
                                        mobile: 'badge',
                                        searchValue: (row) => t(`status.${row.status}`),
                                        cell: (row) => <StatusBadge status={row.status} />,
                                    },
                                    {
                                        id: 'redeemed_at',
                                        header: t('customers.redeemed_at'),
                                        className: 'text-[12px]',
                                        mobile: 'meta',
                                        searchValue: (row) => row.redeemed_at ?? '',
                                        cell: (row) => formatDateTime(row.redeemed_at),
                                    },
                                ]}
                            />
                        </DetailSection>
                    </>
                ) : null}

                {!showAllTransactions ? (
                    <div className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-surface/95 p-3 backdrop-blur-sm sm:hidden">
                        <form
                            className="flex flex-col gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                setBindTouched(true);

                                if (getBindValidationError(bindForm.data.account_number)) {
                                    return;
                                }

                                bindForm.post(`/customers/${customer.id}/account`, {
                                    preserveScroll: true,
                                    onSuccess: () => bindForm.reset(),
                                });
                            }}
                        >
                            <Label htmlFor="account_number" className="sr-only">
                                {t('customers.account_number')}
                            </Label>
                            <div className="flex gap-2">
                                <FormControl icon={HashIcon}>
                                    <Input
                                        id="account_number"
                                        value={bindForm.data.account_number}
                                        onChange={(event) => updateAccountNumber(event.target.value)}
                                        placeholder={t('customers.account_number')}
                                        className="h-8 text-[11px] placeholder:text-[11px]"
                                        aria-invalid={Boolean(bindError)}
                                    />
                                </FormControl>
                                <Button type="submit" size="sm" className="shrink-0" disabled={bindForm.processing}>
                                    {bindForm.processing ? (
                                        <Spinner size="xs" className="text-current" />
                                    ) : (
                                        <Link2Icon />
                                    )}
                                    {t('customers.bind_account')}
                                </Button>
                            </div>
                            <p
                                className={`min-h-4 px-1 text-[11px] leading-4 ${
                                    bindError ? 'text-danger' : 'invisible'
                                }`}
                            >
                                {bindError || '\u00A0'}
                            </p>
                            {canUpdate ? (
                                <Button
                                    type="button"
                                    variant={customer.status === 'active' ? 'destructive' : 'primary'}
                                    className="w-full"
                                    onClick={() => setStatusOpen(true)}
                                >
                                    {customer.status === 'active' ? <BanIcon /> : <UserCheckIcon />}
                                    {customer.status === 'active' ? t('customers.suspend') : t('customers.reactivate')}
                                </Button>
                            ) : null}
                        </form>
                    </div>
                ) : null}

                {showAllTransactions && transactionPage ? (
                    <DetailSection
                        icon={WalletIcon}
                        title={t('customers.transactions')}
                        description={t('transactions.description')}
                    >
                        <TransactionsTable
                            transactions={transactionPage}
                            filters={transactionFilters}
                            filterOptions={transactionFilterOptions}
                            scope="customer"
                            baseUrl={`/customers/${customer.id}`}
                            embeddedCustomer
                        />
                    </DetailSection>
                ) : null}
            </PageContent>

            <CustomerFormDialog open={formOpen} onOpenChange={setFormOpen} customer={customer} />

            <WalletAdjustDialog
                open={adjustOpen}
                onOpenChange={setAdjustOpen}
                customerId={customer.id}
                customerName={customer.name}
            />

            <ConfirmDialog
                open={statusOpen}
                onOpenChange={setStatusOpen}
                title={customer.status === 'active' ? t('customers.suspend_title') : t('customers.reactivate_title')}
                description={
                    customer.status === 'active'
                        ? t('customers.suspend_description')
                        : t('customers.reactivate_description')
                }
                confirmLabel={customer.status === 'active' ? t('customers.suspend') : t('customers.reactivate')}
                destructive={customer.status === 'active'}
                processing={statusProcessing}
                onConfirm={() => {
                    router.patch(
                        `/customers/${customer.id}/status`,
                        { status: nextStatus },
                        {
                            preserveScroll: true,
                            onStart: () => setStatusProcessing(true),
                            onFinish: () => setStatusProcessing(false),
                            onSuccess: () => setStatusOpen(false),
                        },
                    );
                }}
            />

            <ConfirmDialog
                open={unbindAccount}
                onOpenChange={(open) => {
                    if (!open) {
                        setUnbindAccount(false);
                    }
                }}
                title={t('customers.remove_account_title')}
                description={t('customers.remove_account_description')}
                confirmLabel={t('customers.remove_account')}
                destructive
                processing={unbindProcessing}
                onConfirm={() => {
                    if (!unbindAccount) {
                        return;
                    }

                    router.delete(`/customers/${customer.id}/accounts`, {
                        preserveScroll: true,
                        onStart: () => setUnbindProcessing(true),
                        onFinish: () => setUnbindProcessing(false),
                        onSuccess: () => setUnbindAccount(false),
                    });
                }}
            />
        </>
    );
}
