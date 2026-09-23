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
import { AppWindow, ImageIcon, LucideIcon, Monitor, Smartphone } from 'lucide-react';

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
                            className="relative flex"
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
