import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    CalendarDaysIcon,
    GaugeIcon,
    NetworkIcon,
    PuzzleIcon,
    SquarePenIcon,
    Trash2Icon,
    type LucideIcon,
} from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { visitBulkDelete } from '@/lib/bulk-delete';

import { PackageFormDialog, type PackageFormMember } from '@/components/package/PackageFormDialog';
import {
    ReferenceFormDialog,
    type ReferenceFormKind,
    type ReferenceFormRow,
} from '@/components/package/ReferenceFormDialog';

import type { PackageDetailMember } from '@/components/package/PackageDetailDialog';

import type { PackageOption } from '@/components/package/PackageForm';
import { Card } from '@/components/ui/card';

type PackageRow = PackageDetailMember;

type Filters = {
    search: string;
    status: string;
    recommended: string;
    sort: string;
    direction: 'asc' | 'desc';
};

type PackageIndexProps = {
    packages: Paginated<PackageRow>;
    filters: Filters;
    networks: PackageOption[];
    speeds: PackageOption[];
    terms: PackageOption[];
    addons: AddonOption[];
    networkTable: Paginated<NetworkOption>;
    speedTable: Paginated<SpeedOption>;
    termTable: Paginated<TermOption>;
    addonTable: Paginated<AddonOption>;
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

type SpeedOption = Pick<PackageOption, 'id' | 'mbps'>;
type TermOption = Pick<PackageOption, 'id' | 'months'>;
type QuickTableRow = NetworkOption | SpeedOption | TermOption | AddonOption;

type QuickTable = {
    key: 'network' | 'speed' | 'term' | 'addon';
    title: string;
    icon: LucideIcon;
    data: QuickTableRow[];
    pagination: Paginated<QuickTableRow>;
    getRowId: (row: QuickTableRow) => string;
    searchValue: (row: QuickTableRow) => string;
    cell: (row: QuickTableRow) => ReactNode;
    onCreate: () => void;
    onEdit: (row: QuickTableRow) => void;
    onDelete: (row: QuickTableRow) => void;
    createLabel: string;
};

function visitIndex(filters: Filters) {
    router.get(
        '/packages',
        {
            search: filters.search || undefined,
            status: filters.status || undefined,
            recommended: filters.recommended || undefined,
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
    networkTable,
    speedTable,
    termTable,
    addonTable,
}: PackageIndexProps) {
    const { t, locale } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const debounce = useRef<number>(0);
    const canDelete = can('packages.delete');
    const [formOpen, setFormOpen] = useState(false);
    const [editingPackage, setEditingPackage] = useState<PackageFormMember | null>(null);
    const [referenceForm, setReferenceForm] = useState<{
        kind: ReferenceFormKind;
        item: ReferenceFormRow | null;
    } | null>(null);
    const [referenceDelete, setReferenceDelete] = useState<{ kind: ReferenceFormKind; id: number } | null>(null);

    const quickTables: QuickTable[] = [
        {
            key: 'network' as const,
            title: t('packages.network'),
            icon: NetworkIcon,
            data: networkTable.data,
            pagination: networkTable,
            getRowId: (row) => String(row.id),
            searchValue: (row) => String((row as NetworkOption)[`name_${locale}`] ?? ''),
            cell: (row) => (
                <span className="inline-flex items-center gap-1.5">
                    <NetworkIcon className="size-3.5 shrink-0 text-primary" strokeWidth={1.8} />
                    {(row as NetworkOption)[`name_${locale}`] ?? '—'}
                </span>
            ),
            onCreate: () =>
                setReferenceForm({
                    kind: 'network',
                    item: null,
                }),
            onEdit: (row) =>
                setReferenceForm({
                    kind: 'network',
                    item: row as ReferenceFormRow,
                }),
            onDelete: (row) =>
                setReferenceDelete({
                    kind: 'network',
                    id: Number(row.id),
                }),
            createLabel: t('common.create'),
        },
        {
            key: 'speed' as const,
            title: t('packages.speed'),
            icon: GaugeIcon,
            data: speedTable.data,
            pagination: speedTable,
            getRowId: (row) => String(row.id),
            searchValue: (row) => String((row as SpeedOption).mbps ?? ''),
            cell: (row) => (
                <span className="inline-flex items-center gap-1.5">
                    <GaugeIcon className="size-3.5 shrink-0 text-primary" strokeWidth={1.8} />
                    {(row as SpeedOption).mbps ? `${(row as SpeedOption).mbps} Mbps` : '—'}
                </span>
            ),
            onCreate: () => setReferenceForm({ kind: 'speed', item: null }),
            onEdit: (row) => setReferenceForm({ kind: 'speed', item: row as ReferenceFormRow }),
            onDelete: (row) => setReferenceDelete({ kind: 'speed', id: Number(row.id) }),
            createLabel: t('common.create'),
        },
        {
            key: 'term' as const,
            title: t('packages.term'),
            icon: CalendarDaysIcon,
            data: termTable.data,
            pagination: termTable,
            getRowId: (row) => String(row.id),
            searchValue: (row) => String((row as TermOption).months ?? ''),
            cell: (row) => (
                <span className="inline-flex items-center gap-1.5">
                    <CalendarDaysIcon className="size-3.5 shrink-0 text-primary" strokeWidth={1.8} />
                    {(row as TermOption).months
                        ? `${(row as TermOption).months} ${(row as TermOption).months === 1 ? 'Month' : 'Months'}`
                        : '—'}
                </span>
            ),
            onCreate: () => setReferenceForm({ kind: 'term', item: null }),
            onEdit: (row) => setReferenceForm({ kind: 'term', item: row as ReferenceFormRow }),
            onDelete: (row) => setReferenceDelete({ kind: 'term', id: Number(row.id) }),
            createLabel: t('common.create'),
        },
        {
            key: 'addon' as const,
            title: t('packages.addon'),
            icon: PuzzleIcon,
            data: addonTable.data,
            pagination: addonTable,
            getRowId: (row) => String(row.id),
            searchValue: (row) => String((row as AddonOption)[`name_${locale}`] ?? ''),
            cell: (row) => (
                <span className="inline-flex items-center gap-1.5">
                    <PuzzleIcon className="size-3.5 shrink-0 text-primary" strokeWidth={1.8} />
                    {(row as AddonOption)[`name_${locale}`] ?? '—'}
                </span>
            ),
            onCreate: () =>
                setReferenceForm({
                    kind: 'addon',
                    item: null,
                }),
            onEdit: (row) =>
                setReferenceForm({
                    kind: 'addon',
                    item: row as ReferenceFormRow,
                }),
            onDelete: (row) =>
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
                            pagination={table.pagination}
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
                                    cell: (row) => {
                                        const Icon = table.icon;
                                        return <div className="flex items-center gap-2">{table.cell(row)}</div>;
                                    },
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
                        const nextDirection = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';

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
                        canDelete ? (ids) => visitBulkDelete('/packages/bulk-destroy', ids.map(Number)) : undefined
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
                        <FormControl compact className="w-full shrink-0 sm:w-44">
                            <Select
                                value={
                                    filters.recommended === '1'
                                        ? 'recommended'
                                        : filters.status === '1'
                                          ? 'active'
                                          : filters.status === '0'
                                            ? 'inactive'
                                            : 'all'
                                }
                                onValueChange={(value) =>
                                    visitIndex({
                                        ...filters,
                                        status: value === 'active' ? '1' : value === 'inactive' ? '0' : '',
                                        recommended: value === 'recommended' ? '1' : '',
                                    })
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder={t('common.status')} />
                                </SelectTrigger>

                                <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                    <SelectItem value="all">{t('common.all')}</SelectItem>
                                    <SelectItem value="recommended">{t('packages.recommended')}</SelectItem>
                                    <SelectItem value="active">{t('status.active')}</SelectItem>
                                    <SelectItem value="inactive">{t('status.inactive')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormControl>
                    }
                    columns={[
                        {
                            id: 'network',
                            header: t('packages.network'),
                            className: 'font-medium',
                            mobile: 'title',
                            cell: (row) => (
                                <span>
                                    {locale === 'en'
                                        ? row.network?.name_en
                                        : locale === 'zh'
                                          ? row.network?.name_zh
                                          : (row.network?.name_my ?? '—')}
                                </span>
                            ),
                        },
                        {
                            id: 'speed',
                            header: t('packages.speed'),
                            mobile: 'meta',
                            cell: (row) => <span>{row.speed?.mbps ?? '—'} Mbps</span>,
                        },

                        {
                            id: 'term',
                            header: t('packages.term'),
                            mobile: 'meta',
                            cell: (row) => <span>{row.term?.months ?? '—'} Months</span>,
                        },

                        {
                            id: 'price',
                            header: t('packages.price'),
                            sortable: true,
                            cell: (row) => <span>{row.price ?? '—'} Pts</span>,
                        },

                        {
                            id: 'installation_fee',
                            header: t('packages.installation_fee'),
                            sortable: true,
                            cell: (row) => <span>{row.installation_fee ?? '—'} Pts</span>,
                        },

                        // {
                        //     id: 'is_active',
                        //     header: t('common.status'),
                        //     sortable: true,
                        //     cell: (row) => (
                        //         <StatusBadge
                        //             status={
                        //                 row.is_active
                        //                     ? 'active'
                        //                     : 'inactive'
                        //             }
                        //         />
                        //     ),
                        // },

                        // {
                        //     id: 'recommended',
                        //     header: t('packages.recommended'),
                        //     cell: (row) =>
                        //         row.recommended
                        //             ? t('common.yes')
                        //             : t('common.no'),
                        // },

                        // {
                        //     id: 'created_at',
                        //     header: t('customers.joined'),
                        //     className:
                        //         'text-muted-foreground',
                        //     sortable: true,
                        //     cell: (row) =>
                        //         row.created_at ?? '—',
                        // },
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
                description={t('packages.delete_description')}
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (pendingIds.length !== 1) {
                        return;
                    }

                    router.delete(`/packages/${pendingIds[0]}`, {
                        onFinish: () => setPendingIds([]),
                    });
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
