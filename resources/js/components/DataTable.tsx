import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Link, router } from '@inertiajs/react';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    BanknoteIcon,
    CalendarIcon,
    ChevronsUpDownIcon,
    CircleDotIcon,
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
import { Button } from '@/components/ui/button';
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

    const visitRow = (row: T) => {
        const to = href?.(row);

        if (to) {
            router.visit(to);
        }
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

    return (
        <TooltipProvider>
            <Card className={cn('gap-0 overflow-hidden bg-[#FFFFFF] py-0 dark:bg-card', className)}>
                <CardHeader className="gap-3 border-b border-border/70 bg-[#FFFFFF] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-[45px] dark:bg-card">
                    {title ? (
                        <CardTitle className="text-[15px] font-semibold tracking-tight sm:text-base">{title}</CardTitle>
                    ) : (
                        <span />
                    )}
                    <div className="flex w-full min-w-0 flex-col gap-2 sm:w-auto sm:flex-row sm:items-center sm:justify-end">
                        {filters}
                        <SearchInput
                            value={searchValue}
                            onChange={onSearchChange ?? setQuery}
                            placeholder={searchPlaceholder ?? t('common.search')}
                            className="w-full sm:w-72"
                        />
                        {canSelect && selectedIds.length > 0 ? (
                            bulkActions ?? (onBulkDelete ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="destructive"
                                    className="shrink-0 sm:ml-auto"
                                    disabled={processing}
                                    onClick={requestBulkDelete}
                                >
                                    <Trash2Icon />
                                    {t('common.delete_selected_count').replace(':count', String(selectedIds.length))}
                                </Button>
                            ) : null)
                        ) : null}
                    </div>
                </CardHeader>
                <CardContent className="px-0 pb-0">
                    <div className="hidden sm:block">
                        <Table>
                            <TableHeader className="bg-[#FFFFFF] dark:bg-card">
                                <TableRow className="hover:bg-transparent">
                                    {canSelect ? (
                                        <TableHead className={cn(headerCellClass, 'w-10 pr-0')}>
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
                                        <TableHead className={cn(headerCellClass, 'w-12', canSelect && 'pl-3')}>
                                            <ColumnHeaderLabel icon={HashIcon} label={t('common.no')} />
                                        </TableHead>
                                    ) : null}
                                    {columns.map((column) => {
                                        const HeaderIcon = column.icon ?? DEFAULT_COLUMN_ICONS[column.id];
                                        const label = <ColumnHeaderLabel icon={HeaderIcon} label={column.header} />;

                                        return (
                                            <TableHead key={column.id} className={headerCellClass}>
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
                                        <TableHead className={cn(headerCellClass, 'w-px text-center')}>
                                            <ColumnHeaderLabel icon={Settings2Icon} label={t('common.actions')} className="justify-center" />
                                        </TableHead>
                                    ) : null}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.length === 0 ? (
                                    <TableRow className="hover:bg-transparent">
                                        <TableCell colSpan={columnCount} className="h-28 text-center">
                                            <p className="text-sm font-medium text-foreground">{noResults}</p>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    rows.map((row, index) => {
                                        const to = href?.(row);
                                        const id = getRowId(row);
                                        const enabled = isRowSelectable?.(row) ?? true;
                                        const selected = selectedIds.includes(id);

                                        return (
                                            <TableRow
                                                key={id}
                                                data-state={selected ? 'selected' : undefined}
                                                className={cn(to && 'cursor-pointer', 'bg-[#FFFFFF] dark:bg-card')}
                                                onClick={to ? () => visitRow(row) : undefined}
                                            >
                                                {canSelect ? (
                                                    <TableCell className="w-10 pr-0" onClick={(event) => event.stopPropagation()}>
                                                        <TableCheckbox
                                                            checked={selected}
                                                            disabled={! enabled}
                                                            label={t('common.select_row')}
                                                            onChange={() => toggleRow(id, enabled)}
                                                        />
                                                    </TableCell>
                                                ) : null}
                                                {numbered ? (
                                                    <TableCell className={cn('w-12 tabular-nums text-muted-foreground', canSelect && 'pl-3')}>
                                                        {indexStart + index}
                                                    </TableCell>
                                                ) : null}
                                                {columns.map((column) => (
                                                    <TableCell key={column.id} className={column.className}>
                                                        {column.mobile === 'title' && to ? (
                                                            <Link
                                                                href={to}
                                                                className="font-medium text-foreground hover:text-primary hover:underline"
                                                                onClick={(event) => event.stopPropagation()}
                                                            >
                                                                {column.cell(row)}
                                                            </Link>
                                                        ) : (
                                                            column.cell(row)
                                                        )}
                                                    </TableCell>
                                                ))}
                                                {showActions ? (
                                                    <TableCell className="w-px text-center align-middle" onClick={(event) => event.stopPropagation()}>
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

                    <ul className="flex flex-col gap-2.5 bg-[#FFFFFF] px-5 py-4 sm:hidden dark:bg-card">
                        {rows.length === 0 ? (
                            <li className="rounded-xl border border-border/80 bg-muted/30 px-4 py-8 text-center text-sm text-muted-foreground">
                                {noResults}
                            </li>
                        ) : (
                            rows.map((row, index) => {
                                const to = href?.(row);
                                const id = getRowId(row);
                                const enabled = isRowSelectable?.(row) ?? true;
                                const selected = selectedIds.includes(id);
                                const body = (
                                    <>
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
                                    </>
                                );

                                return (
                                    <li
                                        key={id}
                                        className={cn(
                                            'rounded-xl border border-border/80 bg-card px-4 py-3.5 shadow-[0_1px_2px_rgb(23_50_54/0.04)]',
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
                                        {to ? (
                                            <Link href={to} className="block">
                                                {body}
                                            </Link>
                                        ) : (
                                            body
                                        )}
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

const headerCellClass =
    'group/head h-[45px] bg-[#FFFFFF] text-muted-foreground transition-colors hover:bg-primary/[0.08] hover:text-primary dark:bg-card';
