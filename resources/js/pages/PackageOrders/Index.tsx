import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { EyeIcon, PackageIcon } from 'lucide-react';

import { BackButton } from '@/components/BackButton';
import { DataTable, type DataTableColumn } from '@/components/DataTable';
import { FormDialog } from '@/components/FormDialog';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/useTranslation';
import { useReturnTo } from '@/hooks/useReturnTo';
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

export default function PackageOrdersIndex({ orders, search: initialSearch, open_order_id }: Props) {
    const { t } = useTranslation();
    const returnTo = useReturnTo('');
    const [search, setSearch] = useState(initialSearch);
    const [selected, setSelected] = useState<PackageOrder | null>(null);
    const debounce = useRef<number>(0);

    useEffect(() => setSearch(initialSearch), [initialSearch]);
    useEffect(() => {
        if (open_order_id === null) return;

        const order = orders.data.find((row) => row.id === open_order_id);
        if (order) setSelected(order);
    }, [open_order_id, orders.data]);
    useEffect(() => () => window.clearTimeout(debounce.current), []);

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
                    search={search}
                    onSearchChange={(value) => {
                        setSearch(value);
                        window.clearTimeout(debounce.current);
                        debounce.current = window.setTimeout(() => {
                            router.get(
                                '/billing/package-orders',
                                { search: value || undefined },
                                { preserveState: true, preserveScroll: true, replace: true },
                            );
                        }, 300);
                    }}
                    searchPlaceholder={t('package_orders.search_placeholder')}
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
                size="2xl"
            >
                {selected ? (
                    <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <DetailItem
                                label={t('transactions.number')}
                                value={
                                    selected.transaction_no ? (
                                        <Link
                                            className="font-mono text-primary underline-offset-4 hover:underline"
                                            href={transactionHref(selected.transaction_no)}
                                        >
                                            {selected.transaction_no}
                                        </Link>
                                    ) : null
                                }
                            />
                            <DetailItem label={t('common.status')} value={<StatusBadge status={selected.status} />} />
                            <DetailItem label={t('transactions.customer')} value={selected.customer.name} />
                            <DetailItem
                                label={t('transactions.detail_fields.customer_phone')}
                                value={selected.customer.phone}
                            />
                            <DetailItem label={t('menu.packages')} value={selected.package} />
                            <DetailItem label={t('transactions.amount')} value={formatTopUpAmount(selected.amount)} />
                            <DetailItem label={t('common.created_at')} value={formatDateTime(selected.created_at)} />
                            <DetailItem
                                label={t('package_orders.completed_at')}
                                value={formatDateTime(selected.completed_at)}
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
                        <div className="mt-5 grid gap-4 xl:grid-cols-2">
                            <JsonDetails title={t('package_orders.snapshot')} value={selected.snapshot} />
                            <JsonDetails
                                title={t('package_orders.external_response')}
                                value={selected.external_response}
                            />
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
        <div className="min-w-0">
            <dt className="text-[13px] text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 break-words text-[13px] font-medium">{value ?? '—'}</dd>
        </div>
    );
}
