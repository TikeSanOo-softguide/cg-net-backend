import { useMemo, useState, type ReactNode } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowDownIcon, ArrowUpIcon } from 'lucide-react';

import { Pagination, type Paginated } from '@/components/Pagination';
import { SearchInput } from '@/components/SearchInput';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
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

    const visitRow = (row: T) => {
        const to = href?.(row);

        if (to) {
            router.visit(to);
        }
    };

    return (
        <Card className={cn('gap-0 py-0', className)}>
            <CardHeader className="gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                {title ? <CardTitle className="text-sm font-semibold sm:text-[15px]">{title}</CardTitle> : <span />}
                <div className="flex w-full min-w-0 flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                    {filters}
                    <SearchInput
                        size="sm"
                        value={searchValue}
                        onChange={onSearchChange ?? setQuery}
                        placeholder={searchPlaceholder ?? t('common.search')}
                        className="sm:max-w-64"
                    />
                </div>
            </CardHeader>
            <CardContent className="px-0 pb-2 sm:px-2 sm:pb-3">
                <div className="hidden sm:block">
                    <Table className="text-[13px]">
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                {columns.map((column) => (
                                    <TableHead key={column.id} className="h-9 px-2.5 text-[11px]">
                                        {column.sortable && onSort ? (
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1 hover:text-foreground"
                                                onClick={() => onSort(column.id)}
                                            >
                                                {column.header}
                                                {sort === column.id ? (
                                                    direction === 'asc' ? (
                                                        <ArrowUpIcon className="size-3" />
                                                    ) : (
                                                        <ArrowDownIcon className="size-3" />
                                                    )
                                                ) : null}
                                            </button>
                                        ) : (
                                            column.header
                                        )}
                                    </TableHead>
                                ))}
                                {actions ? <TableHead className="h-9 w-24 px-2.5 text-[11px]">{t('common.actions')}</TableHead> : null}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.length === 0 ? (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell colSpan={columns.length + (actions ? 1 : 0)} className="h-16 text-center text-xs text-muted-foreground">
                                        {noResults}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                rows.map((row) => {
                                    const to = href?.(row);

                                    return (
                                        <TableRow
                                            key={getRowId(row)}
                                            className={to ? 'cursor-pointer' : undefined}
                                            onClick={to ? () => visitRow(row) : undefined}
                                        >
                                            {columns.map((column) => (
                                                <TableCell
                                                    key={column.id}
                                                    className={cn('h-9 px-2.5 py-1.5 text-[13px]', column.className)}
                                                >
                                                    {column.mobile === 'title' && to ? (
                                                        <Link href={to} className="font-medium hover:underline" onClick={(event) => event.stopPropagation()}>
                                                            {column.cell(row)}
                                                        </Link>
                                                    ) : (
                                                        column.cell(row)
                                                    )}
                                                </TableCell>
                                            ))}
                                            {actions ? (
                                                <TableCell className="h-9 px-2.5 py-1.5" onClick={(event) => event.stopPropagation()}>
                                                    {actions(row)}
                                                </TableCell>
                                            ) : null}
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                <ul className="flex flex-col gap-2 px-4 pb-3 sm:hidden">
                    {rows.length === 0 ? (
                        <li className="rounded-[8px] border border-border bg-card px-3 py-2.5 text-center text-xs text-muted-foreground">
                            {noResults}
                        </li>
                    ) : (
                        rows.map((row) => {
                            const to = href?.(row);
                            const body = (
                                <>
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            {mobileTitle ? (
                                                <p className="truncate text-[13px] font-medium">{mobileTitle.cell(row)}</p>
                                            ) : null}
                                            {mobileSubtitle ? (
                                                <p className="truncate text-xs text-muted-foreground">{mobileSubtitle.cell(row)}</p>
                                            ) : null}
                                        </div>
                                        {mobileBadge ? mobileBadge.cell(row) : null}
                                    </div>
                                    {mobileMeta ? (
                                        <p className="mt-1.5 font-mono text-[11px] text-muted-foreground">{mobileMeta.cell(row)}</p>
                                    ) : null}
                                </>
                            );

                            return (
                                <li key={getRowId(row)} className="rounded-[8px] border border-border bg-card px-3 py-2.5">
                                    {to ? (
                                        <Link href={to} className="block">
                                            {body}
                                        </Link>
                                    ) : (
                                        body
                                    )}
                                    {actions ? <div className="mt-2">{actions(row)}</div> : null}
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
    );
}
