import { useState } from 'react';
import { Head } from '@inertiajs/react';

import { BannerFormDialog, type BannerItem } from '@/components/cms/banner/BannerFormDialog';
import { CmsIndexPage, type CmsFilters } from '@/components/cms/shared/CmsIndexPage';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDateTime } from '@/lib/utils';

type Props = {
    items: Paginated<BannerItem>;
    filters: CmsFilters;
};

export default function BannersIndex({ items, filters }: Props) {
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
                        sortable: true,
                        className: 'font-medium',
                        cell: (row) => {
                            const imageUrl =
                                locale === 'en'
                                    ? row.image_url_en
                                    : locale === 'zh'
                                      ? row.image_url_zh
                                      : row.image_url_my;

                            return (
                                <span className="inline-flex items-center p-2">
                                    {imageUrl ? (
                                        <img
                                            src={imageUrl}
                                            alt=""
                                            width={200}
                                            height={50}
                                            className="h-20 w-[200px] rounded object-cover"
                                        />
                                    ) : null}
                                </span>
                            );
                        },
                    },
                    {
                        id: 'is_active',
                        header: t('common.status'),
                        sortable: true,
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
                        cell: (row) => formatDateTime(row.start_date) ?? '—',
                    },
                    {
                        id: 'end_date',
                        header: t('cms.end_date'),
                        sortable: true,
                        cell: (row) => formatDateTime(row.end_date) ?? '—',
                    },
                ]}
            />
        </>
    );
}
