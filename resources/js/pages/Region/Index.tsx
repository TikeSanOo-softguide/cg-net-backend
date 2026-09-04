import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { RegionDataTable, type RegionFilters } from '@/components/region/shared/RegionDataTable';
import { RegionFormDialog, type StateRow, type RegionRow, type AreaRow } from '@/components/region/RegionFormDialog';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';
import { truncateText } from '@/lib/utils';

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

    const getName = (row: StateRow | RegionRow | AreaRow): string => {
        switch (locale) {
            case 'my':
                return row.name_my ?? row.name_en ?? '';

            case 'zh':
                return row.name_zh ?? row.name_en ?? '';

            default:
                return row.name_en ?? '';
        }
    };

    const openCreate = (type: 'state' | 'region' | 'area') => {
        setFormTarget({
            type,
            item: null,
        } as FormTarget);
    };

    const openEdit = (type: 'state' | 'region' | 'area', item: StateRow | RegionRow | AreaRow) => {
        setFormTarget({
            type,
            item,
        } as FormTarget);
    };

    const closeForm = () => {
        setFormTarget(null);
    };

    return (
        <>
            <Head title={t('menu.regions')} />

            <PageContent>
                <PageHeader />
                <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-2">
                    <RegionDataTable
                        titleKey="menu.states"
                        createLabelKey="regions.create_state"
                        searchLabelKey="regions.search_state"
                        searchParam="state_search"
                        indexHref="/regions"
                        destroyBase="/regions/states"
                        items={states}
                        filters={stateFilters}
                        onCreate={() => openCreate('state')}
                        onEdit={(row) => openEdit('state', row)}
                        columns={[
                            {
                                id: 'title',
                                header: t('regions.state_name'),
                                mobile: 'title',
                                className: 'font-medium',
                                cell: (row) => truncateText(getName(row), 50),
                            },
                        ]}
                    />

                    <RegionDataTable
                        titleKey="menu.regions"
                        createLabelKey="regions.create_region"
                        searchLabelKey="regions.search_region"
                        searchParam="region_search"
                        indexHref="/regions"
                        destroyBase="/regions/regions"
                        items={regions}
                        filters={regionFilters}
                        onCreate={() => openCreate('region')}
                        onEdit={(row) => openEdit('region', row)}
                        columns={[
                            {
                                id: 'title',
                                header: t('regions.region_name'),
                                mobile: 'title',
                                className: 'font-medium',
                                cell: (row) => truncateText(getName(row), 50),
                            },
                            {
                                id: 'state',
                                header: t('regions.state_name'),
                                mobile: 'subtitle',
                                cell: (row) =>
                                    locale === 'my'
                                        ? truncateText(row.state_name_my ?? row.state_name_en ?? '-', 50)
                                        : locale === 'zh'
                                          ? truncateText(row.state_name_zh ?? row.state_name_en ?? '-', 50)
                                          : truncateText(row.state_name_en ?? '-', 50),
                            },
                        ]}
                    />
                </div>

                <RegionDataTable
                    titleKey="menu.areas"
                    createLabelKey="regions.create_area"
                    searchLabelKey="regions.search_area"
                    searchParam="area_search"
                    indexHref="/regions"
                    destroyBase="/regions/areas"
                    items={areas}
                    filters={areaFilters}
                    onCreate={() => openCreate('area')}
                    onEdit={(row) => openEdit('area', row)}
                    columns={[
                        {
                            id: 'title',
                            header: t('regions.area_name'),
                            mobile: 'title',
                            className: 'font-medium',
                            cell: (row) => truncateText(getName(row), 50),
                        },
                        {
                            id: 'region',
                            header: t('regions.region_name'),
                            mobile: 'subtitle',
                            cell: (row) =>
                                locale === 'my'
                                    ? truncateText(row.region_name_my ?? row.region_name_en ?? '-', 50)
                                    : locale === 'zh'
                                      ? truncateText(row.region_name_zh ?? row.region_name_en ?? '-', 50)
                                      : truncateText(row.region_name_en ?? '-', 50),
                        },
                        {
                            id: 'state',
                            header: t('regions.state_name'),
                            mobile: 'meta',
                            cell: (row) =>
                                locale === 'my'
                                    ? truncateText(row.state_name_my ?? row.state_name_en ?? '-', 50)
                                    : locale === 'zh'
                                      ? truncateText(row.state_name_zh ?? row.state_name_en ?? '-', 50)
                                      : truncateText(row.state_name_en ?? '-', 50),
                        },
                    ]}
                />
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
        </>
    );
}
