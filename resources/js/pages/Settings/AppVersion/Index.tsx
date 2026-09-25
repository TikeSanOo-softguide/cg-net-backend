import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CircleDotIcon, DownloadIcon, EyeIcon, SquarePenIcon, Trash2Icon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable, type DataTableColumn } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { AppVersionDetailDialog } from '@/components/settings/app-version/AppVersionDetailDialog';
import { AppVersionFormDialog } from '@/components/settings/app-version/AppVersionFormDialog';
import type { AppVersionFormData } from '@/components/settings/app-version/AppVersionForm';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { visitBulkDelete } from '@/lib/bulk-delete';

type AppVersionRow = AppVersionFormData & {s
    created_at: string | null;
    updated_at: string | null;
};

type Filters = {
    platform: string;
    version: string;
    sort: string;
    direction: 'asc' | 'desc';
};

type Props = {
    items: Paginated<AppVersionRow>;
    filters: Filters;
     versionOptions: string[];
};

function visitIndex(filters: Filters) {
    router.get(
        '/settings/app-version',
        {
            platform: filters.platform || undefined,
            version: filters.version || undefined,
            sort: filters.sort || undefined,
            direction: filters.direction,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

export default function AppVersionIndex({ items, filters ,versionOptions}: Props) {
    const { t } = useTranslation();
    const can = useCan();
    const canDelete = can('settings.delete');

    const [platform, setPlatform] = useState(filters.platform || '');
    const [version, setVersion] = useState(filters.version || '');
    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const [formOpen, setFormOpen] = useState(false);
    const [detailOpen, setDetailOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<AppVersionFormData | null>(null);
    const [viewingItem, setViewingItem] = useState<AppVersionFormData | null>(null);

    useEffect(() => {
        setPlatform(filters.platform || '');
    }, [filters.platform]);

    useEffect(() => {
        setVersion(filters.version || '');
    }, [filters.version]);

    const toFormData = (row: AppVersionRow): AppVersionFormData => ({
        id: row.id,
        platform: row.platform as AppVersionFormData['platform'],
        version: row.version,
        minimum_version: row.minimum_version,
        download_url: row.download_url,
        release_notes_en: row.release_notes_en ?? '',
        release_notes_zh: row.release_notes_zh ?? '',
        release_notes_my: row.release_notes_my ?? '',
        force_update: row.force_update,
        status: row.status as AppVersionFormData['status'],
    });

    const columns: DataTableColumn<AppVersionRow>[] = [
        {
            id: 'platform',
            header: t('common.platform'),
            cell: (row) => <span className="uppercase">{row.platform}</span>,
            sortable: true,
        },
        {
            id: 'version',
            header: t('settings.app_version.version'),
            cell: (row) => (
                <div className="flex flex-col text-left">
                    <span className="font-medium">{row.version}</span>
                    <span className="text-[11px] text-muted-foreground">Min: {row.minimum_version}</span>
                </div>
            ),
            sortable: true,
        },
        {
            id: 'download_url',
            header: t('common.link'),
            cell: (row) =>
                row.download_url ? (
                    <a
                        href={row.download_url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                        <DownloadIcon className="size-3.5" />
                        {row.download_url}
                    </a>
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                ),
        },
        {
            id: 'force_update',
            header: t('settings.app_version.force_update'),
            cell: (row) => {
                const isForced = Number(row.force_update) === 1;

                return (
                    <span
                        className={`inline-flex items-center px-2 py-0.5 rounded-full text-[12px] font-medium ${
                            isForced
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'
                        }`}
                    >
                        {isForced ? t('common.enabled') : t('common.disabled')}
                    </span>
                );
            },
            sortable: true,
        },
        {
            id: 'status',
            header: t('common.status'),
            cell: (row) => <StatusBadge status={row.status} className="text-[12px]" />,
        },
    ];

    return (
        <>
            <Head title={t('menu.app_version')} />
            <PageContent>
                <PageHeader />

                <div className="rounded-xl border bg-card shadow-sm">
                    <DataTable
                        data={items.data}
                        columns={columns}
                        getRowId={(row) => String(row.id)}
                        showSearch={false}
                        sort={filters.sort}
                       onView={(row) => {
                            setViewingItem(toFormData(row));
                            setDetailOpen(true);
                        }}
                        onCreate={
                            can('settings.create')
                                ? () => {
                                      setEditingItem(null);
                                      setFormOpen(true);
                                  }
                                : undefined
                        }
                        createLabel={t('settings.app_version.create')}
                        pagination={items}
                        onBulkDelete={
                            canDelete
                                ? (ids) => visitBulkDelete('/settings/app-version/bulk-destroy', ids.map(Number))
                                : undefined
                        }
                        bulkDeleteTitle={t('common.bulk_delete_title')}
                        filters={
                            <>
                                <FormControl icon={CircleDotIcon} compact className="w-full shrink-0 sm:w-40">
                                    <Select
                                        value={platform || 'all'}
                                        onValueChange={(value) => {
                                            const nextPlatform = value === 'all' ? '' : value;
                                            setPlatform(nextPlatform);
                                            visitIndex({ ...filters, platform: nextPlatform });
                                        }}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder={t('common.platform')} />
                                        </SelectTrigger>
                                        <SelectContent className="[&_[data-slot=select-item]]:text-xs">
                                            <SelectItem value="all">{t('common.all')}</SelectItem>
                                            <SelectItem value="android">{t('common.platform_android')}</SelectItem>
                                            <SelectItem value="ios">{t('common.platform_ios')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormControl>

                                <FormControl icon={CircleDotIcon} compact className="w-full shrink-0 sm:w-40">
                                    <Select
                                        value={version || 'all'}
                                        onValueChange={(value) => {
                                            const nextVersion = value === 'all' ? '' : value;
                                            setVersion(nextVersion);
                                            visitIndex({ ...filters, version: nextVersion });
                                        }}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder={t('settings.app_version.version')} />
                                        </SelectTrigger>
                                        <SelectContent className="[&_[data-slot=select-item]]:text-xs">
                                            <SelectItem value="all">{t('common.all')}</SelectItem>
                                            {versionOptions.map((option) => (
                                                <SelectItem key={option} value={option}>
                                                    {option}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormControl>
                            </>
                        }
                        actions={(row) => (
                            <>
                                {can('settings.update') ? (
                                    <TableActionButton
                                        label={t('common.edit')}
                                        icon={SquarePenIcon}
                                        tone="edit"
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            setEditingItem(toFormData(row));
                                            setFormOpen(true);
                                        }}
                                    />
                                ) : null}
                                {canDelete ? (
                                    <TableActionButton
                                        label={t('common.delete')}
                                        icon={Trash2Icon}
                                        tone="danger"
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            setPendingIds([row.id!]);
                                        }}
                                    />
                                ) : null}
                            </>
                        )}
                    />
                </div>
            </PageContent>

            <AppVersionDetailDialog
                open={detailOpen}
                onOpenChange={(open) => {
                    setDetailOpen(open);
                    if (!open) setViewingItem(null);
                }}
                item={viewingItem}
                onEdit={(item) => {
                    setDetailOpen(false);
                    setViewingItem(null);
                    setEditingItem(item);
                    setFormOpen(true);
                }}
            />

            <AppVersionFormDialog
                open={formOpen}
                onOpenChange={(open) => {
                    setFormOpen(open);
                    if (!open) setEditingItem(null);
                }}
                version={editingItem}
            />

            <ConfirmDialog
                open={pendingIds.length === 1}
                onOpenChange={(open) => {
                    if (!open) setPendingIds([]);
                }}
                title={t('common.delete_title')}
                description={t('common.delete_description')}
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (pendingIds.length !== 1) return;
                    router.delete(`/settings/app-version/${pendingIds[0]}`, {
                        onFinish: () => setPendingIds([]),
                    });
                }}
            />
        </>
    );
}
