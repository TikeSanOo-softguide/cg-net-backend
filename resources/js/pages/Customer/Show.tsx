import { useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type Customer = {
    id: number;
    name: string;
    phone: string;
    nrc_number: string;
    email: string | null;
    address: string | null;
    language_pref: string;
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
    speed_mbps: number | null;
    data_gb: number | null;
};

type WalletTransactionRow = {
    id: number;
    type: string;
    amount: string;
    reference_id: string | null;
    status: string;
    created_at: string | null;
};

type CustomersShowProps = {
    customer: Customer;
    broadbandAccounts: BroadbandAccountRow[];
    packages: PackageRow[];
    wallet: {
        balance_mmk: string;
        transactions: WalletTransactionRow[];
    };
};

function formatMmk(value: string): string {
    return `${Number(value).toLocaleString()} MMK`;
}

export default function CustomersShow({ customer, broadbandAccounts, packages, wallet }: CustomersShowProps) {
    const { t } = useTranslation();
    const page = usePage();
    const flash = page.props.flash;
    const errors = page.props.errors as Record<string, string | undefined>;
    const [packageTab, setPackageTab] = useState<'active' | 'expired'>('active');
    const [statusOpen, setStatusOpen] = useState(false);
    const [unbindAccount, setUnbindAccount] = useState<BroadbandAccountRow | null>(null);
    const [statusProcessing, setStatusProcessing] = useState(false);
    const [unbindProcessing, setUnbindProcessing] = useState(false);

    const bindForm = useForm({ account_number: '' });

    const nextStatus = customer.status === 'active' ? 'suspended' : 'active';
    const filteredPackages = useMemo(
        () => packages.filter((row) => row.status === packageTab),
        [packageTab, packages],
    );

    const profileFields = [
        { label: t('customers.phone'), value: customer.phone },
        { label: t('customers.nrc'), value: customer.nrc_number },
        { label: t('customers.email'), value: customer.email ?? '—' },
        { label: t('customers.address'), value: customer.address ?? '—' },
        { label: t('customers.language'), value: t(`language.${customer.language_pref}`) },
        { label: t('customers.joined'), value: customer.created_at ?? '—' },
    ];

    return (
        <>
            <Head title={customer.name} />
            <div className="flex w-full flex-col gap-5 pt-6 pb-24 lg:pt-8 sm:pb-8">
                <PageHeader
                    eyebrow={t('menu.customers_list')}
                    title={customer.name}
                    description={customer.phone}
                    actions={
                        <div className="hidden items-center gap-2 sm:flex">
                            <StatusBadge status={customer.status} />
                            <Button type="button" size="sm" variant={customer.status === 'active' ? 'destructive' : 'primary'} onClick={() => setStatusOpen(true)}>
                                {customer.status === 'active' ? t('customers.suspend') : t('customers.reactivate')}
                            </Button>
                        </div>
                    }
                />
                {flash.success ? (
                    <p className="rounded-[8px] bg-primary/10 px-3 py-2 text-sm text-foreground">{t(flash.success)}</p>
                ) : null}

                <div className="flex sm:hidden">
                    <StatusBadge status={customer.status} />
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card className="gap-0 py-0">
                        <CardHeader className="px-4 py-3 sm:px-5">
                            <CardTitle className="text-sm font-semibold">{t('customers.profile')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-3 px-4 pb-4 sm:grid-cols-2 sm:px-5">
                            {profileFields.map((field) => (
                                <div key={field.label} className="min-w-0">
                                    <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">{field.label}</p>
                                    <p className="mt-0.5 truncate text-[13px] text-foreground">{field.value}</p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card className="gap-0 py-0">
                        <CardHeader className="px-4 py-3 sm:px-5">
                            <CardTitle className="text-sm font-semibold">{t('customers.wallet')}</CardTitle>
                        </CardHeader>
                        <CardContent className="px-4 pb-4 sm:px-5">
                            <p className="font-heading text-2xl font-semibold tracking-tight">{formatMmk(wallet.balance_mmk)}</p>
                            <p className="mt-1 text-xs text-muted-foreground">{t('customers.wallet_balance')}</p>
                        </CardContent>
                    </Card>
                </div>

                <DataTable
                    title={t('customers.broadband_accounts')}
                    data={broadbandAccounts}
                    getRowId={(row) => String(row.id)}
                    searchPlaceholder={t('customers.search_accounts')}
                    emptyLabel={t('customers.no_accounts')}
                    filters={
                        <form
                            className="hidden gap-2 sm:flex"
                            onSubmit={(event) => {
                                event.preventDefault();
                                bindForm.post(`/customers/${customer.id}/accounts`, {
                                    preserveScroll: true,
                                    onSuccess: () => bindForm.reset(),
                                });
                            }}
                        >
                            <Input
                                value={bindForm.data.account_number}
                                onChange={(event) => bindForm.setData('account_number', event.target.value)}
                                placeholder={t('customers.account_number')}
                                className="h-8 max-w-56 text-xs"
                                aria-invalid={Boolean(errors.account_number)}
                            />
                            <Button type="submit" size="sm" disabled={bindForm.processing}>
                                {t('customers.bind_account')}
                            </Button>
                        </form>
                    }
                    actions={(row) => (
                        <Button type="button" variant="outline" size="sm" className="h-7 text-[11px]" onClick={() => setUnbindAccount(row)}>
                            {t('customers.remove_account')}
                        </Button>
                    )}
                    columns={[
                        {
                            id: 'account_number',
                            header: t('customers.account_number'),
                            className: 'font-mono text-[12px]',
                            mobile: 'title',
                            searchValue: (row) => row.account_number,
                            cell: (row) => row.account_number,
                        },
                        {
                            id: 'package',
                            header: t('customers.package'),
                            mobile: 'subtitle',
                            searchValue: (row) => row.package_name ?? '',
                            cell: (row) => row.package_name ?? '—',
                        },
                        {
                            id: 'status',
                            header: t('common.status'),
                            mobile: 'badge',
                            searchValue: (row) => t(`status.${row.status}`),
                            cell: (row) => <StatusBadge status={row.status} className="px-1.5 py-0 text-[11px]" />,
                        },
                    ]}
                />
                {errors.account_number ? <p className="-mt-3 text-xs text-danger">{errors.account_number}</p> : null}

                <DataTable
                    title={t('customers.packages')}
                    data={filteredPackages}
                    getRowId={(row) => String(row.id)}
                    searchPlaceholder={t('customers.search_packages')}
                    emptyLabel={t('customers.no_packages')}
                    filters={
                        <div className="flex rounded-[8px] bg-muted p-0.5">
                            {(['active', 'expired'] as const).map((tab) => (
                                <button
                                    key={tab}
                                    type="button"
                                    onClick={() => setPackageTab(tab)}
                                    className={cn(
                                        'h-7 rounded-[6px] px-3 text-xs font-medium',
                                        packageTab === tab ? 'bg-surface text-foreground shadow-sm' : 'text-muted-foreground',
                                    )}
                                >
                                    {t(`status.${tab}`)}
                                </button>
                            ))}
                        </div>
                    }
                    columns={[
                        {
                            id: 'package_name',
                            header: t('customers.package'),
                            mobile: 'title',
                            searchValue: (row) => row.package_name ?? '',
                            cell: (row) => row.package_name ?? '—',
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
                            cell: (row) => `${row.start_date ?? '—'} → ${row.expiry_date ?? '—'}`,
                        },
                        {
                            id: 'status',
                            header: t('common.status'),
                            mobile: 'badge',
                            searchValue: (row) => t(`status.${row.status}`),
                            cell: (row) => <StatusBadge status={row.status} className="px-1.5 py-0 text-[11px]" />,
                        },
                    ]}
                />

                <DataTable
                    title={t('customers.recent_transactions')}
                    data={wallet.transactions}
                    getRowId={(row) => String(row.id)}
                    searchPlaceholder={t('customers.search_transactions')}
                    emptyLabel={t('customers.no_transactions')}
                    columns={[
                        {
                            id: 'type',
                            header: t('customers.transaction_type'),
                            mobile: 'title',
                            searchValue: (row) => t(`wallet.${row.type}`),
                            cell: (row) => t(`wallet.${row.type}`),
                        },
                        {
                            id: 'amount',
                            header: t('customers.amount'),
                            className: 'font-medium',
                            mobile: 'subtitle',
                            searchValue: (row) => row.amount,
                            cell: (row) => formatMmk(row.amount),
                        },
                        {
                            id: 'status',
                            header: t('common.status'),
                            mobile: 'badge',
                            searchValue: (row) => t(`status.${row.status}`),
                            cell: (row) => <StatusBadge status={row.status} className="px-1.5 py-0 text-[11px]" />,
                        },
                        {
                            id: 'created_at',
                            header: t('dashboard.date'),
                            className: 'font-mono text-[11px] text-muted-foreground',
                            mobile: 'meta',
                            searchValue: (row) => row.created_at ?? '',
                            cell: (row) => row.created_at,
                        },
                    ]}
                />

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
                            <Input
                                id="account_number"
                                value={bindForm.data.account_number}
                                onChange={(event) => bindForm.setData('account_number', event.target.value)}
                                placeholder={t('customers.account_number')}
                                className="h-9 text-sm"
                            />
                            <Button type="submit" size="sm" className="h-9 shrink-0" disabled={bindForm.processing}>
                                {t('customers.bind_account')}
                            </Button>
                        </div>
                        <Button
                            type="button"
                            variant={customer.status === 'active' ? 'destructive' : 'primary'}
                            className="h-9 w-full"
                            onClick={() => setStatusOpen(true)}
                        >
                            {customer.status === 'active' ? t('customers.suspend') : t('customers.reactivate')}
                        </Button>
                    </form>
                </div>
            </div>

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
