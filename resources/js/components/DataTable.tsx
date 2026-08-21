import { useMemo, useState, type ReactNode } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowDownIcon, ArrowUpIcon, ChevronsUpDownIcon } from 'lucide-react';

import { Pagination, type Paginated } from '@/components/Pagination';
import { SearchInput } from '@/components/SearchInput';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
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
    href?: (row: T) => string;
    actions?: (row: T) => ReactNode;
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
}: DataTableProps<T>) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const noResults = emptyLabel ?? t('common.no_results');
    const searchValue = onSearchChange ? (search ?? '') : query;
    const serverDriven = Boolean(onSearchChange);

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
    const columnCount = columns.length + (actions ? 1 : 0);

    const visitRow = (row: T) => {
        const to = href?.(row);

        if (to) {
            router.visit(to);
        }
    };

    return (
        <TooltipProvider>
        <Card className={cn('gap-0 overflow-hidden py-0', className)}>
            <CardHeader className="gap-3 border-b border-border/70 bg-card px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
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
                </div>
            </CardHeader>
            <CardContent className="px-0 pb-0">
                <div className="hidden sm:block">
                    <Table>
                        <TableHeader className="bg-muted/45">
                            <TableRow className="hover:bg-transparent">
                                {columns.map((column) => (
                                    <TableHead key={column.id} className="h-[45px] px-4">
                                        {column.sortable && onSort ? (
                                            <button
                                                type="button"
                                                className={cn(
                                                    'inline-flex items-center gap-1.5 rounded-md py-0.5 transition-colors',
                                                    sort === column.id ? 'text-foreground' : 'hover:text-foreground',
                                                )}
                                                onClick={() => onSort(column.id)}
                                            >
                                                {column.header}
                                                {sort === column.id ? (
                                                    direction === 'asc' ? (
                                                        <ArrowUpIcon className="size-3.5 text-primary" />
                                                    ) : (
                                                        <ArrowDownIcon className="size-3.5 text-primary" />
                                                    )
                                                ) : (
                                                    <ChevronsUpDownIcon className="size-3.5 opacity-40" />
                                                )}
                                            </button>
                                        ) : (
                                            column.header
                                        )}
                                    </TableHead>
                                ))}
                                {actions ? (
                                    <TableHead className="h-[45px] w-px px-3 text-center">{t('common.actions')}</TableHead>
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
                                rows.map((row) => {
                                    const to = href?.(row);

                                    return (
                                        <TableRow
                                            key={getRowId(row)}
                                            className={cn(to && 'cursor-pointer', 'even:bg-muted/25')}
                                            onClick={to ? () => visitRow(row) : undefined}
                                        >
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
                                            {actions ? (
                                                <TableCell className="w-px px-3 text-center align-middle" onClick={(event) => event.stopPropagation()}>
                                                    <div className="flex h-full items-center justify-center gap-1.5">{actions(row)}</div>
                                                </TableCell>
                                            ) : null}
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                <ul className="flex flex-col gap-2.5 px-4 py-4 sm:hidden">
                    {rows.length === 0 ? (
                        <li className="rounded-xl border border-border/80 bg-muted/30 px-4 py-8 text-center text-sm text-muted-foreground">
                            {noResults}
                        </li>
                    ) : (
                        rows.map((row) => {
                            const to = href?.(row);
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
                                    key={getRowId(row)}
                                    className="rounded-xl border border-border/80 bg-card px-4 py-3.5 shadow-[0_1px_2px_rgb(23_50_54/0.04)]"
                                >
                                    {to ? (
                                        <Link href={to} className="block">
                                            {body}
                                        </Link>
                                    ) : (
                                        body
                                    )}
                                    {actions ? <div className="mt-3 flex items-center justify-center gap-1.5 border-t border-border/60 pt-3">{actions(row)}</div> : null}
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
        </TooltipProvider>
    );
}
