import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    BanknoteIcon,
    CalendarIcon,
    ChevronsUpDownIcon,
    CircleDotIcon,
    DownloadIcon,
    EyeIcon,
    FolderIcon,
    HashIcon,
    HeadingIcon,
    IdCardIcon,
    LanguagesIcon,
    LinkIcon,
    ListOrderedIcon,
    MailIcon,
    PackageIcon,
    PhoneIcon,
    PlusIcon,
    Settings2Icon,
    ShieldIcon,
    ShapesIcon,
    Trash2Icon,
    TypeIcon,
    UserRoundIcon,
    UsersIcon,
    WifiIcon,
    type LucideIcon,
} from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { Pagination, type Paginated } from '@/components/Pagination';
import { SearchInput } from '@/components/SearchInput';
import { TableActionButton } from '@/components/TableActionButton';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { TableCheckbox } from '@/components/ui/table-checkbox';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
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

const DEFAULT_COLUMN_ICONS: Record<string, LucideIcon> = {
    name: UserRoundIcon,
    customer: UserRoundIcon,
    title: TypeIcon,
    label: HeadingIcon,
    email: MailIcon,
    phone: PhoneIcon,
    nrc_number: IdCardIcon,
    contact_point: PhoneIcon,
    status: CircleDotIcon,
    is_active: CircleDotIcon,
    created_at: CalendarIcon,
    date: CalendarIcon,
    start_date: CalendarIcon,
    end_date: CalendarIcon,
    dates: CalendarIcon,
    roles: ShieldIcon,
    permissions_count: ShieldIcon,
    users_count: UsersIcon,
    accounts_count: WifiIcon,
    account_number: WifiIcon,
    package: PackageIcon,
    package_name: PackageIcon,
    type: ShapesIcon,
    amount: BanknoteIcon,
    lang: LanguagesIcon,
    slug: LinkIcon,
    category_name: FolderIcon,
    sort_order: ListOrderedIcon,
};

const EDGE_PAD = 'px-5 sm:px-[45px]';
const EDGE_CELL = 'first:pl-5 last:pr-5 sm:first:pl-[45px] sm:last:pr-[45px]';

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
    const showActions = Boolean(actions) || Boolean(href);
    const columnCount = columns.length + Number(canSelect) + Number(numbered) + Number(showActions);
    const hasSelection = selectedIds.length > 0;
    const showDelete = hasSelection && Boolean(bulkActions || onBulkDelete);

    const renderActions = (row: T) => {
        const to = href?.(row);

        if (! to && ! actions) {
            return null;
        }

        return (
            <>
                {to ? (
                    <TableActionButton label={t('common.view')} icon={EyeIcon} href={to} />
                ) : null}
                {actions?.(row)}
            </>
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
            <Card className={cn('gap-0 overflow-hidden bg-[#FFFFFF] py-0 dark:bg-card', className)}>
                <CardHeader className={cn('flex flex-col gap-3 border-b border-border/70 bg-[#FFFFFF] py-4 dark:bg-card', EDGE_PAD)}>
                    {title ? (
                        <CardTitle className="text-[15px] font-semibold tracking-tight sm:text-base">{title}</CardTitle>
                    ) : null}
                    <div className="flex w-full min-w-0 flex-col gap-2 sm:flex-row sm:items-center">
                        <div className="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                            {filters}
                            <SearchInput
                                value={searchValue}
                                onChange={onSearchChange ?? setQuery}
                                placeholder={searchPlaceholder ?? t('common.search')}
                                className="w-full sm:w-72"
                            />
                        </div>
                        <div className="flex shrink-0 items-center justify-end gap-2">
                            {showDelete ? (
                                <>
                                    <span className="rounded-[6px] bg-primary/12 px-2.5 py-1.5 text-xs font-semibold tabular-nums text-primary">
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
                <CardContent className="px-0 pb-0">
                    <div className="hidden sm:block">
                        <Table>
                            <TableHeader className="bg-[#FFFFFF] dark:bg-card">
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
                                                        className="inline-flex items-center gap-1.5 rounded-md py-0.5 transition-colors"
                                                        onClick={() => onSort(column.id)}
                                                    >
                                                        {label}
                                                        {sort === column.id ? (
                                                            direction === 'asc' ? (
                                                                <ArrowUpIcon className="size-3.5 text-muted-foreground transition-colors group-hover/head:text-primary" />
                                                            ) : (
                                                                <ArrowDownIcon className="size-3.5 text-muted-foreground transition-colors group-hover/head:text-primary" />
                                                            )
                                                        ) : (
                                                            <ChevronsUpDownIcon className="size-3.5 text-muted-foreground opacity-40 transition-colors group-hover/head:text-primary group-hover/head:opacity-100" />
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
                                            <ColumnHeaderLabel icon={Settings2Icon} label={t('common.actions')} className="justify-center" />
                                        </TableHead>
                                    ) : null}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.length === 0 ? (
                                    <TableRow className="hover:bg-transparent">
                                        <TableCell colSpan={columnCount} className={cn(EDGE_CELL, 'h-28 text-center')}>
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
                                                className="bg-[#FFFFFF] dark:bg-card"
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
                                                        <div className="flex h-full items-center justify-center gap-1.5">{renderActions(row)}</div>
                                                    </TableCell>
                                                ) : null}
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <ul className={cn('flex flex-col gap-2.5 bg-[#FFFFFF] py-4 sm:hidden dark:bg-card', EDGE_PAD)}>
                        {rows.length === 0 ? (
                            <li className="rounded-[6px] border border-border/80 bg-muted/30 px-4 py-8 text-center text-sm text-muted-foreground">
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
                                            'rounded-[6px] border border-border/80 bg-card px-4 py-3.5 shadow-[0_1px_2px_rgb(23_50_54/0.04)]',
                                            selected && 'border-primary/40 bg-primary/6',
                                        )}
                                    >
                                        <div className="mb-2 flex items-center gap-2.5">
                                            {canSelect ? (
                                                <TableCheckbox
                                                    checked={selected}
                                                    disabled={! enabled}
                                                    label={t('common.select_row')}
                                                    onChange={() => toggleRow(id, enabled)}
                                                />
                                            ) : null}
                                            {numbered ? (
                                                <span className="text-xs font-medium tabular-nums text-muted-foreground">
                                                    {t('common.no')} {indexStart + index}
                                                </span>
                                            ) : null}
                                        </div>
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                {mobileTitle ? (
                                                    <p className="truncate text-sm font-semibold text-foreground">{mobileTitle.cell(row)}</p>
                                                ) : null}
                                                {mobileSubtitle ? (
                                                    <p className="mt-0.5 truncate text-[13px] text-muted-foreground">{mobileSubtitle.cell(row)}</p>
                                                ) : null}
                                            </div>
                                            {mobileBadge ? mobileBadge.cell(row) : null}
                                        </div>
                                        {mobileMeta ? (
                                            <p className="mt-2 font-mono text-[11px] text-muted-foreground">{mobileMeta.cell(row)}</p>
                                        ) : null}
                                        {showActions ? (
                                            <div className="mt-3 flex items-center justify-center gap-1.5 border-t border-border/60 pt-3">
                                                {renderActions(row)}
                                            </div>
                                        ) : null}
                                    </li>
                                );
                            })
                        )}
                    </ul>
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

