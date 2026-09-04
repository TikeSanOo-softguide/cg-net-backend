import { useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    BanIcon,
    HashIcon,
    HistoryIcon,
    Link2Icon,
    PackageIcon,
    TicketIcon,
    UnlinkIcon,
    UserCheckIcon,
    WalletIcon,
    WifiIcon,
} from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { CustomerFormDialog } from '@/components/customer/CustomerFormDialog';
import { CustomerProfileCard } from '@/components/customer/CustomerProfileCard';
import { DetailSection } from '@/components/customer/DetailSection';
import { MetaCell } from '@/components/customer/MetaCell';
import { formatPhoneLocal } from '@/lib/phone';
import { DataTable } from '@/components/DataTable';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { FormControl } from '@/components/ui/form-control';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type Customer = {
    id: number;
    name: string;
    phone: string;
    status: string;
    created_at: string | null;
};

type BroadbandAccountRow = {
    id: number;
    account_number: string;
    customer_name: string;
    status: string;
    package_name: string | null;
};

type PackageRow = {
    id: number;
    package_name: string | null;
    account_number: string | null;
    start_date: string | null;
    expiry_date: string | null;
    auto_renew: boolean;
    status: string;
};

type TopUpRow = {
    id: number;
    serial_no: string;
    amount: string;
    status: string;
    redeemed_at: string | null;
};

type CustomersShowProps = {
    customer: Customer;
    broadbandAccounts: BroadbandAccountRow[];
    currentPackages: PackageRow[];
    packageHistory: PackageRow[];
    wallet: {
        balance_mmk: string;
    };
    topUpHistory: TopUpRow[];
};

function formatMmk(value: string): string {
    return `${Number(value).toLocaleString()} MMK`;
}

