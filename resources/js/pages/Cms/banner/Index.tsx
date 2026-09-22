import { useState } from 'react';
import { Head, router } from '@inertiajs/react';

import { BannerFormDialog, type BannerItem } from '@/components/cms/banner/BannerFormDialog';
import { CmsIndexPage, type CmsFilters } from '@/components/cms/shared/CmsIndexPage';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDate } from '@/lib/utils';
import { MultiSelect, MultiSelectOption } from '@/components/MultiSelect';
import { FormControl } from '@/components/ui/form-control';

type Props = {
    items: Paginated<BannerItem>;
    filters: CmsFilters;
    bannerTypes: {
        value: string;
        label: string;
    }[];
};

export default function BannersIndex({ items, filters, bannerTypes }: Props) {
    const { t, locale } = useTranslation();
    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<BannerItem | null>(null);
    const imageLabels = {
        en: t('cms.banner.image_en'),
        zh: t('cms.banner.image_zh'),
        my: t('cms.banner.image_my'),
    };

    const isExpired = (endDate: string | null) => {
        if (!endDate) return false;

        return new Date(endDate) < new Date();
    };

    const bannerTypeOptions: MultiSelectOption[] = bannerTypes.map((type) => ({
        value: type.value,
        label: t(`cms.banner.types.${type.value}`),
    }));

    return (
        <>
            <Head title={t('menu.cms_banners')} />
            <CmsIndexPage
                createLabelKey="cms.banner.create"
                indexHref="/cms/banners"
                destroyBase="/cms/banners"
                items={items}
                filters={filters}
                statusFilter="active"
                showSearch={false}
                extraFilters={
                    <FormControl compact className="w-[700px] min-w-[700px] max-w-[900px] shrink-0">
                        <MultiSelect
                            values={filters.types ?? []}
                            options={bannerTypeOptions}
                            onChange={(types) => {
                                router.get(
                                    '/cms/banners',
                                    {
                                        status: filters.status || undefined,
                                        types: types.length ? types : undefined,
                                        sort: filters.sort,
                                        direction: filters.direction,
                                    },
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                    },
                                );
                            }}
                            placeholder={t('cms.banner.type')}
                            heading={t('cms.banner.type')}
                            className="relative flex min-h-10 w-[700px] min-w-[700px] max-w-[900px] h-auto items-center text-left border-none flex-wrap gap-1.5 p-2"
                        />
                    </FormControl>
                }
                onCreate={() => {
                    setEditingItem(null);
                    setFormOpen(true);
                }}
                onEdit={(row) => {
                    setEditingItem(row);
                    setFormOpen(true);
                }}
                formDialog={
                    <BannerFormDialog
                        open={formOpen}
                        onOpenChange={(open) => {
                            setFormOpen(open);

                            if (!open) {
                                setEditingItem(null);
                            }
                        }}
                        item={editingItem}
                    />
                }
                columns={[
                    {
                        id: 'image',
                        header: imageLabels[locale],
                        className: 'font-medium',
                        cell: (row) => {
                            const imageUrl =
                                locale === 'en'
                                    ? row.image_url_en
                                    : locale === 'zh'
                                      ? row.image_url_zh
                                      : row.image_url_my;

                            return (
                                <span className="block w-[200px] p-1">
                                    {imageUrl ? (
                                        <img
                                            src={imageUrl}
                                            alt=""
                                            width={200}
                                            height={50}
                                            className="h-[50px] w-[200px] rounded object-cover"
                                        />
                                    ) : null}
                                </span>
                            );
                        },
                    },
                    {
                        id: 'type',
                        header: t('cms.banner.type'),
                        cell: (row) => {
                            const bannerType = row.type;
                            return (
                                <span className="font-medium">
                                    {bannerType ? t(`cms.banner.types.${bannerType}`) : '—'}
                                </span>
                            );
                        },
                    },
                    {
                        id: 'is_active',
                        header: t('common.status'),
                        mobile: 'badge',
                        cell: (row) => {
                            const expired = isExpired(row.end_date);
                            return <StatusBadge status={expired ? 'expired' : row.is_active ? 'active' : 'inactive'} />;
                        },
                    },
                    {
                        id: 'start_date',
                        header: t('cms.start_date'),
                        sortable: true,
                        cell: (row) => formatDate(row.start_date) ?? '—',
                    },
                    {
                        id: 'end_date',
                        header: t('cms.end_date'),
                        sortable: true,
                        cell: (row) => formatDate(row.end_date) ?? '—',
                    },
                ]}
            />
        </>
    );
}
