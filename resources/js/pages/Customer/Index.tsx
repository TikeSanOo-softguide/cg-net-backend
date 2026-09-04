import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    CircleDotIcon,
    PackageIcon,
    SquarePenIcon,
    Trash2Icon,
    WalletIcon,
    WifiIcon,
} from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { CustomerFormDialog, type CustomerFormMember } from '@/components/customer/CustomerFormDialog';
import { MetaCell } from '@/components/customer/MetaCell';
import { PhoneDisplay } from '@/components/customer/PhoneDisplay';
import { StaffListAvatar } from '@/components/staff/StaffListAvatar';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { visitBulkDelete } from '@/lib/bulk-delete';

type CustomerRow = CustomerFormMember & {
    wallet_balance: string;
    broadband_connected: boolean;
    broadband_count: number;
    current_package: string | null;
    created_at: string | null;
};

type Filters = {
    search: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
};

type CustomersIndexProps = {
    customers: Paginated<CustomerRow>;
    filters: Filters;
};

function visitIndex(filters: Filters) {
    router.get('/customers', {
        search: filters.search || undefined,
        status: filters.status || undefined,
        sort: filters.sort,
        direction: filters.direction,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function formatMmk(value: string): string {
    return `${Number(value).toLocaleString()} MMK`;
}

export default function CustomersIndex({ customers, filters }: CustomersIndexProps) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const [formOpen, setFormOpen] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState<CustomerFormMember | null>(null);
    const debounce = useRef<number>(0);
    const canDelete = can('customers.delete');

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const onSearchChange = (value: string) => {
        setSearch(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => {
            visitIndex({ ...filters, search: value });
        }, 300);
    };

    const onSort = (column: string) => {
        const nextDirection = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
        visitIndex({ ...filters, sort: column, direction: nextDirection });
    };

    return (
        <>
            <Head title={t('menu.customers_list')} />
            <PageContent>
                <PageHeader />
                <DataTable
                    data={customers.data}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={onSearchChange}
                    searchPlaceholder={t('customers.search_placeholder')}
                    sort={filters.sort}
                    direction={filters.direction}
                    onSort={onSort}
                    href={(row) => `/customers/${row.id}`}
                    onCreate={can('customers.create') ? () => {
                        setEditingCustomer(null);
                        setFormOpen(true);
                    } : undefined}
                    createLabel={t('customers.create')}
                    pagination={customers}
                    onBulkDelete={canDelete ? (ids) => visitBulkDelete('/customers/bulk-destroy', ids.map(Number)) : undefined}
                    bulkDeleteTitle={t('customers.bulk_delete_title')}
                    actions={(row) => (
                        <>
                            {can('customers.update') ? (
                                <TableActionButton
                                    label={t('common.edit')}
                                    icon={SquarePenIcon}
                                    tone="edit"
                                    size="sm"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        setEditingCustomer(row);
                                        setFormOpen(true);
                                    }}
                                />
                            ) : null}
                            {canDelete ? (
                                <TableActionButton
                                    label={t('common.delete')}
                                    icon={Trash2Icon}
                                    tone="danger"
                                    size="sm"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        setPendingIds([row.id]);
                                    }}
                                />
                            ) : null}
                        </>
                    )}
                    filters={
                        <FormControl icon={CircleDotIcon} compact className="w-full shrink-0 sm:w-48">
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(value) => visitIndex({ ...filters, status: value === 'all' ? '' : value })}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder={t('customers.filter_status')} />
                                </SelectTrigger>
                                <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                    <SelectItem value="all">{t('customers.all_statuses')}</SelectItem>
                                    <SelectItem value="active">{t('status.active')}</SelectItem>
                                    <SelectItem value="suspended">{t('status.suspended')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormControl>
                    }
                    columns={[
                        {
                            id: 'name',
                            header: t('customers.name'),
                            className: 'font-medium',
                            mobile: 'title',
                            sortable: true,
                            cell: (row) => (
                                <span className="flex min-w-0 items-center gap-2">
                                    <StaffListAvatar username={row.name} />
                                    <span className="truncate text-[13px]">{row.name}</span>
                                </span>
                            ),
                        },
                        {
                            id: 'phone',
                            header: t('customers.phone'),
                            mobile: 'subtitle',
                            sortable: true,
                            cell: (row) => <PhoneDisplay phone={row.phone} />,
                        },
                        {
                            id: 'status',
                            header: t('common.status'),
                            mobile: 'badge',
                            sortable: true,
                            cell: (row) => <StatusBadge status={row.status} />,
                        },
                        {
                            id: 'wallet',
                            header: t('customers.wallet'),
                            className: 'tabular-nums',
                            mobile: 'meta',
                            cell: (row) => (
                                <MetaCell icon={WalletIcon} muted={Number(row.wallet_balance) === 0}>
                                    {formatMmk(row.wallet_balance)}
                                </MetaCell>
                            ),
                        },
                        {
                            id: 'broadband',
                            header: t('customers.broadband'),
                            cell: (row) => (
                                <MetaCell
                                    icon={WifiIcon}
                                    muted={! row.broadband_connected}
                                    className={row.broadband_connected ? 'text-success' : undefined}
                                >
                                    {row.broadband_connected
                                        ? `${t('customers.connected')}${row.broadband_count > 1 ? ` · ${row.broadband_count}` : ''}`
                                        : t('customers.not_connected')}
                                </MetaCell>
                            ),
                        },
                        {
                            id: 'current_package',
                            header: t('customers.current_package'),
                            cell: (row) => (
                                <MetaCell icon={PackageIcon} muted={! row.current_package}>
                                    {row.current_package ?? '—'}
                                </MetaCell>
                            ),
                        },
                        {
                            id: 'created_at',
                            header: t('customers.joined'),
                            className: 'text-muted-foreground',
                            sortable: true,
                            cell: (row) => (
                                <span className="font-mono text-[11px] text-muted-foreground">{row.created_at ?? '—'}</span>
                            ),
                        },
                    ]}
                />
            </PageContent>
            <CustomerFormDialog
                open={formOpen}
                onOpenChange={(open) => {
                    setFormOpen(open);

                    if (! open) {
                        setEditingCustomer(null);
                    }
                }}
                customer={editingCustomer}
            />
            <ConfirmDialog
                open={pendingIds.length === 1}
                onOpenChange={(open) => {
                    if (! open) {
                        setPendingIds([]);
                    }
                }}
                title={t('customers.delete_title')}
                description={t('customers.delete_description')}
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (pendingIds.length !== 1) {
                        return;
                    }

                    router.delete(`/customers/${pendingIds[0]}`, {
                        onFinish: () => setPendingIds([]),
                    });
                }}
            />
        </>
    );
}
