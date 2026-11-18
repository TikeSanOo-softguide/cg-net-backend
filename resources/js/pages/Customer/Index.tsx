import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { PackageIcon, SearchIcon, SquarePenIcon, WalletIcon, WifiIcon, XIcon } from 'lucide-react';

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
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toolbarInputClass } from '@/components/data-table/styles';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { formatTopUpNumber, TOP_UP_CARD_CURRENCY } from '@/lib/top-up-cards';
import { formatDate } from '@/lib/utils';

type CustomerRow = CustomerFormMember & {
    wallet_balance: string;
    broadband_connected: boolean;
    created_at: string | null;
};

type LocalizedText = {
    en: string | null;
    my: string | null;
    zh: string | null;
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
    router.get(
        '/customers',
        {
            search: filters.search || undefined,
            status: filters.status || undefined,
            sort: filters.sort,
            direction: filters.direction,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

export default function CustomersIndex({ customers, filters }: CustomersIndexProps) {
    const { t, locale } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [formOpen, setFormOpen] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState<CustomerFormMember | null>(null);
    const debounce = useRef<number>(0);

    const localizePackageName = (value: LocalizedText | null | undefined): string => {
        if (!value) {
            return '—';
        }

        return value[locale] ?? value.en ?? value.my ?? value.zh ?? '—';
    };

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
                    showSearch={false}
                    sort={filters.sort}
                    direction={filters.direction}
                    onSort={onSort}
                    href={(row) => `/customers/${row.id}`}
                    onCreate={
                        can('customers.create')
                            ? () => {
                                  setEditingCustomer(null);
                                  setFormOpen(true);
                              }
                            : undefined
                    }
                    createLabel={t('customers.create')}
                    pagination={customers}
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
                        </>
                    )}
                    filters={
                        <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <FormField
                                label={t('common.search')}
                                htmlFor="customer-search"
                                icon={SearchIcon}
                                rightSlot={
                                    search ? (
                                        <button
                                            type="button"
                                            aria-label={t('common.clear')}
                                            className="inline-flex size-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                                            onClick={() => onSearchChange('')}
                                        >
                                            <XIcon className="size-3.5" strokeWidth={2} />
                                        </button>
                                    ) : undefined
                                }
                                className="w-full shrink-0 sm:w-60 mr-3"
                                labelClassName="text-[13px]"
                            >
                                <Input
                                    id="customer-search"
                                    type="text"
                                    inputMode="search"
                                    autoComplete="off"
                                    value={search}
                                    onChange={(event) => onSearchChange(event.target.value)}
                                    placeholder={t('customers.search_placeholder')}
                                    aria-label={t('customers.search_placeholder')}
                                    className={toolbarInputClass}
                                />
                            </FormField>
                            <FormField
                                label={t('common.status')}
                                htmlFor="customer-status"
                                className="w-full shrink-0 sm:w-40 mr-3"
                                labelClassName="text-[13px]"
                            >
                                <Select
                                    value={filters.status || 'all'}
                                    onValueChange={(value) =>
                                        visitIndex({ ...filters, status: value === 'all' ? '' : value })
                                    }
                                >
                                    <SelectTrigger id="customer-status">
                                        <SelectValue placeholder={t('common.status')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        <SelectItem value="active">{t('status.active')}</SelectItem>
                                        <SelectItem value="suspended">{t('status.suspended')}</SelectItem>
                                        <SelectItem value="deactivated">{t('status.deactivated')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>
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
                                    <span className="truncate text-[13px] leading-[1.7]">{row.name}</span>
                                </span>
                            ),
                        },
                        {
                            id: 'phone',
                            header: t('customers.phone'),
                            mobile: 'subtitle',
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
                            sortable: true,
                            cell: (row) => (
                                <MetaCell icon={WalletIcon} muted={Number(row.wallet_balance) === 0}>
                                    <span className="font-heading text-[14px] font-semibold tabular-nums text-primary">
                                        {formatTopUpNumber(row.wallet_balance)}
                                    </span>
                                    <span className="ml-1">{TOP_UP_CARD_CURRENCY}</span>
                                </MetaCell>
                            ),
                        },
                        {
                            id: 'broadband',
                            header: t('customers.broadband'),
                            cell: (row) => (
                                <MetaCell
                                    icon={WifiIcon}
                                    muted={!row.broadband_connected}
                                    className={row.broadband_connected ? 'text-success' : undefined}
                                >
                                    {row.broadband_connected ? t('customers.connected') : t('customers.not_connected')}
                                </MetaCell>
                            ),
                        },
                        {
                            id: 'created_at',
                            header: t('customers.joined'),
                            sortable: true,
                            cell: (row) => (
                                <span className=" text-[12px]">
                                    {row.created_at ? formatDate(row.created_at) : '—'}
                                </span>
                            ),
                        },
                    ]}
                />
            </PageContent>
            <CustomerFormDialog
                open={formOpen}
                onOpenChange={(open) => {
                    setFormOpen(open);

                    if (!open) {
                        setEditingCustomer(null);
                    }
                }}
                customer={editingCustomer}
            />
        </>
    );
}
