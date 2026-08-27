import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    ChevronsUpDownIcon,
    DownloadIcon,
    EyeIcon,
    HashIcon,
    PlusIcon,
    Trash2Icon,
    type LucideIcon,
} from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DEFAULT_COLUMN_ICONS } from '@/components/data-table/column-icons';
import { RadialBubbleActions } from '@/components/data-table/RadialBubbleActions';
import { csvEscape, EDGE_CELL, EDGE_PAD, headerCellClass } from '@/components/data-table/styles';
import { ColumnHeaderLabel, ToolbarIconButton } from '@/components/data-table/toolbar';
import { Pagination, type Paginated } from '@/components/Pagination';
import { SearchInput } from '@/components/SearchInput';
import { TableActionButton } from '@/components/TableActionButton';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TableCheckbox } from '@/components/ui/table-checkbox';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

export type DataTableColumn<T> = {
    id: string;
    header: string;
    cell: (row: T) => ReactNode;
    className?: string;
    mobile?: 'title' | 'subtitle' | 'meta' | 'badge' | false;
    searchValue?: (row: T) => string;
    sortable?: boolean;
    icon?: LucideIcon;
};
type DataTableProps<T> = {
    title?: string;
    data: T[];
    columns: DataTableColumn<T>[];
    getRowId: (row: T) => string;
    searchPlaceholder?: string;
    emptyLabel?: string;
    className?: string;
    search?: string;
    onSearchChange?: (value: string) => void;
    filters?: ReactNode;
    sort?: string;
    direction?: 'asc' | 'desc';
    onSort?: (column: string) => void;
    pagination?: Paginated<T>;
    paginationSummary?: string;
    href?: (row: T) => string | undefined;
    onView?: (row: T) => void;
    actions?: (row: T) => ReactNode;
    numbered?: boolean;
    selectable?: boolean;
    selectedIds?: string[];
    onSelectionChange?: (ids: string[]) => void;
    isRowSelectable?: (row: T) => boolean;
    onBulkDelete?: (ids: string[]) => void | Promise<unknown>;
    confirmBulkDelete?: boolean;
    bulkDeleteTitle?: string;
    bulkDeleteDescription?: string;
    bulkActions?: ReactNode;
    createHref?: string;
    createLabel?: string;
    onCreate?: () => void;
    onExport?: () => void;
};

