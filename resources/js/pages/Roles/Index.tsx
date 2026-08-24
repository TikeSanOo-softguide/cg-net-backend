import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { PlusIcon, SquarePenIcon, Trash2Icon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { PageHeader } from '@/components/PageHeader';
import { TableActionButton } from '@/components/TableActionButton';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { visitBulkDelete } from '@/lib/bulk-delete';

type RoleRow = {
    id: number;
    name: string;
    is_locked: boolean;
    permissions_count: number;
    users_count: number;
};

type RolesIndexProps = {
    roles: RoleRow[];
    filters: { search: string };
};

export default function RolesIndex({ roles, filters }: RolesIndexProps) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const debounce = useRef<number>(0);
    const canDelete = can('roles.delete');

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    return (
        <>
            <Head title={t('menu.roles')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader
                    eyebrow={t('menu.staff_role_management')}
                    title={t('menu.roles')}
                    description={t('staff.roles_description')}
                    actions={
                        can('roles.create') ? (
                            <Button asChild>
                                <Link href="/roles/create">
                                    <PlusIcon />
                                    {t('staff.create_role')}
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <DataTable
                    data={roles}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={(value) => {
                        setSearch(value);
                        window.clearTimeout(debounce.current);
                        debounce.current = window.setTimeout(() => {
                            router.get('/roles', { search: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
                        }, 300);
                    }}
                    searchPlaceholder={t('staff.search_roles')}
                    onBulkDelete={canDelete ? (ids) => visitBulkDelete('/roles/bulk-destroy', ids.map(Number)) : undefined}
                    bulkDeleteTitle={t('staff.bulk_delete_roles_title')}
                    isRowSelectable={(row) => ! row.is_locked}
                    actions={(row) => (
                        <>
                            {can('roles.update') ? (
                                <TableActionButton label={t('common.edit')} icon={SquarePenIcon} tone="edit" href={`/roles/${row.id}/edit`} />
                            ) : null}
                            {canDelete && ! row.is_locked ? (
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
                    columns={[
                        {
                            id: 'name',
                            header: t('staff.role_name'),
                            className: 'font-medium',
                            mobile: 'title',
                            cell: (row) => row.name,
                        },
                        {
                            id: 'permissions_count',
                            header: t('staff.permissions'),
                            mobile: 'subtitle',
                            cell: (row) => row.permissions_count,
                        },
                        {
                            id: 'users_count',
                            header: t('staff.assigned_staff'),
                            mobile: 'meta',
                            cell: (row) => row.users_count,
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
                title={t('staff.delete_role_title')}
                description={t('staff.delete_role_description')}
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (pendingIds.length !== 1) {
                        return;
                    }

                    router.delete(`/roles/${pendingIds[0]}`, {
                        onFinish: () => setPendingIds([]),
                    });
                }}
            />
        </>
    );
}
