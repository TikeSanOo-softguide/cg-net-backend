import { useEffect, useRef, useState, type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { SearchIcon, SquarePenIcon, Trash2Icon, XIcon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable, type DataTableColumn } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { TableActionButton } from '@/components/TableActionButton';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { useCan } from '@/hooks/useCan';
import { toolbarInputClass } from '@/components/data-table/styles';
import { visitBulkDelete } from '@/lib/bulk-delete';

export type CmsFilters = {
    search: string;
    status: string;
    sort: string;
    types?: string[];
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
    searchPlaceholderKey?: string;
    onCreate?: () => void;
    onEdit?: (row: T) => void;
    formDialog?: ReactNode;
    extraFilters?: ReactNode;
    showSearch?: boolean;
    permissionPrefix?: string;
};

export function CmsIndexPage<T extends { id: number }>({
    indexHref,
    destroyBase,
    createLabelKey,
    items,
    filters,
    columns,
    statusFilter,
    searchPlaceholderKey = 'cms.search',
    onCreate,
    onEdit,
    formDialog,
    extraFilters,
    showSearch = true,
    permissionPrefix = 'cms',
}: CmsIndexPageProps<T>) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const [processing, setProcessing] = useState(false);
    const debounce = useRef<number>(0);
    const canDelete = can(`${permissionPrefix}.delete`);

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const visit = (next: Partial<CmsFilters>) => {
        router.get(
            indexHref,
            {
                search: showSearch ? (next.search ?? filters.search) || undefined : undefined,
                status: (next.status ?? filters.status) || undefined,
                types: next.types ?? filters.types ?? undefined,
                sort: next.sort ?? filters.sort,
                direction: next.direction ?? filters.direction,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
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

    const statusOptions: { value: string; label: string }[] =
        statusFilter === 'news'
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
        <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
            {showSearch ? (
                <FormField
                    label={t('common.search')}
                    htmlFor="cms-search"
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
                    className="mr-3 w-full shrink-0 sm:w-60"
                    labelClassName="text-[13px]"
                >
                    <Input
                        id="cms-search"
                        type="text"
                        inputMode="search"
                        autoComplete="off"
                        value={search}
                        onChange={(event) => onSearchChange(event.target.value)}
                        placeholder={t(searchPlaceholderKey)}
                        aria-label={t(searchPlaceholderKey)}
                        className={toolbarInputClass}
                    />
                </FormField>
            ) : null}
            {statusFilter ? (
                <FormField
                    label={t('common.status')}
                    htmlFor="cms-status"
                    className="mr-3 w-full shrink-0 sm:w-40"
                    labelClassName="text-[13px]"
                >
                    <Select
                        value={filters.status || 'all'}
                        onValueChange={(value) => visit({ status: value === 'all' ? '' : value })}
                    >
                        <SelectTrigger id="cms-status">
                            <SelectValue placeholder={t('common.status')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('common.all')}</SelectItem>
                            {statusOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
            ) : null}
            {extraFilters}
        </div>
    );

    return (
        <PageContent>
            <PageHeader />
            <DataTable
                data={items.data}
                getRowId={(row) => String(row.id)}
                showSearch={false}
                sort={filters.sort}
                direction={filters.direction}
                onSort={onSort}
                pagination={items}
                filters={filterControls}
                onCreate={can(`${permissionPrefix}.create`) ? onCreate : undefined}
                createLabel={t(createLabelKey)}
                onBulkDelete={
                    canDelete ? (ids) => visitBulkDelete(`${destroyBase}/bulk-destroy`, ids.map(Number)) : undefined
                }
                bulkDeleteTitle={t('cms.bulk_delete_title')}
                actions={(row) => (
                    <>
                        {can(`${permissionPrefix}.update`) ? (
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
