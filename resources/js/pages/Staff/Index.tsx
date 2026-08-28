import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { CircleDotIcon, SquarePenIcon, Trash2Icon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { StaffDetailDialog, type StaffDetailMember } from '@/components/staff/StaffDetailDialog';
import { StaffFormDialog, type StaffFormMember } from '@/components/staff/StaffFormDialog';
import { StaffListAvatar } from '@/components/staff/StaffListAvatar';
import { StaffRoleChip } from '@/components/staff/StaffRoleChip';
import type { StaffRoleOption } from '@/components/staff/StaffForm';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { visitBulkDelete } from '@/lib/bulk-delete';

type StaffRow = StaffDetailMember;

type Filters = {
    search: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
};

type StaffIndexProps = {
    staff: Paginated<StaffRow>;
    roles: StaffRoleOption[];
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

export default function StaffIndex({ staff, roles = [], filters }: StaffIndexProps) {
    const { t } = useTranslation();
    const can = useCan();
    const { auth } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const [formOpen, setFormOpen] = useState(false);
    const [detailOpen, setDetailOpen] = useState(false);
    const [editingStaff, setEditingStaff] = useState<StaffFormMember | null>(null);
    const [viewingStaff, setViewingStaff] = useState<StaffDetailMember | null>(null);
    const debounce = useRef<number>(0);
    const canDelete = can('staff.delete');

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    return (
        <>
            <Head title={t('menu.staff_accounts')} />
            <PageContent>
                <PageHeader />
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
                    onView={(row) => {
                        setViewingStaff(row);
                        setDetailOpen(true);
                    }}
                    onCreate={can('staff.create') ? () => {
                        setEditingStaff(null);
                        setFormOpen(true);
                    } : undefined}
                    createLabel={t('staff.create')}
                    pagination={staff}
                    onBulkDelete={canDelete ? (ids) => visitBulkDelete('/staff/bulk-destroy', ids.map(Number)) : undefined}
                    bulkDeleteTitle={t('staff.bulk_delete_title')}
                    isRowSelectable={(row) => row.id !== auth.user?.id}
                    actions={(row) => (
                        <>
                            {can('staff.update') ? (
                                <TableActionButton
                                    label={t('common.edit')}
                                    icon={SquarePenIcon}
                                    tone="edit"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        setEditingStaff(row);
                                        setFormOpen(true);
                                    }}
                                />
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
                        <FormControl icon={CircleDotIcon} compact className="w-full shrink-0 sm:w-48">
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(value) => visitIndex({ ...filters, status: value === 'all' ? '' : value })}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder={t('common.status')} />
                                </SelectTrigger>
                                <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                    <SelectItem value="all">{t('common.all')}</SelectItem>
                                    <SelectItem value="active">{t('status.active')}</SelectItem>
                                    <SelectItem value="inactive">{t('status.inactive')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormControl>
                    }
                    columns={[
                        {
                            id: 'username',
                            header: t('staff.username'),
                            className: 'font-medium',
                            mobile: 'title',
                            sortable: true,
                            cell: (row) => (
                                <span className="flex min-w-0 items-center gap-2.5">
                                    <StaffListAvatar username={row.username} />
                                    <span className="truncate">{row.username}</span>
                                </span>
                            ),
                        },
                        {
                            id: 'roles',
                            header: t('staff.roles'),
                            mobile: 'meta',
                            cell: (row) => (
                                <span className="flex flex-wrap gap-1">
                                    {row.roles.length === 0 ? '—' : row.roles.map((role) => (
                                        <StaffRoleChip key={role.id} name={role.name} />
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
            </PageContent>
            <StaffDetailDialog
                open={detailOpen}
                onOpenChange={(open) => {
                    setDetailOpen(open);

                    if (! open) {
                        setViewingStaff(null);
                    }
                }}
                staff={viewingStaff}
                onEdit={(member) => {
                    setDetailOpen(false);
                    setViewingStaff(null);
                    setEditingStaff(member);
                    setFormOpen(true);
                }}
            />
            <StaffFormDialog
                open={formOpen}
                onOpenChange={(open) => {
                    setFormOpen(open);

                    if (! open) {
                        setEditingStaff(null);
                    }
                }}
                roles={roles}
                staff={editingStaff}
            />
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
