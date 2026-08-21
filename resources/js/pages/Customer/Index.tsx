import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import { DataTable } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

type CustomerRow = {
    id: number;
    name: string;
    phone: string;
    nrc_number: string;
    status: string;
    accounts_count: number;
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

export default function CustomersIndex({ customers, filters }: CustomersIndexProps) {
    const { t } = useTranslation();
    const { flash } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const debounce = useRef<number>(0);

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
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader title={t('menu.customers_list')} description={t('customers.index_description')} />
                {flash.success ? (
                    <p className="rounded-[8px] bg-primary/10 px-3 py-2 text-sm text-foreground">{t(flash.success)}</p>
                ) : null}
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
                    pagination={customers}
                    filters={
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) => visitIndex({ ...filters, status: value === 'all' ? '' : value })}
                        >
                            <SelectTrigger className="h-8 w-full text-xs sm:w-40">
                                <SelectValue placeholder={t('customers.filter_status')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('customers.all_statuses')}</SelectItem>
                                <SelectItem value="active">{t('status.active')}</SelectItem>
                                <SelectItem value="suspended">{t('status.suspended')}</SelectItem>
                            </SelectContent>
                        </Select>
                    }
                    columns={[
                        {
                            id: 'name',
                            header: t('customers.name'),
                            className: 'font-medium',
                            mobile: 'title',
                            sortable: true,
                            cell: (row) => row.name,
                        },
                        {
                            id: 'phone',
                            header: t('customers.phone'),
                            mobile: 'subtitle',
                            sortable: true,
                            cell: (row) => row.phone,
                        },
                        {
                            id: 'nrc_number',
                            header: t('customers.nrc'),
                            className: 'font-mono text-[12px]',
                            mobile: 'meta',
                            sortable: true,
                            cell: (row) => row.nrc_number,
                        },
                        {
                            id: 'status',
                            header: t('common.status'),
                            mobile: 'badge',
                            sortable: true,
                            cell: (row) => <StatusBadge status={row.status} className="px-1.5 py-0 text-[11px]" />,
                        },
                        {
                            id: 'accounts_count',
                            header: t('customers.accounts'),
                            className: 'text-muted-foreground',
                            cell: (row) => row.accounts_count,
                        },
                        {
                            id: 'created_at',
                            header: t('customers.joined'),
                            className: 'text-muted-foreground',
                            sortable: true,
                            cell: (row) => row.created_at,
                        },
                    ]}
                />
            </div>
        </>
    );
}