function ToolbarIconButton({
    label,
    icon: Icon,
    tone = 'primary',
    disabled = false,
    prominent = false,
    href,
    onClick,
}: {
    label: string;
    icon: LucideIcon;
    tone?: 'primary' | 'danger';
    disabled?: boolean;
    prominent?: boolean;
    href?: string;
    onClick?: () => void;
}) {
    const className = cn(
        'inline-flex shrink-0 items-center justify-center rounded-[6px] transition-all duration-200',
        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:outline-none',
        'disabled:pointer-events-none disabled:opacity-50',
        prominent ? 'size-8' : 'size-10',
        tone === 'danger'
            ? 'bg-danger text-[#FFFFFF] hover:bg-danger/12 hover:text-danger'
            : 'bg-primary text-[#FFFFFF] hover:bg-primary/12 hover:text-primary',
    );
    const icon = <Icon className={prominent ? 'size-[18px]' : 'size-4'} strokeWidth={prominent ? 2.5 : 1.85} />;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                {href ? (
                    <Link href={href} aria-label={label} className={className}>
                        {icon}
                    </Link>
                ) : (
                    <button type="button" aria-label={label} disabled={disabled} className={className} onClick={onClick}>
                        {icon}
                    </button>
                )}
            </TooltipTrigger>
            <TooltipContent side="top" className="bg-primary text-primary-foreground">
                {label}
            </TooltipContent>
        </Tooltip>
    );
}

function ColumnHeaderLabel({
    icon: Icon,
    label,
    className,
}: {
    icon?: LucideIcon;
    label: string;
    className?: string;
}) {
    return (
        <span className={cn('inline-flex items-center gap-1.5', className)}>
            {Icon ? <Icon className="size-3.5 shrink-0 text-muted-foreground transition-colors group-hover/head:text-primary" strokeWidth={1.85} /> : null}
            {label}
        </span>
    );
}

function csvEscape(value: string): string {
    if (/[",\n]/.test(value)) {
        return `"${value.replaceAll('"', '""')}"`;
    }

    return value;
}

const headerCellClass =
    'group/head h-[45px] bg-[#FFFFFF] text-muted-foreground transition-colors hover:bg-primary/[0.08] hover:text-primary dark:bg-card';
