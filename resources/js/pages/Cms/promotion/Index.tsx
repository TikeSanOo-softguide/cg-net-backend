import { useState } from 'react';
import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/shared/CmsIndexPage';
import { PromotionFormDialog, type PromotionItem } from '@/components/cms/promotion/PromotionFormDialog';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDateTime } from '@/lib/utils';

type Props = {
    items: Paginated<PromotionItem>;
    filters: CmsFilters;
};

export default function PromotionsIndex({ items, filters }: Props) {
    const { t, locale } = useTranslation();
    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<PromotionItem | null>(null);

    const getLabel = (row: PromotionItem): string => {
        switch (locale) {
            case 'my':
                return row.title_my ?? row.title_en ?? '';

            case 'zh':
                return row.title_zh ?? row.title_en ?? '';

            default:
                return row.title_en ?? '';
        }
    };

    function isExpired(value: string | null | undefined): boolean {
        if (!value) {
            return false;
        }

        const [year, month, day] = value.split('-').map(Number);
        const endDate = new Date(year, month - 1, day);
        const today = new Date();

        today.setHours(0, 0, 0, 0);

        return endDate < today;
    }

    return (
        <>
            <Head title={t('menu.cms_promotions')} />
            <CmsIndexPage
                createLabelKey="cms.create_promotion"
                indexHref="/cms/promotions"
                destroyBase="/cms/promotions"
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
                    <PromotionFormDialog
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
                        header: t('cms.image'),
                        mobile: 'image',
                        className: 'font-medium',
                        cell: (row) => {
                            const imageUrl = row.image_url;
                            return (
                                <span className="inline-flex items-center justify-content-center">
                                    {imageUrl ? (
                                        <img src={imageUrl} alt="" className="size-8 rounded object-cover" />
                                    ) : null}
                                </span>
                            );
                        },
                    },
                    {
                        id: 'title',
                        header: t('cms.title'),
                        mobile: 'title',
                        sortable: true,
                        className: 'font-medium',
                        cell: (row) => {
                            return getLabel(row);
                        },
                    },
                    {
                        id: 'start_date',
                        header: t('cms.start_date'),
                        sortable: true,
                        mobile: 'meta',
                        cell: (row) => formatDateTime(row.start_date),
                    },
                    {
                        id: 'end_date',
                        header: t('cms.end_date'),
                        sortable: true,
                        cell: (row) => formatDateTime(row.end_date) ?? '—',
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
                ]}
            />
        </>
    );
}
