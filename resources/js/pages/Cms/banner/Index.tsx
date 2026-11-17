import { useState } from 'react';
import { Head, router } from '@inertiajs/react';

import { BannerFormDialog, type BannerItem } from '@/components/cms/banner/BannerFormDialog';
import { CmsIndexPage, type CmsFilters } from '@/components/cms/shared/CmsIndexPage';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDate, todayInTimezone } from '@/lib/utils';
import { MultiSelect, MultiSelectOption } from '@/components/MultiSelect';
import { FormField } from '@/components/ui/form-field';
import { AppWindow, ImageIcon, LucideIcon, Monitor, Smartphone } from 'lucide-react';

type Props = {
    items: Paginated<BannerItem>;
    filters: CmsFilters;
    bannerTypes: {
        value: string;
        label: string;
    }[];
    bannerTypeCounts: Partial<Record<'web_background' | 'web_popup' | 'app_entry' | 'app_popup', number>>;
};

export default function BannersIndex({ items, filters, bannerTypes, bannerTypeCounts }: Props) {
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

        return endDate < todayInTimezone();
    };

    const bannerTypeIcons: Record<string, LucideIcon> = {
        web_background: Monitor,
        web_popup: ImageIcon,
        app_entry: Smartphone,
        app_popup: AppWindow,
    };

    const bannerTypeOptions: MultiSelectOption[] = bannerTypes.map((type) => ({
        value: type.value,
        label: t(`cms.banner.types.${type.value}`),
        icon: bannerTypeIcons[type.value],
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
                    <FormField
                        label={t('cms.banner.type')}
                        htmlFor="banner-types"
                        className="mr-3 w-full shrink-0 sm:w-64"
                        labelClassName="text-[13px]"
                    >
                        <MultiSelect
                            id="banner-types"
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
                            className="relative flex"
                        />
                    </FormField>
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
                        bannerTypeCounts={bannerTypeCounts}
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
                                <span className="block w-[220px] p-1">
                                    {imageUrl ? (
                                        <img
                                            src={imageUrl}
                                            alt=""
                                            width={220}
                                            style={{ aspectRatio: '925 / 390' }}
                                            className="h-auto w-[220px] rounded object-cover"
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
                        id: 'sort_order',
                        header: t('cms.sort_order'),
                        sortable: true,
                        cell: (row) => row.sort_order ?? '—',
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