export function DataTable<T>({
    title,
    data,
    columns,
    getRowId,
    searchPlaceholder,
    emptyLabel,
    className,
    search,
    onSearchChange,
    filters,
    sort,
    direction,
    onSort,
    pagination,
    paginationSummary,
    href,
    onView,
    actions,
    numbered = true,
    selectable,
    selectedIds: selectedIdsProp,
    onSelectionChange,
    isRowSelectable,
    onBulkDelete,
    confirmBulkDelete,
    bulkDeleteTitle,
    bulkDeleteDescription,
    bulkActions,
    createHref,
    createLabel,
    onCreate,
    onExport,
}: DataTableProps<T>) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [internalSelectedIds, setInternalSelectedIds] = useState<string[]>([]);
    const noResults = emptyLabel ?? t('common.no_results');
    const searchValue = onSearchChange ? (search ?? '') : query;
    const serverDriven = Boolean(onSearchChange);
    const canSelect = selectable ?? Boolean(onBulkDelete);
    const selectedIds = onSelectionChange ? (selectedIdsProp ?? []) : internalSelectedIds;
    const setSelectedIds = onSelectionChange ?? setInternalSelectedIds;
    const shouldConfirmBulkDelete = confirmBulkDelete ?? Boolean(onBulkDelete);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const previousPageRef = useRef<number | undefined>(undefined);
    const previousPageIdsRef = useRef<string[]>([]);

    useEffect(() => {
        const currentIds = data.map(getRowId);

        if (pagination) {
            const pageChanged = previousPageRef.current !== undefined
                && previousPageRef.current !== pagination.current_page;

            if (! pageChanged && previousPageIdsRef.current.length > 0) {
                const vanished = new Set(
                    previousPageIdsRef.current.filter((id) => ! currentIds.includes(id)),
                );

                if (vanished.size > 0) {
                    const next = selectedIds.filter((id) => ! vanished.has(id));

                    if (next.length !== selectedIds.length) {
                        setSelectedIds(next);
                    }
                }
            }

            previousPageRef.current = pagination.current_page;
            previousPageIdsRef.current = currentIds;

            return;
        }

        const present = new Set(currentIds);
        const next = selectedIds.filter((id) => present.has(id));

        if (next.length !== selectedIds.length) {
            setSelectedIds(next);
        }
    }, [data, getRowId, pagination, selectedIds, setSelectedIds]);

    const rows = useMemo(() => {
        if (serverDriven) {
            return data;
        }

        const term = query.trim().toLowerCase();

        if (! term) {
            return data;
        }

        return data.filter((row) =>
            columns.some((column) => (column.searchValue?.(row) ?? '').toLowerCase().includes(term)),
        );
    }, [columns, data, query, serverDriven]);

    const mobileTitle = columns.find((column) => column.mobile === 'title');
    const mobileSubtitle = columns.find((column) => column.mobile === 'subtitle');
    const mobileMeta = columns.find((column) => column.mobile === 'meta');
    const mobileBadge = columns.find((column) => column.mobile === 'badge');
    const indexStart = pagination?.from ?? 1;
    const selectableIds = rows.filter((row) => isRowSelectable?.(row) ?? true).map((row) => getRowId(row));
    const selectedOnPage = selectableIds.filter((id) => selectedIds.includes(id));
    const allSelected = selectableIds.length > 0 && selectedOnPage.length === selectableIds.length;
    const someSelected = selectedOnPage.length > 0 && ! allSelected;
    const showActions = Boolean(actions) || Boolean(href) || Boolean(onView);
    const columnCount = columns.length + Number(canSelect) + Number(numbered) + Number(showActions);
    const hasSelection = selectedIds.length > 0;
    const showDelete = hasSelection && Boolean(bulkActions || onBulkDelete);

    const renderActions = (row: T) => {
        const to = href?.(row);
        const canView = Boolean(onView) || Boolean(to);

        if (! canView && ! actions) {
            return null;
        }

        return (
            <RadialBubbleActions>
                {canView ? (
                    <TableActionButton
                        label={t('common.view')}
                        icon={EyeIcon}
                        href={onView ? undefined : to}
                        onClick={onView ? (event) => {
                            event.stopPropagation();
                            onView(row);
                        } : undefined}
                    />
                ) : null}
                {actions?.(row)}
            </RadialBubbleActions>
        );
    };

    const toggleAll = () => {
        if (allSelected) {
            setSelectedIds(selectedIds.filter((id) => ! selectableIds.includes(id)));

            return;
        }

        setSelectedIds([...new Set([...selectedIds, ...selectableIds])]);
    };

    const toggleRow = (id: string, enabled: boolean) => {
        if (! enabled) {
            return;
        }

        if (selectedIds.includes(id)) {
            setSelectedIds(selectedIds.filter((item) => item !== id));

            return;
        }

        setSelectedIds([...selectedIds, id]);
    };

    const requestBulkDelete = () => {
        if (! onBulkDelete || selectedIds.length === 0 || processing) {
            return;
        }

        if (shouldConfirmBulkDelete) {
            setConfirmOpen(true);

            return;
        }

        void Promise.resolve(onBulkDelete(selectedIds))
            .then(() => setSelectedIds([]))
            .catch(() => {
                // Keep the current selection when deletion fails.
            });
    };

    const runBulkDelete = async () => {
        if (! onBulkDelete || selectedIds.length === 0 || processing) {
            return;
        }

        setProcessing(true);

        try {
            await onBulkDelete(selectedIds);
            setSelectedIds([]);
            setConfirmOpen(false);
        } catch {
            // Keep selection and the dialog open so the user can retry.
        } finally {
            setProcessing(false);
        }
    };

    const exportRows = () => {
        if (onExport) {
            onExport();

            return;
        }

        const headers = [
            ...(numbered ? [t('common.no')] : []),
            ...columns.map((column) => column.header),
        ];
        const lines = rows.map((row, index) => [
            ...(numbered ? [String(indexStart + index)] : []),
            ...columns.map((column) => column.searchValue?.(row) ?? ''),
        ].map(csvEscape).join(','));
        const csv = [headers.map(csvEscape).join(','), ...lines].join('\n');
        const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'export.csv';
        link.click();
        URL.revokeObjectURL(url);
    };

    return (
        <TooltipProvider>
            <Card className={cn('flex h-full min-h-0 flex-col gap-0 overflow-hidden border-0 py-0 shadow-[0_4px_16px_rgb(23_50_54/0.06)] dark:shadow-[0_4px_16px_rgb(0_0_0/0.22)]', className)}>
                <CardHeader className={cn('flex flex-col gap-2.5 py-3', EDGE_PAD)}>
                    {title ? (
                        <CardTitle className="text-[13px] font-semibold tracking-tight sm:text-sm">{title}</CardTitle>
                    ) : null}
                    <div className="flex w-full min-w-0 flex-col gap-2 sm:flex-row sm:items-center">
                        <div className="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center [&_[data-slot=input]]:h-8 [&_[data-slot=input]]:rounded-[4px] [&_[data-slot=input]]:bg-muted/50 [&_[data-slot=input]]:py-0 [&_[data-slot=input]]:text-[11px] [&_[data-slot=input]]:placeholder:text-[11px] [&_[data-slot=select-trigger]]:h-8 [&_[data-slot=select-trigger]]:rounded-[4px] [&_[data-slot=select-trigger]]:bg-muted/50 [&_[data-slot=select-trigger]]:py-0 [&_[data-slot=select-trigger]]:text-[11px] [&_[data-slot=select-trigger]]:data-[placeholder]:text-[11px] [&_.h-10]:h-8 [&_.rounded-\[6px\]]:rounded-[4px]">
                            {filters}
                            <SearchInput
                                value={searchValue}
                                onChange={onSearchChange ?? setQuery}
                                placeholder={searchPlaceholder ?? t('common.search')}
                                size="sm"
                                className="w-full sm:max-w-64"
                            />
                        </div>
                        <div className="flex shrink-0 items-center justify-end gap-2">
                            {showDelete ? (
                                <>
                                    <span className="inline-flex h-8 items-center rounded-[6px] bg-primary/12 px-2.5 text-[10px] font-semibold tabular-nums text-primary">
                                        {t('common.selected_count').replace(':count', String(selectedIds.length))}
                                    </span>
                                    {bulkActions ?? (
                                        <ToolbarIconButton
                                            label={t('common.delete')}
                                            icon={Trash2Icon}
                                            tone="danger"
                                            prominent
                                            disabled={processing}
                                            onClick={requestBulkDelete}
                                        />
                                    )}
                                    <span className="mx-0.5 hidden h-6 w-px bg-border sm:block" aria-hidden />
                                </>
                            ) : null}
                            <ToolbarIconButton
                                label={t('common.export')}
                                icon={DownloadIcon}
                                prominent
                                onClick={exportRows}
                            />
                            {createHref || onCreate ? (
                                <ToolbarIconButton
                                    label={createLabel ?? t('common.create')}
                                    icon={PlusIcon}
                                    prominent
                                    href={onCreate ? undefined : createHref}
                                    onClick={onCreate}
                                />
                            ) : null}
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="flex min-h-0 flex-1 flex-col px-0 pb-0">
                    <div className={cn(EDGE_PAD, 'min-h-0 flex-1 pb-3')}>
                        <div className="flex h-full min-h-0 flex-col overflow-hidden rounded-[4px] border-0 bg-card shadow-[0_2px_8px_rgb(23_50_54/0.08)] dark:shadow-[0_2px_8px_rgb(0_0_0/0.28)]">
                            <div className="hidden min-h-0 flex-1 overflow-x-auto sm:block">
                                <Table>
                            <TableHeader className="bg-muted">
                                <TableRow className="hover:bg-transparent">
                                    {canSelect ? (
                                        <TableHead className={cn(headerCellClass, EDGE_CELL, 'w-10 pr-0')}>
                                            <TableCheckbox
                                                checked={allSelected}
                                                indeterminate={someSelected}
                                                disabled={selectableIds.length === 0}
                                                label={t('permissions.select_all')}
                                                onChange={toggleAll}
                                            />
                                        </TableHead>
                                    ) : null}
                                    {numbered ? (
                                        <TableHead className={cn(headerCellClass, EDGE_CELL, 'w-12', canSelect && 'pl-3')}>
                                            <ColumnHeaderLabel icon={HashIcon} label={t('common.no')} />
                                        </TableHead>
                                    ) : null}
                                    {columns.map((column) => {
                                        const HeaderIcon = column.icon ?? DEFAULT_COLUMN_ICONS[column.id];
                                        const label = <ColumnHeaderLabel icon={HeaderIcon} label={column.header} />;

                                        return (
                                            <TableHead key={column.id} className={cn(headerCellClass, EDGE_CELL)}>
                                                {column.sortable && onSort ? (
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center gap-1.5 rounded-md py-0.5"
                                                        onClick={() => onSort(column.id)}
                                                    >
                                                        {label}
                                                        {sort === column.id ? (
                                                            direction === 'asc' ? (
                                                                <ArrowUpIcon className="size-3.5 text-muted-foreground group-hover/head:text-primary" />
                                                            ) : (
                                                                <ArrowDownIcon className="size-3.5 text-muted-foreground group-hover/head:text-primary" />
                                                            )
                                                        ) : (
                                                            <ChevronsUpDownIcon className="size-3.5 text-muted-foreground opacity-40 group-hover/head:text-primary group-hover/head:opacity-100" />
                                                        )}
                                                    </button>
                                                ) : (
                                                    label
                                                )}
                                            </TableHead>
                                        );
                                    })}
                                    {showActions ? (
                                        <TableHead className={cn(headerCellClass, EDGE_CELL, 'w-px text-center')}>
                                            <ColumnHeaderLabel label={t('common.actions')} className="justify-center" />
                                        </TableHead>
                                    ) : null}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.length === 0 ? (
                                    <TableRow className="hover:bg-transparent">
                                        <TableCell colSpan={columnCount} className={cn(EDGE_CELL, 'h-20 text-center')}>
                                            <p className="text-sm font-medium text-foreground">{noResults}</p>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    rows.map((row, index) => {
                                        const id = getRowId(row);
                                        const enabled = isRowSelectable?.(row) ?? true;
                                        const selected = selectedIds.includes(id);

                                        return (
                                            <TableRow
                                                key={id}
                                                data-state={selected ? 'selected' : undefined}
                                                className="bg-card"
                                            >
                                                {canSelect ? (
                                                    <TableCell className={cn(EDGE_CELL, 'w-10 pr-0')}>
                                                        <TableCheckbox
                                                            checked={selected}
                                                            disabled={! enabled}
                                                            label={t('common.select_row')}
                                                            onChange={() => toggleRow(id, enabled)}
                                                        />
                                                    </TableCell>
                                                ) : null}
                                                {numbered ? (
                                                    <TableCell className={cn(EDGE_CELL, 'w-12 tabular-nums text-muted-foreground', canSelect && 'pl-3')}>
                                                        {indexStart + index}
                                                    </TableCell>
                                                ) : null}
                                                {columns.map((column) => (
                                                    <TableCell key={column.id} className={cn(EDGE_CELL, column.className)}>
                                                        {column.cell(row)}
                                                    </TableCell>
                                                ))}
                                                {showActions ? (
                                                    <TableCell className={cn(EDGE_CELL, 'w-px text-center align-middle')}>
                                                        <div className="flex h-full items-center justify-center">{renderActions(row)}</div>
                                                    </TableCell>
                                                ) : null}
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <ul className="flex flex-col gap-2 p-2.5 sm:hidden">
                        {rows.length === 0 ? (
                            <li className="rounded-[6px] bg-muted/30 px-3 py-6 text-center text-sm text-muted-foreground">
                                {noResults}
                            </li>
                        ) : (
                            rows.map((row, index) => {
                                const id = getRowId(row);
                                const enabled = isRowSelectable?.(row) ?? true;
                                const selected = selectedIds.includes(id);

                                return (
                                    <li
                                        key={id}
                                        className={cn(
                                            'rounded-[6px] border border-border/70 bg-card px-3 py-2.5 shadow-[0_1px_2px_rgb(23_50_54/0.04)]',
                                            selected && 'border-primary/40 bg-primary/6',
                                        )}
                                    >
                                        <div className="mb-1.5 flex items-center gap-2">
                                            {canSelect ? (
                                                <TableCheckbox
                                                    checked={selected}
                                                    disabled={! enabled}
                                                    label={t('common.select_row')}
                                                    onChange={() => toggleRow(id, enabled)}
                                                />
                                            ) : null}
                                            {numbered ? (
                                                <span className="text-[11px] font-medium tabular-nums text-muted-foreground">
                                                    {t('common.no')} {indexStart + index}
                                                </span>
                                            ) : null}
                                        </div>
                                        <div className="flex items-start justify-between gap-2.5">
                                            <div className="min-w-0">
                                                {mobileTitle ? (
                                                    <p className="truncate text-[13px] font-semibold text-foreground">{mobileTitle.cell(row)}</p>
                                                ) : null}
                                                {mobileSubtitle ? (
                                                    <p className="mt-0.5 truncate text-xs text-muted-foreground">{mobileSubtitle.cell(row)}</p>
                                                ) : null}
                                            </div>
                                            {mobileBadge ? mobileBadge.cell(row) : null}
                                        </div>
                                        {mobileMeta ? (
                                            <p className="mt-1.5 font-mono text-[10px] text-muted-foreground">{mobileMeta.cell(row)}</p>
                                        ) : null}
                                        {showActions ? (
                                            <div className="mt-2 flex items-center justify-center border-t border-border/60 pt-2">
                                                {renderActions(row)}
                                            </div>
                                        ) : null}
                                    </li>
                                );
                            })
                        )}
                    </ul>
                        </div>
                    </div>
                    {pagination ? (
                        <Pagination
                            meta={pagination}
                            summary={
                                paginationSummary ??
                                t('common.showing')
                                    .replace(':from', String(pagination.from ?? 0))
                                    .replace(':to', String(pagination.to ?? 0))
                                    .replace(':total', String(pagination.total))
                            }
                        />
                    ) : null}
                </CardContent>
            </Card>
            {shouldConfirmBulkDelete ? (
                <ConfirmDialog
                    open={confirmOpen}
                    onOpenChange={(open) => {
                        if (! open && ! processing) {
                            setConfirmOpen(false);
                        }
                    }}
                    title={bulkDeleteTitle ?? t('common.bulk_delete_title')}
                    description={(bulkDeleteDescription ?? t('common.bulk_delete_description')).replace(':count', String(selectedIds.length))}
                    confirmLabel={t('common.delete')}
                    destructive
                    processing={processing}
                    onConfirm={() => void runBulkDelete()}
                />
            ) : null}
        </TooltipProvider>
    );
}
