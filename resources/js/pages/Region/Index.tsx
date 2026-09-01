import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Trash2Icon } from 'lucide-react';

import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import {
    RegionDataTable,
    type RegionFilters,
} from '@/components/region/shared/RegionDataTable';
import {
    RegionFormDialog,
    type StateRow,
    type RegionRow,
    type AreaRow,
} from '@/components/region/RegionFormDialog';
import type { Paginated } from '@/components/Pagination';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { useTranslation } from '@/hooks/useTranslation';

type RegionIndexProps = {
    states: Paginated<StateRow>;
    stateFilters: RegionFilters;

    regions: Paginated<RegionRow>;
    regionFilters: RegionFilters;

    areas: Paginated<AreaRow>;
    areaFilters: RegionFilters;
};

type PendingDelete = {
    type: 'state' | 'region' | 'area';
    id: number;
    name: string;
};

type FormTarget =
    | {
          type: 'state';
          item: StateRow | null;
      }
    | {
          type: 'region';
          item: RegionRow | null;
      }
    | {
          type: 'area';
          item: AreaRow | null;
      }
    | null;

export default function RegionIndex({
    states,
    stateFilters,
    regions,
    regionFilters,
    areas,
    areaFilters,
}: RegionIndexProps) {
    const { t, locale } = useTranslation();

    const [formTarget, setFormTarget] = useState<FormTarget>(null);

    const [pendingDelete, setPendingDelete] =
        useState<PendingDelete | null>(null);

    const [isDeleting, setIsDeleting] = useState(false);

    const getName = (
        row:
            | StateRow
            | RegionRow
            | AreaRow,
    ): string => {
        switch (locale) {
            case 'my':
                return row.name_my ?? row.name_en ?? '';

            case 'zh':
                return row.name_zh ?? row.name_en ?? '';

            default:
                return row.name_en ?? '';
        }
    };

    const openCreate = (
        type: 'state' | 'region' | 'area',
    ) => {
        setFormTarget({
            type,
            item: null,
        } as FormTarget);
    };

    const openEdit = (
        type: 'state' | 'region' | 'area',
        item: StateRow | RegionRow | AreaRow,
    ) => {
        setFormTarget({
            type,
            item,
        } as FormTarget);
    };

    const closeForm = () => {
        setFormTarget(null);
    };

    const handleDelete = () => {
        if (!pendingDelete) {
            return;
        }

        const { type, id } = pendingDelete;

        const urlMap = {
            state: `/regions/states/${id}`,
            region: `/regions/regions/${id}`,
            area: `/regions/areas/${id}`,
        };

        setIsDeleting(true);

        router.delete(urlMap[type], {
            preserveScroll: true,

            onSuccess: () => {
                setPendingDelete(null);
            },

            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    return (
        <>
            <Head title={t('menu.regions')} />

            <PageContent>
                <PageHeader />
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <Card className="overflow-hidden">
                        <CardHeader className="border-b">
                            <div className="flex items-start gap-3">
                                <div>
                                    <CardTitle>
                                        {t('menu.states')}
                                    </CardTitle>

                                    <CardDescription>
                                        {t(
                                            'menu.states_description',
                                        )}
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent className="p-0">
                            <RegionDataTable
                                createLabelKey="regions.create_state"
                                searchLabelKey="regions.search_state"
                                indexHref="/regions"
                                destroyBase="/regions/states"
                                items={states}
                                filters={stateFilters}
                                onCreate={() =>
                                    openCreate('state')
                                }
                                onEdit={(row) =>
                                    openEdit('state', row)
                                }
                                columns={[
                                    {
                                        id: 'name',
                                        header: t(
                                            'regions.state_name',
                                        ),
                                        mobile: 'title',
                                        sortable: true,
                                        className:
                                            'font-medium',
                                        cell: (row) =>
                                            getName(row),
                                    },
                                    {
                                        id: 'created_at',
                                        header: t(
                                            'customers.joined',
                                        ),
                                        sortable: true,
                                        mobile: 'meta',
                                        className:
                                            'text-muted-foreground',
                                        cell: (row) =>
                                            row.created_at,
                                    },
                                ]}
                            />
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden">
                        <CardHeader className="border-b">
                            <div className="flex items-start gap-3">
                                <div>
                                    <CardTitle>
                                        {t('menu.regions')}
                                    </CardTitle>

                                    <CardDescription>
                                        {t(
                                            'menu.regions_description',
                                        )}
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent className="p-0">
                            <RegionDataTable
                                createLabelKey="regions.create_region"
                                searchLabelKey="regions.search_region"
                                indexHref="/regions"
                                destroyBase="/regions/regions"
                                items={regions}
                                filters={regionFilters}
                                onCreate={() =>
                                    openCreate('region')
                                }
                                onEdit={(row) =>
                                    openEdit('region', row)
                                }
                                columns={[
                                    {
                                        id: 'name',
                                        header: t(
                                            'regions.region_name',
                                        ),
                                        mobile: 'title',
                                        sortable: true,
                                        className:
                                            'font-medium',
                                        cell: (row) =>
                                            getName(row),
                                    },
                                    {
                                        id: 'state',
                                        header: t(
                                            'regions.state_name',
                                        ),
                                        mobile: 'subtitle',
                                        cell: (row) =>
                                            locale === 'my'
                                                ? row.state_name_my ??
                                                  row.state_name_en ??
                                                  '-'
                                                : locale === 'zh'
                                                  ? row.state_name_zh ??
                                                    row.state_name_en ??
                                                    '-'
                                                  : row.state_name_en ??
                                                    '-',
                                    },
                                    {
                                        id: 'created_at',
                                        header: t(
                                            'customers.joined',
                                        ),
                                        sortable: true,
                                        mobile: 'meta',
                                        className:
                                            'text-muted-foreground',
                                        cell: (row) =>
                                            row.created_at,
                                    },
                                ]}
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card className="overflow-hidden">
                    <CardHeader className="border-b">
                        <div className="flex items-center gap-3">
                            <div>
                                <CardTitle>
                                    {t('menu.areas')}
                                </CardTitle>

                                <CardDescription>
                                    {t('menu.areas_description')}
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        <RegionDataTable
                            createLabelKey="regions.create_area"
                            searchLabelKey="regions.search_area"
                            indexHref="/regions"
                            destroyBase="/regions/areas"
                            items={areas}
                            filters={areaFilters}
                            onCreate={() => openCreate('area')}
                            onEdit={(row) =>
                                openEdit('area', row)
                            }
                            columns={[
                                {
                                    id: 'name',
                                    header: t(
                                        'regions.area_name',
                                    ),
                                    mobile: 'title',
                                    sortable: true,
                                    className:
                                        'font-medium',
                                    cell: (row) =>
                                        getName(row),
                                },
                                {
                                    id: 'region',
                                    header: t(
                                        'regions.region_name',
                                    ),
                                    mobile: 'subtitle',
                                    cell: (row) =>
                                        locale === 'my'
                                            ? row.region_name_my ??
                                              row.region_name_en ??
                                              '-'
                                            : locale === 'zh'
                                              ? row.region_name_zh ??
                                                row.region_name_en ??
                                                '-'
                                              : row.region_name_en ??
                                                '-',
                                },
                                {
                                    id: 'state',
                                    header: t(
                                        'regions.state_name',
                                    ),
                                    mobile: 'meta',
                                    cell: (row) =>
                                        locale === 'my'
                                            ? row.state_name_my ??
                                              row.state_name_en ??
                                              '-'
                                            : locale === 'zh'
                                              ? row.state_name_zh ??
                                                row.state_name_en ??
                                                '-'
                                              : row.state_name_en ??
                                                '-',
                                },
                                {
                                    id: 'created_at',
                                    header: t(
                                        'customers.joined',
                                    ),
                                    sortable: true,
                                    mobile: 'meta',
                                    className:
                                        'text-muted-foreground',
                                    cell: (row) =>
                                        row.created_at,
                                },
                            ]}
                        />
                    </CardContent>
                </Card>
            </PageContent>

            {formTarget && (
                <RegionFormDialog
                    open={true}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeForm();
                        }
                    }}
                    type={formTarget.type}
                    item={formTarget.item}
                    states={states.data}
                    regions={regions.data}
                />
            )}

            {pendingDelete && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div
                        className="w-full max-w-md rounded-lg bg-background p-6 shadow-xl"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="delete-dialog-title"
                    >
                        <div className="flex items-start gap-4">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-destructive/10">
                                <Trash2Icon className="h-5 w-5 text-destructive" />
                            </div>

                            <div className="flex-1">
                                <h2
                                    id="delete-dialog-title"
                                    className="text-lg font-semibold"
                                >
                                    {t('common.delete')}
                                </h2>

                                <p className="mt-2 text-sm text-muted-foreground">
                                    Are you sure you want to delete{' '}
                                    <span className="font-medium text-foreground">
                                        {pendingDelete.name}
                                    </span>
                                    ?
                                </p>

                                <p className="mt-1 text-sm text-muted-foreground">
                                    This action cannot be undone.
                                </p>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                disabled={isDeleting}
                                onClick={() =>
                                    setPendingDelete(null)
                                }
                                className="rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted disabled:pointer-events-none disabled:opacity-50"
                            >
                                {t('common.cancel')}
                            </button>

                            <button
                                type="button"
                                disabled={isDeleting}
                                onClick={handleDelete}
                                className="rounded-md bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground hover:bg-destructive/90 disabled:pointer-events-none disabled:opacity-50"
                            >
                                {isDeleting
                                    ? 'Deleting...'
                                    : t('common.delete')}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}