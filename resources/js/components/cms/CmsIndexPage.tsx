import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { CircleDotIcon, LanguagesIcon, PlusIcon, SquarePenIcon, Trash2Icon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable, type DataTableColumn } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageHeader } from '@/components/PageHeader';
import { TableActionButton } from '@/components/TableActionButton';
import { Button } from '@/components/ui/button';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

export type CmsFilters = {
    search: string;
    status: string;
    lang: string;
    sort: string;
    direction: 'asc' | 'desc';
};

type CmsIndexPageProps<T extends { id: number }> = {
    titleKey: string;
    descriptionKey: string;
    createHref: string;
    createLabelKey: string;
    indexHref: string;
    destroyBase: string;
    items: Paginated<T>;
    filters: CmsFilters;
    columns: DataTableColumn<T>[];
    statusFilter?: 'active' | 'news';
    langFilter?: boolean;
};

export function CmsIndexPage<T extends { id: number }>({
    titleKey,
    descriptionKey,
    createHref,
    createLabelKey,
    indexHref,
    destroyBase,
    items,
    filters,
    columns,
    statusFilter,
    langFilter = true,
}: CmsIndexPageProps<T>) {
    const { t } = useTranslation();
    const { flash } = usePage().props;
    const deleteError = (usePage().props as { errors?: { delete?: string } }).errors?.delete;
    const [search, setSearch] = useState(filters.search);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);
    const debounce = useRef<number>(0);

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const visit = (next: Partial<CmsFilters>) => {
        router.get(indexHref, {
            search: (next.search ?? filters.search) || undefined,
            status: (next.status ?? filters.status) || undefined,
            lang: (next.lang ?? filters.lang) || undefined,
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
                            <SelectValue placeholder={t('customers.filter_status')} />
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
            {langFilter ? (
                <FormControl icon={LanguagesIcon} className="w-full shrink-0 sm:w-48">
                    <Select value={filters.lang || 'all'} onValueChange={(value) => visit({ lang: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder={t('cms.filter_lang')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{t('cms.all_languages')}</SelectItem>
                            <SelectItem value="en">{t('language.en')}</SelectItem>
                            <SelectItem value="my">{t('language.my')}</SelectItem>
                            <SelectItem value="zh">{t('language.zh')}</SelectItem>
                        </SelectContent>
                    </Select>
                </FormControl>
            ) : null}
        </>
    );

    return (
        <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
            <PageHeader
                eyebrow={t('menu.cms')}
                title={t(titleKey)}
                description={t(descriptionKey)}
                actions={
                    <Button asChild>
                        <Link href={createHref}>
                            <PlusIcon />
                            {t(createLabelKey)}
                        </Link>
                    </Button>
                }
            />
            {flash.success ? (
                <p className="rounded-[4px] bg-primary/10 px-3 py-2 text-sm text-foreground">{t(flash.success)}</p>
            ) : null}
            {deleteError ? <p className="rounded-[4px] bg-danger/10 px-3 py-2 text-sm text-danger">{deleteError}</p> : null}
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
                actions={(row) => (
                    <>
                        <TableActionButton
                            label={t('common.edit')}
                            icon={SquarePenIcon}
                            tone="edit"
                            href={`${destroyBase}/${row.id}/edit`}
                        />
                        <TableActionButton
                            label={t('common.delete')}
                            icon={Trash2Icon}
                            tone="danger"
                            onClick={() => setDeleteId(row.id)}
                        />
                    </>
                )}
                columns={columns}
            />
            <ConfirmDialog
                open={deleteId !== null}
                onOpenChange={(open) => {
                    if (! open) {
                        setDeleteId(null);
                    }
                }}
                title={t('cms.delete_title')}
                description={t('cms.delete_description')}
                confirmLabel={t('common.delete')}
                destructive
                processing={processing}
                onConfirm={() => {
                    if (deleteId === null) {
                        return;
                    }

                    router.delete(`${destroyBase}/${deleteId}`, {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                        onSuccess: () => setDeleteId(null),
                    });
                }}
            />
        </div>
    );
}
