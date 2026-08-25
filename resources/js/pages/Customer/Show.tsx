import { useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    BanIcon,
    CalendarIcon,
    CircleDotIcon,
    HashIcon,
    IdCardIcon,
    LanguagesIcon,
    Link2Icon,
    MailIcon,
    MapPinIcon,
    PhoneIcon,
    SquarePenIcon,
    UnlinkIcon,
    UserCheckIcon,
    UserIcon,
    WalletIcon,
} from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { CustomerFormDialog } from '@/components/customer/CustomerFormDialog';
import { DataTable } from '@/components/DataTable';
import { DetailPanel } from '@/components/DetailPanel';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { Button } from '@/components/ui/button';
import { FormControl } from '@/components/ui/form-control';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
    const errors = page.props.errors as Record<string, string | undefined>;
    const [packageTab, setPackageTab] = useState<'active' | 'expired'>('active');
    const [statusOpen, setStatusOpen] = useState(false);
    const [formOpen, setFormOpen] = useState(false);
    const [unbindAccount, setUnbindAccount] = useState<BroadbandAccountRow | null>(null);
    const [statusProcessing, setStatusProcessing] = useState(false);
    const [unbindProcessing, setUnbindProcessing] = useState(false);

    const bindForm = useForm({ account_number: '' });

    const nextStatus = customer.status === 'active' ? 'suspended' : 'active';
    const filteredPackages = useMemo(
        () => packages.filter((row) => row.status === packageTab),
        [packageTab, packages],
    );

    return (
        <>
            <Head title={customer.name} />
            <PageContent className="pb-24 sm:pb-8">
                <PageHeader title={customer.name} description={customer.phone} />

                <div className="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,560px)_minmax(0,1fr)]">
                    <DetailPanel
                        title={t('customers.profile')}
                        description={t('customers.edit_description')}
                        icon={UserIcon}
                        actions={
                            <div className="hidden items-center gap-2 sm:flex">
                                <StatusBadge status={customer.status} />
                                <Button type="button" size="sm" variant="outline" onClick={() => setFormOpen(true)}>
                                    <SquarePenIcon />
                                    {t('common.edit')}
                                </Button>
                            </div>
                        }
                        footer={
                            <>
                                <Button type="button" size="sm" variant="outline" className="h-8 w-[120px] rounded-[4px] sm:hidden" onClick={() => setFormOpen(true)}>
                                    <SquarePenIcon className="size-3.5" strokeWidth={1.85} />
                                    {t('common.edit')}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={customer.status === 'active' ? 'destructive' : 'primary'}
                                    className="h-8 min-w-[120px] rounded-[4px]"
                                    onClick={() => setStatusOpen(true)}
                                >
                                    {customer.status === 'active' ? <BanIcon className="size-3.5" strokeWidth={1.85} /> : <UserCheckIcon className="size-3.5" strokeWidth={1.85} />}
                                    {customer.status === 'active' ? t('customers.suspend') : t('customers.reactivate')}
                                </Button>
                            </>
                        }
                    >
                        <FormField label={t('customers.name')} htmlFor="detail-name" icon={UserIcon} className="sm:col-span-2">
                            <Input id="detail-name" value={customer.name} readOnly />
                        </FormField>
                        <FormField label={t('customers.phone')} htmlFor="detail-phone" icon={PhoneIcon}>
                            <Input id="detail-phone" value={customer.phone} readOnly />
                        </FormField>
                        <FormField label={t('customers.nrc')} htmlFor="detail-nrc" icon={IdCardIcon}>
                            <Input id="detail-nrc" value={customer.nrc_number} readOnly />
                        </FormField>
                        <FormField label={t('customers.email')} htmlFor="detail-email" icon={MailIcon}>
                            <Input id="detail-email" value={customer.email ?? '—'} readOnly />
                        </FormField>
                        <FormField label={t('customers.language')} htmlFor="detail-language" icon={LanguagesIcon}>
                            <Input id="detail-language" value={t(`language.${customer.language_pref}`)} readOnly />
                        </FormField>
                        <FormField label={t('common.status')} htmlFor="detail-status" icon={CircleDotIcon}>
                            <div id="detail-status" className="flex h-10 items-center">
                                <StatusBadge status={customer.status} />
                            </div>
                        </FormField>
                        <FormField label={t('customers.joined')} htmlFor="detail-joined" icon={CalendarIcon}>
                            <Input id="detail-joined" value={customer.created_at ?? '—'} readOnly />
                        </FormField>
                        <FormField label={t('customers.address')} htmlFor="detail-address" icon={MapPinIcon} className="sm:col-span-2">
                            <Textarea id="detail-address" value={customer.address ?? '—'} readOnly rows={3} />
                        </FormField>
                    </DetailPanel>

                    <DetailPanel
                        title={t('customers.wallet')}
                        description={t('customers.wallet_balance')}
                        icon={WalletIcon}
                    >
                        <FormField label={t('customers.wallet_balance')} htmlFor="detail-wallet" icon={WalletIcon} className="sm:col-span-2">
                            <Input id="detail-wallet" value={formatMmk(wallet.balance_mmk)} readOnly className="font-heading text-base font-semibold" />
                        </FormField>
                    </DetailPanel>
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
                            <FormControl icon={HashIcon} className="max-w-56">
                                <Input
                                    value={bindForm.data.account_number}
                                    onChange={(event) => bindForm.setData('account_number', event.target.value)}
                                    placeholder={t('customers.account_number')}
                                    aria-invalid={Boolean(errors.account_number)}
                                />
                            </FormControl>
                            <Button type="submit" size="sm" disabled={bindForm.processing}>
                                <Link2Icon />
                                {t('customers.bind_account')}
                            </Button>
                        </form>
                    }
                    actions={(row) => (
                        <TableActionButton
                            label={t('customers.remove_account')}
                            icon={UnlinkIcon}
                            tone="danger"
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
                            cell: (row) => <StatusBadge status={row.status} />,
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
                        <div className="flex h-10 w-full items-center rounded-[6px] border border-input bg-surface p-0.5 sm:w-auto">
                            {(['active', 'expired'] as const).map((tab) => (
                                <button
                                    key={tab}
                                    type="button"
                                    onClick={() => setPackageTab(tab)}
                                    className={cn(
                                        'h-full rounded-[6px] px-3 text-sm font-medium transition-colors duration-200',
                                        packageTab === tab ? 'bg-primary/12 text-primary' : 'text-muted-foreground hover:text-foreground',
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
                            cell: (row) => <StatusBadge status={row.status} />,
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
                            cell: (row) => <StatusBadge status={row.status} />,
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
                            <FormControl icon={HashIcon}>
                                <Input
                                    id="account_number"
                                    value={bindForm.data.account_number}
                                    onChange={(event) => bindForm.setData('account_number', event.target.value)}
                                    placeholder={t('customers.account_number')}
                                />
                            </FormControl>
                            <Button type="submit" size="sm" className="shrink-0" disabled={bindForm.processing}>
                                <Link2Icon />
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

            <CustomerFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                customer={customer}
            />

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