export default function CustomersShow({
    customer,
    broadbandAccounts,
    currentPackages,
    packageHistory,
    wallet,
    topUpHistory,
}: CustomersShowProps) {
    const { t } = useTranslation();
    const page = usePage();
    const errors = page.props.errors as Record<string, string | undefined>;
    const [packageTab, setPackageTab] = useState<'active' | 'expired'>('expired');
    const [statusOpen, setStatusOpen] = useState(false);
    const [formOpen, setFormOpen] = useState(false);
    const [unbindAccount, setUnbindAccount] = useState<BroadbandAccountRow | null>(null);
    const [statusProcessing, setStatusProcessing] = useState(false);
    const [unbindProcessing, setUnbindProcessing] = useState(false);

    const bindForm = useForm({ account_number: '' });

    const nextStatus = customer.status === 'active' ? 'suspended' : 'active';
    const filteredHistory = useMemo(
        () => packageHistory.filter((row) => row.status === packageTab),
        [packageTab, packageHistory],
    );

    const bindFilters = (
        <form
            className="hidden gap-1.5 sm:flex"
            onSubmit={(event) => {
                event.preventDefault();
                bindForm.post(`/customers/${customer.id}/accounts`, {
                    preserveScroll: true,
                    onSuccess: () => bindForm.reset(),
                });
            }}
        >
            <FormControl icon={HashIcon} compact className="max-w-44">
                <Input
                    value={bindForm.data.account_number}
                    onChange={(event) => bindForm.setData('account_number', event.target.value)}
                    placeholder={t('customers.account_number')}
                    aria-invalid={Boolean(errors.account_number)}
                />
            </FormControl>
            <Button type="submit" size="sm" className="h-8 gap-1 px-2.5 text-[11px]" disabled={bindForm.processing}>
                {bindForm.processing ? <Spinner size="xs" className="text-current" /> : <Link2Icon className="size-3.5" strokeWidth={1.85} />}
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
                        packageTab === tab ? 'bg-primary/12 text-primary' : 'text-muted-foreground hover:text-foreground',
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
                <PageHeader title={customer.name} description={formatPhoneLocal(customer.phone)} />

                <CustomerProfileCard
                    name={customer.name}
                    phone={customer.phone}
                    status={customer.status}
                    joined={customer.created_at}
                    walletBalance={formatMmk(wallet.balance_mmk)}
                    onEdit={() => setFormOpen(true)}
                    onToggleStatus={() => setStatusOpen(true)}
                />

                <div className="grid grid-cols-1 items-start gap-3 xl:grid-cols-2">
                    <DetailSection
                        icon={WifiIcon}
                        title={t('customers.broadband_accounts')}
                        description={t('customers.broadband_hint')}
                        actions={bindFilters}
                    >
                        <DataTable
                            data={broadbandAccounts}
                            getRowId={(row) => String(row.id)}
                            emptyLabel={t('customers.no_accounts')}
                            numbered={false}
                            showSearch={false}
                            className="shadow-none"
                            actions={(row) => (
                                <TableActionButton
                                    label={t('customers.remove_account')}
                                    icon={UnlinkIcon}
                                    tone="danger"
                                    size="sm"
                                    onClick={() => setUnbindAccount(row)}
                                />
                            )}
                            columns={[
                                {
                                    id: 'account_number',
                                    header: t('customers.account_number'),
                                    className: 'font-mono text-[12px]',
                                    mobile: 'title',
                                    searchValue: (row) => row.account_number,
                                    cell: (row) => <MetaCell icon={HashIcon}>{row.account_number}</MetaCell>,
                                },
                                {
                                    id: 'package',
                                    header: t('customers.package'),
                                    mobile: 'subtitle',
                                    searchValue: (row) => row.package_name ?? '',
                                    cell: (row) => (
                                        <MetaCell icon={PackageIcon} muted={! row.package_name}>
                                            {row.package_name ?? '—'}
                                        </MetaCell>
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
                        {errors.account_number ? <p className="mt-2 px-1 text-[11px] text-danger">{errors.account_number}</p> : null}
                    </DetailSection>

                    <DetailSection
                        icon={PackageIcon}
                        title={t('customers.current_package')}
                        description={t('customers.current_package_hint')}
                    >
                        <DataTable
                            data={currentPackages}
                            getRowId={(row) => String(row.id)}
                            emptyLabel={t('customers.no_current_package')}
                            numbered={false}
                            showSearch={false}
                            className="shadow-none"
                            columns={[
                                {
                                    id: 'package_name',
                                    header: t('customers.package'),
                                    mobile: 'title',
                                    searchValue: (row) => row.package_name ?? '',
                                    cell: (row) => <MetaCell icon={PackageIcon}>{row.package_name ?? '—'}</MetaCell>,
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
                                        <span className="font-mono text-[11px] text-muted-foreground">
                                            {row.start_date ?? '—'} → {row.expiry_date ?? '—'}
                                        </span>
                                    ),
                                },
                                {
                                    id: 'status',
                                    header: t('common.status'),
                                    mobile: 'badge',
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
                                    searchValue: (row) => row.package_name ?? '',
                                    cell: (row) => (
                                        <MetaCell icon={PackageIcon} muted={row.status === 'expired'}>
                                            {row.package_name ?? '—'}
                                        </MetaCell>
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
                                        <span className="font-mono text-[11px] text-muted-foreground">
                                            {row.start_date ?? '—'} → {row.expiry_date ?? '—'}
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
                            data={topUpHistory}
                            getRowId={(row) => String(row.id)}
                            emptyLabel={t('customers.no_top_ups')}
                            numbered={false}
                            showSearch={false}
                            className="shadow-none"
                            columns={[
                                {
                                    id: 'serial_no',
                                    header: t('customers.serial_no'),
                                    mobile: 'title',
                                    searchValue: (row) => row.serial_no,
                                    cell: (row) => (
                                        <MetaCell icon={TicketIcon}>
                                            <span className="font-mono text-[12px]">{row.serial_no}</span>
                                        </MetaCell>
                                    ),
                                },
                                {
                                    id: 'amount',
                                    header: t('customers.amount'),
                                    className: 'tabular-nums',
                                    mobile: 'subtitle',
                                    searchValue: (row) => row.amount,
                                    cell: (row) => <MetaCell icon={WalletIcon}>{formatMmk(row.amount)}</MetaCell>,
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
                                    header: t('dashboard.date'),
                                    className: 'font-mono text-[11px] text-muted-foreground',
                                    mobile: 'meta',
                                    searchValue: (row) => row.redeemed_at ?? '',
                                    cell: (row) => row.redeemed_at ?? '—',
                                },
                            ]}
                        />
                    </DetailSection>
                </div>

                <div className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-surface/95 p-3 backdrop-blur-sm sm:hidden">
                    <form
                        className="flex flex-col gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            bindForm.post(`/customers/${customer.id}/accounts`, {
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
                                    onChange={(event) => bindForm.setData('account_number', event.target.value)}
                                    placeholder={t('customers.account_number')}
                                />
                            </FormControl>
                            <Button type="submit" size="sm" className="shrink-0" disabled={bindForm.processing}>
                                {bindForm.processing ? <Spinner size="xs" className="text-current" /> : <Link2Icon />}
                                {t('customers.bind_account')}
                            </Button>
                        </div>
                        <Button
                            type="button"
                            variant={customer.status === 'active' ? 'destructive' : 'primary'}
                            className="w-full"
                            onClick={() => setStatusOpen(true)}
                        >
                            {customer.status === 'active' ? <BanIcon /> : <UserCheckIcon />}
                            {customer.status === 'active' ? t('customers.suspend') : t('customers.reactivate')}
                        </Button>
                    </form>
                </div>
            </PageContent>

            <CustomerFormDialog open={formOpen} onOpenChange={setFormOpen} customer={customer} />

            <ConfirmDialog
                open={statusOpen}
                onOpenChange={setStatusOpen}
                title={customer.status === 'active' ? t('customers.suspend_title') : t('customers.reactivate_title')}
                description={customer.status === 'active' ? t('customers.suspend_description') : t('customers.reactivate_description')}
                confirmLabel={customer.status === 'active' ? t('customers.suspend') : t('customers.reactivate')}
                destructive={customer.status === 'active'}
                processing={statusProcessing}
                onConfirm={() => {
                    router.patch(`/customers/${customer.id}/status`, { status: nextStatus }, {
                        preserveScroll: true,
                        onStart: () => setStatusProcessing(true),
                        onFinish: () => setStatusProcessing(false),
                        onSuccess: () => setStatusOpen(false),
                    });
                }}
            />

            <ConfirmDialog
                open={unbindAccount !== null}
                onOpenChange={(open) => {
                    if (! open) {
                        setUnbindAccount(null);
                    }
                }}
                title={t('customers.remove_account_title')}
                description={t('customers.remove_account_description')}
                confirmLabel={t('customers.remove_account')}
                destructive
                processing={unbindProcessing}
                onConfirm={() => {
                    if (! unbindAccount) {
                        return;
                    }

                    router.delete(`/customers/${customer.id}/accounts/${unbindAccount.id}`, {
                        preserveScroll: true,
                        onStart: () => setUnbindProcessing(true),
                        onFinish: () => setUnbindProcessing(false),
                        onSuccess: () => setUnbindAccount(null),
                    });
                }}
            />
        </>
    );
}
