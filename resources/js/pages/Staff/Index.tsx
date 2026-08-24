import { useEffect, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CircleDotIcon, PlusIcon, SquarePenIcon, Trash2Icon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { Button } from '@/components/ui/button';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { visitBulkDelete } from '@/lib/bulk-delete';

type StaffRow = {
    id: number;
    name: string;
    email: string;
    status: string;
    roles: { id: number; name: string }[];
    created_at: string | null;
};

type Filters = {
    search: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
};

type StaffIndexProps = {
    staff: Paginated<StaffRow>;
    filters: Filters;
};

function visitIndex(filters: Filters) {
    router.get('/staff', {
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

export default function StaffIndex({ staff, filters }: StaffIndexProps) {
    const { t } = useTranslation();
    const can = useCan();
    const { auth } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const debounce = useRef<number>(0);
    const canDelete = can('staff.delete');

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    return (
        <>
            <Head title={t('menu.staff_accounts')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader
                    eyebrow={t('menu.staff_role_management')}
                    title={t('menu.staff_accounts')}
                    description={t('staff.index_description')}
                    actions={
                        can('staff.create') ? (
                            <Button asChild>
                                <Link href="/staff/create">
                                    <PlusIcon />
                                    {t('staff.create')}
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    data={staff.data}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={(value) => {
                        setSearch(value);
                        window.clearTimeout(debounce.current);
                        debounce.current = window.setTimeout(() => visitIndex({ ...filters, search: value }), 300);
                    }}
                    searchPlaceholder={t('staff.search_placeholder')}
                    sort={filters.sort}
                    direction={filters.direction}
                    onSort={(column) => {
                        const nextDirection = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
                        visitIndex({ ...filters, sort: column, direction: nextDirection });
                    }}
                    href={(row) => `/staff/${row.id}`}
                    pagination={staff}
                    onBulkDelete={canDelete ? (ids) => visitBulkDelete('/staff/bulk-destroy', ids.map(Number)) : undefined}
                    bulkDeleteTitle={t('staff.bulk_delete_title')}
                    isRowSelectable={(row) => row.id !== auth.user?.id}
                    actions={(row) => (
                        <>
                            {can('staff.update') ? (
                                <TableActionButton label={t('common.edit')} icon={SquarePenIcon} tone="edit" href={`/staff/${row.id}/edit`} />
                            ) : null}
                            {canDelete && row.id !== auth.user?.id ? (
                                <TableActionButton
                                    label={t('common.delete')}
                                    icon={Trash2Icon}
                                    tone="danger"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        setPendingIds([row.id]);
                                    }}
                                />
                            ) : null}
                        </>
                    )}
                    filters={
                        <FormControl icon={CircleDotIcon} className="w-full shrink-0 sm:w-48">
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(value) => visitIndex({ ...filters, status: value === 'all' ? '' : value })}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder={t('common.status')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('common.all')}</SelectItem>
                                    <SelectItem value="active">{t('status.active')}</SelectItem>
                                    <SelectItem value="inactive">{t('status.inactive')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormControl>
                    }
                    columns={[
                        {
                            id: 'name',
                            header: t('staff.name'),
                            className: 'font-medium',
                            mobile: 'title',
                            sortable: true,
                            cell: (row) => row.name,
                        },
                        {
                            id: 'email',
                            header: t('staff.email'),
                            mobile: 'subtitle',
                            sortable: true,
                            cell: (row) => row.email,
                        },
                        {
                            id: 'roles',
                            header: t('staff.roles'),
                            mobile: 'meta',
                            cell: (row) => (
                                <span className="flex flex-wrap gap-1">
                                    {row.roles.length === 0 ? '—' : row.roles.map((role) => (
                                        <span key={role.id} className="rounded-[6px] bg-primary/12 px-1.5 py-0.5 text-[11px] font-medium text-primary">
                                            {role.name}
                                        </span>
                                    ))}
                                </span>
                            ),
                        },
                        {
                            id: 'status',
                            header: t('common.status'),
                            mobile: 'badge',
                            sortable: true,
                            cell: (row) => <StatusBadge status={row.status} />,
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
            <ConfirmDialog
                open={pendingIds.length === 1}
                onOpenChange={(open) => {
                    if (! open) {
                        setPendingIds([]);
                    }
                }}
                title={t('staff.delete_title')}
                description={t('staff.delete_description')}
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (pendingIds.length !== 1) {
                        return;
                    }

                    router.delete(`/staff/${pendingIds[0]}`, {
                        onFinish: () => setPendingIds([]),
                    });
                }}
            />
        </>
    );
}
