import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CircleDotIcon, SquarePenIcon, Trash2Icon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { FormControl } from '@/components/ui/form-control';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { visitBulkDelete } from '@/lib/bulk-delete';

import {
    PackageFormDialog,
    type PackageFormMember,
} from '@/components/package/PackageFormDialog';
import {
    ReferenceFormDialog,
    type ReferenceFormKind,
    type ReferenceFormRow,
} from '@/components/package/ReferenceFormDialog';

import type {
    PackageDetailMember,
} from '@/components/package/PackageDetailDialog';

import type {
    PackageOption,
} from '@/components/package/PackageForm';
import { Card } from '@/components/ui/card';

type PackageRow = PackageDetailMember;

type Filters = {
    search: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
};

type PackageIndexProps = {
    packages: Paginated<PackageRow>;
    filters: Filters;
    networks: PackageOption[];
    speeds: PackageOption[];
    terms: PackageOption[];
    addons: PackageOption[];
};

export type NetworkOption = {
    id: number;
    name_en: string | null;
    name_zh: string | null;
    name_my: string | null;
};

export type AddonOption = {
    id: number;
    name_en: string | null;
    name_zh: string | null;
    name_my: string | null;
};

function visitIndex(filters: Filters) {
    router.get(
        '/packages',
        {
            search: filters.search || undefined,
            status: filters.status || undefined,
            sort: filters.sort,
            direction: filters.direction,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

export default function PackageIndex({
    packages,
    filters,
    networks,
    speeds,
    terms,
    addons,
}: PackageIndexProps) {
    const { t,locale } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const debounce = useRef<number>(0);
    const canDelete = can('packages.delete');
    const [formOpen, setFormOpen] = useState(false);
    const [editingPackage, setEditingPackage] = useState<PackageFormMember | null>(null);
    const [referenceForm, setReferenceForm] = useState<{ kind: ReferenceFormKind; item: ReferenceFormRow | null } | null>(null);
    const [referenceDelete, setReferenceDelete] = useState<{ kind: ReferenceFormKind; id: number } | null>(null);

    const quickTables = [
        {
            key: 'network' as const,
            title: t('package.network'),
            data: networks,
            getRowId: (row: NetworkOption) => String(row.id),
            searchValue: (row: NetworkOption) =>String(row[`name_${locale}`] ?? ''),
            cell: (row: NetworkOption) =>row[`name_${locale}`] ?? '—',
            onCreate: () =>
                setReferenceForm({
                    kind: 'network',
                    item: null,
                }),
            onEdit: (row: NetworkOption) =>
                setReferenceForm({
                    kind: 'network',
                    item: row as ReferenceFormRow,
                }),
            onDelete: (row: NetworkOption) =>
                setReferenceDelete({
                    kind: 'network',
                    id: Number(row.id),
                }),
            createLabel: t('common.create'),
        },
        {
            key: 'speed' as const,
            title: t('package.speed'),
            data: speeds,
            getRowId: (row: PackageOption) => String(row.id),
            searchValue: (row: PackageOption) => String(row.mbps ?? ''),
            cell: (row: PackageOption) => (row.mbps ? `${row.mbps} Mbps` : '—'),
            onCreate: () => setReferenceForm({ kind: 'speed', item: null }),
            onEdit: (row: PackageOption) => setReferenceForm({ kind: 'speed', item: row as ReferenceFormRow }),
            onDelete: (row: PackageOption) => setReferenceDelete({ kind: 'speed', id: Number(row.id) }),
            createLabel: t('common.create'),
        },
        {
            key: 'term' as const,
            title: t('package.term'),
            data: terms,
            getRowId: (row: PackageOption) => String(row.id),
            searchValue: (row: PackageOption) => String(row.months ?? ''),
            cell: (row: PackageOption) => row.months ? `${row.months} ${row.months === 1 ? 'Month' : 'Months'}` : '—',
            onCreate: () => setReferenceForm({ kind: 'term', item: null }),
            onEdit: (row: PackageOption) => setReferenceForm({ kind: 'term', item: row as ReferenceFormRow }),
            onDelete: (row: PackageOption) => setReferenceDelete({ kind: 'term', id: Number(row.id) }),
            createLabel: t('common.create'),
        },
        {
            key: 'addon' as const,
            title: t('package.addon'),
            data: addons,
            getRowId: (row: AddonOption) => String(row.id),
            searchValue: (row: AddonOption) =>String(row[`name_${locale}`] ?? ''),
            cell: (row: AddonOption) =>row[`name_${locale}`] ?? '—',
            onCreate: () =>
                setReferenceForm({
                    kind: 'addon',
                    item: null,
                }),
            onEdit: (row: AddonOption) =>
                setReferenceForm({
                    kind: 'addon',
                    item: row as ReferenceFormRow,
                }),
            onDelete: (row: AddonOption) =>
                setReferenceDelete({
                    kind: 'addon',
                    id: Number(row.id),
                }),
            createLabel: t('common.create'),
        },        
    ];
    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => {
        return () => {
            window.clearTimeout(debounce.current);
        };
    }, []);

    return (
        <>
            <Head title={t('menu.packages')} />

            <PageContent>
                <PageHeader />

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {quickTables.map((table) => (
                        <DataTable
                            key={table.key}
                            className="min-w-0"
                            numbered={false}
                            title={table.title}
                            data={table.data}
                            getRowId={table.getRowId}
                            searchPlaceholder={t('dashboard.search_requests')}
                            onCreate={can('packages.create') ? table.onCreate : undefined}
                            createLabel={table.createLabel}
                            actions={(row) => (
                                <>
                                    {can('packages.update') ? (
                                        <TableActionButton
                                            label={t('common.edit')}
                                            icon={SquarePenIcon}
                                            tone="edit"
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                table.onEdit(row as PackageOption);
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
                                                table.onDelete(row as PackageOption);
                                            }}
                                        />
                                    ) : null}
                                </>
                            )}
                            columns={[
                                {
                                    id: table.key,
                                    header: table.title,
                                    className: 'font-medium',
                                    mobile: 'title',
                                    searchValue: table.searchValue,
                                    cell: table.cell,
                                },
                            ]}
                        />
                    ))}
                </div>
                

                <DataTable
                    data={packages.data}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={(value) => {
                        setSearch(value);
                        window.clearTimeout(debounce.current);
                        debounce.current = window.setTimeout(() => {
                            visitIndex({
                                ...filters,
                                search: value,
                            });
                        }, 300);
                    }}
                    searchPlaceholder={t('packages.search_placeholder')}
                    sort={filters.sort}
                    direction={filters.direction}
                    onSort={(column) => {
                        const nextDirection =
                            filters.sort === column &&
                            filters.direction === 'asc'
                                ? 'desc'
                                : 'asc';

                        visitIndex({
                            ...filters,
                            sort: column,
                            direction: nextDirection,
                        });
                    }}
                    onCreate={
                        can('packages.create')
                            ? () => {
                                  setEditingPackage(null);
                                  setFormOpen(true);
                              }
                            : undefined
                    }
                    createLabel={t('packages.create')}
                    pagination={packages}
                    onBulkDelete={
                        canDelete
                            ? (ids) =>
                                  visitBulkDelete(
                                      '/packages/bulk-destroy',
                                      ids.map(Number),
                                  )
                            : undefined
                    }
                    bulkDeleteTitle={t('packages.bulk_delete_title')}
                    actions={(row) => (
                        <>
                            {can('packages.update') ? (
                                <TableActionButton
                                    label={t('common.edit')}
                                    icon={SquarePenIcon}
                                    tone="edit"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        setEditingPackage(row);
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

                                        setPendingIds([row.id]);
                                    }}
                                />
                            ) : null}
                        </>
                    )}
                    filters={
                        <FormControl
                            icon={CircleDotIcon}
                            compact
                            className="w-full shrink-0 sm:w-48"
                        >
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(value) =>
                                    visitIndex({
                                        ...filters,
                                        status:
                                            value === 'all'
                                                ? ''
                                                : value,
                                    })
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue
                                        placeholder={t('common.status')}
                                    />
                                </SelectTrigger>

                                <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                    <SelectItem value="all">
                                        {t('common.all')}
                                    </SelectItem>

                                    <SelectItem value="active">
                                        {t('status.active')}
                                    </SelectItem>

                                    <SelectItem value="inactive">
                                        {t('status.inactive')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormControl>
                    }
                    columns={[
                        {
                            id: 'network',
                            header: t('package.network'),
                            className: 'font-medium',
                            mobile: 'title',
                            cell: (row) => (
                               <span>
                                {
                                locale === 'en'
                                 ? row.network?.name_en
                                 : locale === 'zh'
                                 ? row.network?.name_zh
                                 : row.network?.name_my ?? '—'
                                }
                               </span>
                            ),
                        },
                        {
                            id: 'speed',
                            header: t('package.speed'),
                            mobile: 'meta',
                            cell: (row) => (
                                <span>
                                  {row.speed?.mbps ?? '—'}
                                </span>
                            ),
                        },

                        {
                            id: 'term',
                            header: t('package.term'),
                            mobile: 'meta',
                            cell: (row) => (
                                <span>
                                    {row.term?.months ?? '—'}
                                </span>
                            ),
                        },

                        {
                            id: 'price',
                            header: t('package.price'),
                            sortable: true,
                            cell: (row) => (
                                <span>{row.price}</span>
                            ),
                        },

                        {
                            id: 'installation_fee',
                            header: t('package.installation_fee'),
                            sortable: true,
                            cell: (row) => (
                                <span>
                                    {row.installation_fee}
                                </span>
                            ),
                        },

                        {
                            id: 'is_active',
                            header: t('common.status'),
                            sortable: true,
                            cell: (row) => (
                                <StatusBadge
                                    status={
                                        row.is_active
                                            ? 'active'
                                            : 'inactive'
                                    }
                                />
                            ),
                        },

                        {
                            id: 'recommended',
                            header: t('package.recommended'),
                            cell: (row) =>
                                row.recommended
                                    ? t('common.yes')
                                    : t('common.no'),
                        },

                        {
                            id: 'created_at',
                            header: t('customers.joined'),
                            className:
                                'text-muted-foreground',
                            sortable: true,
                            cell: (row) =>
                                row.created_at ?? '—',
                        },
                    ]}
                />
            </PageContent>

            {/* Create / Edit Package Dialog */}
            <PackageFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                package={editingPackage}
                networks={networks}
                speeds={speeds}
                terms={terms}
            />

            {/* Delete Confirmation */}
            <ReferenceFormDialog
                open={referenceForm !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setReferenceForm(null);
                    }
                }}
                kind={referenceForm?.kind ?? 'network'}
                item={referenceForm?.item ?? null}
            />

            <ConfirmDialog
                open={pendingIds.length === 1}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingIds([]);
                    }
                }}
                title={t('packages.delete_title')}
                description={t(
                    'packages.delete_description',
                )}
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (pendingIds.length !== 1) {
                        return;
                    }

                    router.delete(
                        `/packages/${pendingIds[0]}`,
                        {
                            onFinish: () =>
                                setPendingIds([]),
                        },
                    );
                }}
            />

            <ConfirmDialog
                open={referenceDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setReferenceDelete(null);
                    }
                }}
                title={t('packages.delete_title')}
                description={t('packages.delete_description')}
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (!referenceDelete) {
                        return;
                    }

                    const routeMap: Record<ReferenceFormKind, string> = {
                        network: '/networks',
                        speed: '/speeds',
                        term: '/terms',
                        addon: '/addons',
                    };

                    router.delete(`${routeMap[referenceDelete.kind]}/${referenceDelete.id}`, {
                        onFinish: () => setReferenceDelete(null),
                    });
                }}
            />
        </>
    );
}
