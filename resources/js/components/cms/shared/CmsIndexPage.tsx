import { useEffect, useRef, useState, type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { CircleDotIcon, SquarePenIcon, Trash2Icon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable, type DataTableColumn } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { TableActionButton } from '@/components/TableActionButton';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { useCan } from '@/hooks/useCan';
import { visitBulkDelete } from '@/lib/bulk-delete';

export type CmsFilters = {
    search: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
};

type CmsIndexPageProps<T extends { id: number }> = {
    indexHref: string;
    destroyBase: string;
    createLabelKey: string;
    items: Paginated<T>;
    filters: CmsFilters;
    columns: DataTableColumn<T>[];
    statusFilter?: 'active' | 'news';
    onCreate?: () => void;
    onEdit?: (row: T) => void;
    formDialog?: ReactNode;
};

export function CmsIndexPage<T extends { id: number }>({
    indexHref,
    destroyBase,
    createLabelKey,
    items,
    filters,
    columns,
    statusFilter,
    onCreate,
    onEdit,
    formDialog,
}: CmsIndexPageProps<T>) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const [processing, setProcessing] = useState(false);
    const debounce = useRef<number>(0);
    const canDelete = can('cms.delete');

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const visit = (next: Partial<CmsFilters>) => {
        router.get(indexHref, {
            search: (next.search ?? filters.search) || undefined,
            status: (next.status ?? filters.status) || undefined,
            sort: next.sort ?? filters.sort,
            direction: next.direction ?? filters.direction,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const onSearchChange = (value: string) => {
        setSearch(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => visit({ search: value }), 300);
    };

    const onSort = (column: string) => {
        const nextDirection = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
        visit({ sort: column, direction: nextDirection });
    };

    const statusOptions: { value: string; label: string }[] = statusFilter === 'news'
        ? [
            { value: 'draft', label: t('status.draft') },
            { value: 'published', label: t('status.published') },
            { value: 'archived', label: t('status.archived') },
        ]
        : [
            { value: 'active', label: t('status.active') },
            { value: 'inactive', label: t('status.inactive') },
        ];

    const filterControls: ReactNode = (
        <>
            {statusFilter ? (
                <FormControl icon={CircleDotIcon} className="w-full shrink-0 sm:w-48">
                    <Select value={filters.status || 'all'} onValueChange={(value) => visit({ status: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder={t('common.status')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('customers.all_statuses')}</SelectItem>
                            {statusOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormControl>
            ) : null}
        </>
    );

    return (
        <PageContent>
            <PageHeader />
            <DataTable
                data={items.data}
                getRowId={(row) => String(row.id)}
                search={search}
                onSearchChange={onSearchChange}
                searchPlaceholder={t('cms.search')}
                sort={filters.sort}
                direction={filters.direction}
                onSort={onSort}
                pagination={items}
                filters={filterControls}
                onCreate={can('cms.create') ? onCreate : undefined}
                createLabel={t(createLabelKey)}
                onBulkDelete={canDelete ? (ids) => visitBulkDelete(`${destroyBase}/bulk-destroy`, ids.map(Number)) : undefined}
                bulkDeleteTitle={t('cms.bulk_delete_title')}
                actions={(row) => (
                    <>
                        {can('cms.update') ? (
                            <TableActionButton
                                label={t('common.edit')}
                                icon={SquarePenIcon}
                                tone="edit"
                                onClick={onEdit ? () => onEdit(row) : undefined}
                            />
                        ) : null}
                        {canDelete ? (
                            <TableActionButton
                                label={t('common.delete')}
                                icon={Trash2Icon}
                                tone="danger"
                                onClick={() => setPendingIds([row.id])}
                            />
                        ) : null}
                    </>
                )}
                columns={columns}
            />
            {formDialog}
            <ConfirmDialog
                open={pendingIds.length === 1}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingIds([]);
                    }
                }}
                title={t('cms.delete_title')}
                description={t('cms.delete_description')}
                confirmLabel={t('common.delete')}
                destructive
                processing={processing}
                onConfirm={() => {
                    if (pendingIds.length !== 1) {
                        return;
                    }

                    router.delete(`${destroyBase}/${pendingIds[0]}`, {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => {
                            setProcessing(false);
                            setPendingIds([]);
                        },
                    });
                }}
            />
        </PageContent>
    );
}
