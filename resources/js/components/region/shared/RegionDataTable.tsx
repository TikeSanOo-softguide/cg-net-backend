import { useEffect, useRef, useState, type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { SquarePenIcon, Trash2Icon } from 'lucide-react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable, type DataTableColumn } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { TableActionButton } from '@/components/TableActionButton';
import { useTranslation } from '@/hooks/useTranslation';
import { useCan } from '@/hooks/useCan';
import { visitBulkDelete } from '@/lib/bulk-delete';

export type RegionFilters = {
    search: string;
};

type RegionDataTableProps<T extends { id: number }> = {
    indexHref: string;
    destroyBase: string;

    titleKey: string;
    createLabelKey: string;
    searchLabelKey: string;

    /**
     * Query parameter used only by this table.
     *
     * Example:
     * - state_search
     * - region_search
     * - area_search
     */
    searchParam: string;

    items: Paginated<T>;
    filters: RegionFilters;

    columns: DataTableColumn<T>[];

    onCreate?: () => void;
    onEdit?: (row: T) => void;

    formDialog?: ReactNode;
};

export function RegionDataTable<T extends { id: number }>({
    indexHref,
    destroyBase,
    titleKey,
    createLabelKey,
    searchLabelKey,
    searchParam,
    items,
    filters,
    columns,
    onCreate,
    onEdit,
    formDialog,
}: RegionDataTableProps<T>) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const can = useCan();

    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const [processing, setProcessing] = useState(false);

    const debounce = useRef<number>(0);

    /**
     * Keep local search value synchronized
     * with the server-side filter.
     */
    useEffect(() => {
        setSearch(filters.search ?? '');
    }, [filters.search]);

    /**
     * Clear debounce timer when component unmounts.
     */
    useEffect(() => {
        return () => {
            window.clearTimeout(debounce.current);
        };
    }, []);

    /**
     * Visit the same index page while sending
     * this table's search parameter.
     *
     * Example:
     *
     * State:
     * /regions?state_search=shan
     *
     * Region:
     * /regions?region_search=yangon
     *
     * Area:
     * /regions?area_search=...
     */
    const visit = (next: Partial<RegionFilters>) => {
        const params: Record<string, string | undefined> = {
            /**
             * IMPORTANT:
             * Do NOT use `search` here.
             *
             * Each table has its own query parameter.
             */
            state_search: undefined,
            region_search: undefined,
            area_search: undefined,
        };

        const nextSearch = (next.search ?? filters.search) || undefined;

        params[searchParam] = nextSearch;

        router.get(indexHref, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    /**
     * Search with debounce.
     */
    const onSearchChange = (value: string) => {
        setSearch(value);

        window.clearTimeout(debounce.current);

        debounce.current = window.setTimeout(() => {
            visit({
                search: value,
            });
        }, 300);
    };

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="min-h-0 flex-1">
                <DataTable
                    data={items.data}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={onSearchChange}
                    searchPlaceholder={t(searchLabelKey)}
                    title={t(titleKey)}
                    pagination={items}
                    onCreate={onCreate}
                    createLabel={t(createLabelKey)}
                    onBulkDelete={(ids) => visitBulkDelete(`${destroyBase}/bulk-destroy`, ids.map(Number))}
                    actions={(row) => (
                        <>
                            {can('regions.update') ? (
                                <TableActionButton
                                    label={t('common.edit')}
                                    icon={SquarePenIcon}
                                    tone="edit"
                                    onClick={onEdit ? () => onEdit(row) : undefined}
                                />
                            ) : null}

                            {can('regions.delete') ? (
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
            </div>

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
        </div>
    );
}
