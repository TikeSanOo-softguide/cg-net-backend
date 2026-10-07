import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { EyeIcon, PackageIcon, SearchIcon, XIcon } from 'lucide-react';

import { BackButton } from '@/components/BackButton';
import { CopyValueButton } from '@/components/CopyValueButton';
import { DataTable, type DataTableColumn } from '@/components/DataTable';
import { FormDialog } from '@/components/FormDialog';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { useReturnTo } from '@/hooks/useReturnTo';
import { toolbarInputClass } from '@/components/data-table/styles';
import { formatTopUpAmount } from '@/lib/top-up-cards';
import { formatDateTime } from '@/lib/utils';

type PackageOrder = {
    id: number;
    customer: { id: number | null; name: string | null; phone: string | null };
    package: string | null;
    transaction_no: string | null;
    amount: number;
    status: string;
    created_at: string | null;
    completed_at: string | null;
    snapshot: Record<string, unknown> | null;
    external_response: Record<string, unknown> | null;
    customer_package: { status: string; starts_at: string | null; expires_at: string | null } | null;
};

type Props = {
    orders: Paginated<PackageOrder>;
    search: string;
    filters: { package_id: number | null; status: string };
    filter_options: {
        packages: { id: number; label: string }[];
        statuses: string[];
    };
    open_order_id: number | null;
};

function JsonDetails({ title, value }: { title: string; value: Record<string, unknown> | null }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <pre className="max-h-56 overflow-auto rounded-md bg-muted p-3 text-xs">
                    {value ? JSON.stringify(value, null, 2) : '—'}
                </pre>
            </CardContent>
        </Card>
    );
}

