import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    CalendarDaysIcon,
    GaugeIcon,
    NetworkIcon,
    PuzzleIcon,
    SquarePenIcon,
    StarIcon,
    Trash2Icon,
    type LucideIcon,
} from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { MultiSelect } from '@/components/MultiSelect';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
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
import { truncateText } from '@/lib/utils';
import { FormControl } from '@/components/ui/form-control';

type PackageRow = PackageDetailMember;

type Filters = {
    search: string;
    status: string;
    recommended: string;
    sort: string;
    direction: 'asc' | 'desc';
    network_search?: string;
    speed_search?: string;
    term_search?: string;
    addon_search?: string;
    network_ids?: string[];
    speed_ids?: string[];
    term_ids?: string[];
    addon_ids?: string[];
    status_filters?: string[];
};

type PackageIndexProps = {
    packages: Paginated<PackageRow>;
    filters: Filters;
    networks: PackageOption[];
    speeds: PackageOption[];
    terms: PackageOption[];
    addons: AddonOption[];
    networkTable: NetworkOption[];
    speedTable: SpeedOption[];
    termTable: TermOption[];
    addonTable: AddonOption[];
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
            network_search: filters.network_search || undefined,
            speed_search: filters.speed_search || undefined,
            term_search: filters.term_search || undefined,
            addon_search: filters.addon_search || undefined,
            status_filters: filters.status_filters?.length ? filters.status_filters : undefined,
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
    const [networkSearch, setNetworkSearch] = useState('');
    const [speedSearch, setSpeedSearch] = useState('');
    const [termSearch, setTermSearch] = useState('');
    const [addonSearch, setAddonSearch] = useState('');
    const [networkIds, setNetworkIds] = useState(filters.network_ids ?? []);
    const [speedIds, setSpeedIds] = useState(filters.speed_ids ?? []);
    const [termIds, setTermIds] = useState(filters.term_ids ?? []);
    const [addonIds, setAddonIds] = useState(filters.addon_ids ?? []);
    const [statusFilters, setStatusFilters] = useState(
        filters.status_filters ??
            [
                filters.status === '1' ? 'active' : filters.status === '0' ? 'inactive' : '',
                filters.recommended === '1' ? 'recommended' : '',
            ].filter(Boolean),
    );
    const networkDebounce = useRef<number>(0);
    const speedDebounce = useRef<number>(0);
    const termDebounce = useRef<number>(0);
    const addonDebounce = useRef<number>(0);
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
    const iconClass = 'size-7 shrink-0 rounded-[6px] bg-primary/12 p-1 text-primary';
    const Icon = ({ icon: Icon }: { icon: LucideIcon }) => <Icon className={iconClass} strokeWidth={1.8} />;

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => {
        setNetworkSearch(filters.network_search ?? '');
    }, [filters.network_search]);

    useEffect(() => {
        setSpeedSearch(filters.speed_search ?? '');
    }, [filters.speed_search]);

    useEffect(() => {
        setTermSearch(filters.term_search ?? '');
    }, [filters.term_search]);

    useEffect(() => {
        setAddonSearch(filters.addon_search ?? '');
    }, [filters.addon_search]);

    useEffect(() => setNetworkIds(filters.network_ids ?? []), [filters.network_ids]);
    useEffect(() => setSpeedIds(filters.speed_ids ?? []), [filters.speed_ids]);
    useEffect(() => setTermIds(filters.term_ids ?? []), [filters.term_ids]);
    useEffect(() => setAddonIds(filters.addon_ids ?? []), [filters.addon_ids]);
    useEffect(() => {
        setStatusFilters(
            filters.status_filters ??
                [
                    filters.status === '1' ? 'active' : filters.status === '0' ? 'inactive' : '',
                    filters.recommended === '1' ? 'recommended' : '',
                ].filter(Boolean),
        );
    }, [filters.recommended, filters.status, filters.status_filters]);

    useEffect(() => {
        return () => {
            window.clearTimeout(debounce.current);
            window.clearTimeout(debounce.current);
            window.clearTimeout(networkDebounce.current);
            window.clearTimeout(speedDebounce.current);
            window.clearTimeout(termDebounce.current);
            window.clearTimeout(addonDebounce.current);
        };
    }, []);

    return (
        <>
            <Head title={t('menu.packages')} />

            <PageContent>
                <PageHeader />

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <DataTable
                        data={networkTable}
                        className={networkTable.length > 5 ? 'max-h-90 overflow-y-auto' : undefined}
                        getRowId={(row) => String(row.id)}
                        search={networkSearch}
                        onSearchChange={(value) => {
                            setNetworkSearch(value);
                            if (networkDebounce.current) {
                                window.clearTimeout(networkDebounce.current);
                            }
                            networkDebounce.current = window.setTimeout(() => {
                                visitIndex({
                                    ...filters,
                                    network_search: value,
                                });
                            }, 300);
                        }}
                        searchPlaceholder={t('packages.networks.search_placeholder')}
                        sort={filters.sort}
                        direction={filters.direction}
                        onSort={(column) => {
                            const nextDirection =
                                filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';

                            visitIndex({
                                ...filters,
                                sort: column,
                                direction: nextDirection,
                            });
                        }}
                        onCreate={() =>
                            setReferenceForm({
                                kind: 'network',
                                item: null,
                            })
                        }
                        createLabel={t('networks.create')}
                        onBulkDelete={
                            canDelete ? (ids) => visitBulkDelete('/networks/bulk-destroy', ids.map(Number)) : undefined
                        }
                        bulkDeleteTitle={t('networks.bulk_delete_title')}
                        actions={(row) => (
                            <>
                                {can('networks.update') ? (
                                    <TableActionButton
                                        label={t('common.edit')}
                                        icon={SquarePenIcon}
                                        tone="edit"
                                        onClick={(event) => {
                                            event.stopPropagation();

                                            setReferenceForm({
                                                kind: 'network',
                                                item: row as ReferenceFormRow,
                                            });
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
                                            setReferenceDelete({ kind: 'network', id: row.id });
                                        }}
                                    />
                                ) : null}
                            </>
                        )}
                        columns={[
                            {
                                id: 'network',
                                header: t('packages.network'),
                                className: 'font-medium',
                                mobile: 'title',
                                sortable: true,
                                searchValue: (row) =>
                                    String(
                                        locale === 'en'
                                            ? (row.name_en ?? '')
                                            : locale === 'zh'
                                              ? (row.name_zh ?? '')
                                              : (row.name_my ?? ''),
                                    ),
                                cell: (row) => (
                                    <span className="inline-flex items-center gap-1.5">
                                        <NetworkIcon
                                            className="size-7 shrink-0 rounded-[6px] bg-primary/12 p-1 text-primary"
                                            strokeWidth={1.8}
                                        />
                                        {truncateText(
                                            locale === 'en'
                                                ? (row.name_en ?? '—')
                                                : locale === 'zh'
                                                  ? (row.name_zh ?? '—')
                                                  : (row.name_my ?? '—'),
                                            50,
                                        )}
                                    </span>
                                ),
                            },
                        ]}
                    />
                    <DataTable
                        data={speedTable}
                        className={speedTable.length > 5 ? 'max-h-90 overflow-y-auto' : undefined}
                        getRowId={(row) => String(row.id)}
                        search={speedSearch}
                        onSearchChange={(value) => {
                            setSpeedSearch(value);
                            if (speedDebounce.current) {
                                window.clearTimeout(speedDebounce.current);
                            }
                            speedDebounce.current = window.setTimeout(() => {
                                visitIndex({
                                    ...filters,
                                    speed_search: value,
                                });
                            }, 300);
                        }}
                        searchPlaceholder={t('packages.speeds.search_placeholder')}
                        sort={filters.sort}
                        direction={filters.direction}
                        onSort={(column) => {
                            const nextDirection =
                                filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
                            visitIndex({
                                ...filters,
                                sort: column,
                                direction: nextDirection,
                            });
                        }}
                        onCreate={() =>
                            setReferenceForm({
                                kind: 'speed',
                                item: null,
                            })
                        }
                        createLabel={t('packages.speeds.create')}
                        onBulkDelete={
                            canDelete ? (ids) => visitBulkDelete('/speeds/bulk-destroy', ids.map(Number)) : undefined
                        }
                        bulkDeleteTitle={t('speeds.bulk_delete_title')}
                        actions={(row) => (
                            <>
                                {can('speeds.update') ? (
                                    <TableActionButton
                                        label={t('common.edit')}
                                        icon={SquarePenIcon}
                                        tone="edit"
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            setReferenceForm({
                                                kind: 'speed',
                                                item: row as ReferenceFormRow,
                                            });
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
                                            setReferenceDelete({ kind: 'speed', id: row.id });
                                        }}
                                    />
                                ) : null}
                            </>
                        )}
                        columns={[
                            {
                                id: 'speed',
                                header: t('packages.speed'),
                                className: 'font-medium',
                                mobile: 'title',
                                sortable: true,
                                searchValue: (row) => String((row as SpeedOption).mbps ?? ''),
                                cell: (row) => (
                                    <span className="inline-flex items-center gap-1.5">
                                        <Icon icon={GaugeIcon} />
                                        {(row as SpeedOption).mbps ? `${(row as SpeedOption).mbps} Mbps` : '—'}
                                    </span>
                                ),
                            },
                        ]}
                    />

                    <DataTable
                        data={termTable}
                        className={termTable.length > 5 ? 'max-h-90 overflow-y-auto' : undefined}
                        getRowId={(row) => String(row.id)}
                        search={termSearch}
                        onSearchChange={(value) => {
                            setTermSearch(value);
                            if (termDebounce.current) {
                                window.clearTimeout(termDebounce.current);
                            }
                            termDebounce.current = window.setTimeout(() => {
                                visitIndex({
                                    ...filters,
                                    term_search: value,
                                });
                            }, 300);
                        }}
                        searchPlaceholder={t('packages.terms.search_placeholder')}
                        sort={filters.sort}
                        direction={filters.direction}
                        onSort={(column) => {
                            const nextDirection =
                                filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
                            visitIndex({
                                ...filters,
                                sort: column,
                                direction: nextDirection,
                            });
                        }}
                        onCreate={() =>
                            setReferenceForm({
                                kind: 'term',
                                item: null,
                            })
                        }
                        createLabel={t('term.create')}
                        onBulkDelete={
                            canDelete ? (ids) => visitBulkDelete('/terms/bulk-destroy', ids.map(Number)) : undefined
                        }
                        bulkDeleteTitle={t('terms.bulk_delete_title')}
                        actions={(row) => (
                            <>
                                {can('terms.update') ? (
                                    <TableActionButton
                                        label={t('common.edit')}
                                        icon={SquarePenIcon}
                                        tone="edit"
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            setReferenceForm({
                                                kind: 'term',
                                                item: row as ReferenceFormRow,
                                            });
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
                                            setReferenceDelete({ kind: 'term', id: row.id });
                                        }}
                                    />
                                ) : null}
                            </>
                        )}
                        columns={[
                            {
                                id: 'network',
                                header: t('packages.network'),
                                className: 'font-medium',
                                mobile: 'title',
                                sortable: true,
                                searchValue: (row) => String((row as TermOption).months ?? ''),
                                cell: (row) => (
                                    <span className="inline-flex items-center gap-1.5">
                                        <Icon icon={CalendarDaysIcon} />
                                        {(row as TermOption).months
                                            ? `${(row as TermOption).months} ${(row as TermOption).months === 1 ? 'Month' : 'Months'}`
                                            : '—'}
                                    </span>
                                ),
                            },
                        ]}
                    />

                    <DataTable
                        data={addonTable}
                        className={addonTable.length > 5 ? 'max-h-90 overflow-y-auto' : undefined}
                        getRowId={(row) => String(row.id)}
                        search={addonSearch}
                        onSearchChange={(value) => {
                            setAddonSearch(value);
                            if (addonDebounce.current) {
                                window.clearTimeout(addonDebounce.current);
                            }
                            addonDebounce.current = window.setTimeout(() => {
                                visitIndex({
                                    ...filters,
                                    addon_search: value,
                                });
                            }, 300);
                        }}
                        searchPlaceholder={t('packages.addons.search_placeholder')}
                        sort={filters.sort}
                        direction={filters.direction}
                        onSort={(column) => {
                            const nextDirection =
                                filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
                            visitIndex({
                                ...filters,
                                sort: column,
                                direction: nextDirection,
                            });
                        }}
                        onCreate={() =>
                            setReferenceForm({
                                kind: 'addon',
                                item: null,
                            })
                        }
                        createLabel={t('packages.addons.create')}
                        onBulkDelete={
                            canDelete ? (ids) => visitBulkDelete('/addons/bulk-destroy', ids.map(Number)) : undefined
                        }
                        bulkDeleteTitle={t('addons.bulk_delete_title')}
                        actions={(row) => (
                            <>
                                {can('addons.update') ? (
                                    <TableActionButton
                                        label={t('common.edit')}
                                        icon={SquarePenIcon}
                                        tone="edit"
                                        onClick={(event) => {
                                            event.stopPropagation();

                                            setReferenceForm({
                                                kind: 'addon',
                                                item: row as ReferenceFormRow,
                                            });
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
                                            setReferenceDelete({ kind: 'addon', id: row.id });
                                        }}
                                    />
                                ) : null}
                            </>
                        )}
                        columns={[
                            {
                                id: 'addon',
                                header: t('packages.addon'),
                                className: 'font-medium',
                                mobile: 'title',
                                sortable: true,
                                searchValue: (row) =>
                                    String(
                                        locale === 'en'
                                            ? (row.name_en ?? '')
                                            : locale === 'zh'
                                              ? (row.name_zh ?? '')
                                              : (row.name_my ?? ''),
                                    ),
                                cell: (row) => (
                                    <span className="inline-flex items-center gap-1.5">
                                        <Icon icon={PuzzleIcon} />
                                        {truncateText((row as AddonOption)[`name_${locale}`] ?? '—', 30)}
                                    </span>
                                ),
                            },
                        ]}
                    />
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
                        <FormControl compact className="w-full shrink-0 sm:w-90">
                            <MultiSelect
                                values={statusFilters}
                                options={[
                                    { value: 'active', label: t('status.active') },
                                    { value: 'inactive', label: t('status.inactive') },
                                    { value: 'recommended', label: t('packages.recommended') },
                                ]}
                                placeholder={t('common.status')}
                                onChange={(values) => {
                                    setStatusFilters(values);
                                    visitIndex({
                                        ...filters,
                                        status: '',
                                        recommended: '',
                                        status_filters: values,
                                    });
                                }}
                            />
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
                                    {truncateText(
                                        locale === 'en'
                                            ? (row.network?.name_en ?? '—')
                                            : locale === 'zh'
                                              ? (row.network?.name_zh ?? '—')
                                              : (row.network?.name_my ?? '—'),
                                        50,
                                    )}
                                </span>
                            ),
                        },
                        {
                            id: 'speed',
                            header: t('packages.speed'),
                            mobile: 'meta',
                            sortable: true,
                            cell: (row) => <span>{row.speed?.mbps ?? '—'} Mbps</span>,
                        },

                        {
                            id: 'term',
                            header: t('packages.term'),
                            mobile: 'meta',
                            sortable: true,
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
                        {
                            id: 'recommended',
                            header: t('packages.recommended'),
                            cell: (row) => (
                                <StarIcon
                                    className={`size-4 ${
                                        row.recommended ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'
                                    }`}
                                    strokeWidth={1.8}
                                />
                            ),
                        },
                    ]}
                />
            </PageContent>

            <PackageFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                package={editingPackage}
                networks={networks}
                speeds={speeds}
                terms={terms}
            />

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