export default function PackageOrdersIndex({
    orders,
    search: initialSearch,
    filters,
    filter_options,
    open_order_id,
}: Props) {
    const { t } = useTranslation();
    const returnTo = useReturnTo('');
    const [search, setSearch] = useState(initialSearch);
    const [packageId, setPackageId] = useState(filters.package_id ? String(filters.package_id) : '');
    const [status, setStatus] = useState(filters.status);
    const [selected, setSelected] = useState<PackageOrder | null>(null);
    const debounce = useRef<number>(0);

    useEffect(() => setSearch(initialSearch), [initialSearch]);
    useEffect(() => setPackageId(filters.package_id ? String(filters.package_id) : ''), [filters.package_id]);
    useEffect(() => setStatus(filters.status), [filters.status]);
    useEffect(() => {
        if (open_order_id === null) return;

        const order = orders.data.find((row) => row.id === open_order_id);
        if (order) setSelected(order);
    }, [open_order_id, orders.data]);
    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const visitOrders = (nextSearch: string, nextPackageId: string, nextStatus: string) => {
        router.get(
            '/billing/package-orders',
            {
                search: nextSearch || undefined,
                package_id: nextPackageId || undefined,
                status: nextStatus || undefined,
                return_to: returnTo || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const updateFilter = (field: 'package_id' | 'status', value: string) => {
        const nextPackageId = field === 'package_id' ? (value === 'all' ? '' : value) : packageId;
        const nextStatus = field === 'status' ? (value === 'all' ? '' : value) : status;

        field === 'package_id' ? setPackageId(nextPackageId) : setStatus(nextStatus);
        window.clearTimeout(debounce.current);
        visitOrders(search, nextPackageId, nextStatus);
    };

    const updateSearch = (value: string) => {
        setSearch(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => {
            visitOrders(value, packageId, status);
        }, 300);
    };

    const columns: DataTableColumn<PackageOrder>[] = [
        {
            id: 'transaction',
            header: t('transactions.number'),
            cell: (row) =>
                row.transaction_no ? (
                    <Link
                        className="font-mono text-xs text-primary underline-offset-4 hover:underline"
                        href={transactionHref(row.transaction_no)}
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
            header: t('transactions.customer'),
            cell: (row) =>
                row.customer.id && row.customer.name ? (
                    <Link
                        href={`/customers/${row.customer.id}`}
                        className="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        {row.customer.name}
                    </Link>
                ) : (
                    (row.customer.name ?? '—')
                ),
            searchValue: (row) => `${row.customer.name ?? ''} ${row.customer.phone ?? ''}`,
        },
        {
            id: 'package',
            header: t('menu.packages'),
            cell: (row) => row.package ?? '—',
            searchValue: (row) => row.package ?? '',
        },
        {
            id: 'amount',
            header: t('transactions.amount'),
            cell: (row) => formatTopUpAmount(row.amount),
        },
        {
            id: 'status',
            header: t('common.status'),
            cell: (row) => <StatusBadge status={row.status} />,
            searchValue: (row) => row.status,
        },
        {
            id: 'created_at',
            header: t('common.created_at'),
            cell: (row) => formatDateTime(row.created_at),
        },
    ];

    return (
        <>
            <Head title={t('menu.package_orders')} />
            <PageContent>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <PageHeader title={t('menu.package_orders')} description={t('menu.package_orders_description')} />
                    {returnTo ? <BackButton href={returnTo} /> : null}
                </div>
                <DataTable
                    data={orders.data}
                    columns={columns}
                    getRowId={(row) => String(row.id)}
                    showSearch={false}
                    filters={
                        <>
                            <FormField
                                label={t('transactions.search')}
                                htmlFor="package-order-search"
                                icon={SearchIcon}
                                rightSlot={
                                    search ? (
                                        <button
                                            type="button"
                                            aria-label={t('common.clear')}
                                            className="inline-flex size-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                                            onClick={() => updateSearch('')}
                                        >
                                            <XIcon className="size-3.5" strokeWidth={2} />
                                        </button>
                                    ) : undefined
                                }
                                className="mr-3 w-full shrink-0 sm:w-60"
                                labelClassName="text-[13px]"
                            >
                                <Input
                                    id="package-order-search"
                                    type="text"
                                    inputMode="search"
                                    autoComplete="off"
                                    value={search}
                                    onChange={(event) => updateSearch(event.target.value)}
                                    placeholder={t('package_orders.search_placeholder')}
                                    aria-label={t('package_orders.search_placeholder')}
                                    className={toolbarInputClass}
                                />
                            </FormField>
                            <FormField
                                label={t('menu.packages')}
                                htmlFor="package-order-package"
                                className="w-full shrink-0 sm:mr-3 sm:w-48"
                                labelClassName="text-[13px]"
                            >
                                <Select
                                    value={packageId || 'all'}
                                    onValueChange={(value) => updateFilter('package_id', value)}
                                >
                                    <SelectTrigger id="package-order-package">
                                        <SelectValue placeholder={t('menu.packages')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {filter_options.packages.map((option) => (
                                            <SelectItem key={option.id} value={String(option.id)}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                label={t('common.status')}
                                htmlFor="package-order-status"
                                className="w-full shrink-0 sm:mr-3 sm:w-40"
                                labelClassName="text-[13px]"
                            >
                                <Select
                                    value={status || 'all'}
                                    onValueChange={(value) => updateFilter('status', value)}
                                >
                                    <SelectTrigger id="package-order-status">
                                        <SelectValue placeholder={t('common.status')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {filter_options.statuses.map((value) => (
                                            <SelectItem key={value} value={value}>
                                                {t(`status.${value}`)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </>
                    }
                    pagination={orders}
                    emptyLabel={t('common.no_results')}
                    directActions
                    actions={(row) => (
                        <TableActionButton label={t('common.view')} icon={EyeIcon} onClick={() => setSelected(row)} />
                    )}
                />
            </PageContent>

            <FormDialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) setSelected(null);
                }}
                title={
                    selected
                        ? `${t('package_orders.order_details')} #${selected.id}`
                        : t('package_orders.order_details')
                }
                description={t('menu.package_orders_description')}
                icon={PackageIcon}
                size="xl"
            >
                {selected ? (
                    <div className="flex min-h-0 flex-1 flex-col">
                        <div className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">
                            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <DetailItem
                                    label={t('transactions.number')}
                                    value={
                                        selected.transaction_no ? (
                                            <span className="flex items-center gap-1.5">
                                                <Link
                                                    className="font-mono text-primary underline-offset-4 hover:underline"
                                                    href={transactionHref(selected.transaction_no)}
                                                >
                                                    {selected.transaction_no}
                                                </Link>
                                                <CopyValueButton
                                                    value={selected.transaction_no}
                                                    label={t('transactions.number')}
                                                />
                                            </span>
                                        ) : null
                                    }
                                />
                                <DetailItem
                                    label={t('common.status')}
                                    value={<StatusBadge status={selected.status} />}
                                />
                                <DetailItem
                                    label={t('transactions.customer')}
                                    value={
                                        selected.customer.id && selected.customer.name ? (
                                            <Link
                                                href={`/customers/${selected.customer.id}`}
                                                className="text-primary underline-offset-4 hover:underline"
                                            >
                                                {selected.customer.name}
                                            </Link>
                                        ) : (
                                            selected.customer.name
                                        )
                                    }
                                />
                                <DetailItem
                                    label={t('transactions.detail_fields.customer_phone')}
                                    value={
                                        selected.customer.phone ? (
                                            <span className="flex items-center gap-1.5">
                                                {selected.customer.phone}
                                                <CopyValueButton
                                                    value={selected.customer.phone}
                                                    label={t('transactions.detail_fields.customer_phone')}
                                                />
                                            </span>
                                        ) : null
                                    }
                                />
                                <DetailItem label={t('menu.packages')} value={selected.package} />
                                <DetailItem
                                    label={t('transactions.amount')}
                                    value={formatTopUpAmount(selected.amount)}
                                />
                                <DetailItem
                                    label={t('common.created_at')}
                                    value={formatDateTime(selected.created_at)}
                                />
                                {selected.customer_package ? (
                                    <>
                                        <DetailItem
                                            label={t('package_orders.service_details')}
                                            value={<StatusBadge status={selected.customer_package.status} />}
                                        />
                                        <DetailItem
                                            label={t('package_orders.starts_at')}
                                            value={formatDateTime(selected.customer_package.starts_at)}
                                        />
                                        <DetailItem
                                            label={t('package_orders.expires_at')}
                                            value={formatDateTime(selected.customer_package.expires_at)}
                                        />
                                    </>
                                ) : null}
                            </dl>
                            <div className="mt-5 grid w-full gap-4">
                                <JsonDetails title={t('package_orders.snapshot')} value={selected.snapshot} />
                                <JsonDetails
                                    title={t('package_orders.external_response')}
                                    value={selected.external_response}
                                />
                            </div>
                        </div>
                    </div>
                ) : null}
            </FormDialog>
        </>
    );
}

function transactionHref(transactionNo: string): string {
    const query = new URLSearchParams({
        search: transactionNo,
        open_transaction: transactionNo,
        return_to: `${window.location.pathname}${window.location.search}`,
    });

    return `/billing/transactions?${query.toString()}`;
}

function DetailItem({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="min-w-0 rounded-xl border border-border/60 bg-muted/20 p-3.5">
            <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
            <dd className="mt-1 break-words text-sm font-semibold text-foreground">
                <span className="flex items-center gap-1.5">{value ?? '—'}</span>
            </dd>
        </div>
    );
}
