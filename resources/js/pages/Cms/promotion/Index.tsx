import { useState } from 'react';
import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/shared/CmsIndexPage';
import { PromotionFormDialog, type PromotionItem } from '@/components/cms/promotion/PromotionFormDialog';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    items: Paginated<PromotionItem>;
    filters: CmsFilters;
};

export default function PromotionsIndex({ items, filters }: Props) {
    const { t } = useTranslation();
    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<PromotionItem | null>(null);

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
                        id: 'title',
                        header: t('cms.title'),
                        mobile: 'title',
                        sortable: true,
                        className: 'font-medium',
                        cell: (row) => (
                            <span className="inline-flex items-center gap-2">
                                {row.image_url ? <img src={row.image_url} alt="" className="size-8 rounded object-cover" /> : null}
                                {row.title}
                            </span>
                        ),
                    },
                    { id: 'start_date', header: t('cms.start_date'), sortable: true, mobile: 'meta', cell: (row) => row.start_date ?? '—' },
                    { id: 'end_date', header: t('cms.end_date'), sortable: true, cell: (row) => row.end_date ?? '—' },
                    { id: 'is_active', header: t('common.status'), sortable: true, mobile: 'badge', cell: (row) => <StatusBadge status={row.is_active ? 'active' : 'inactive'} /> },
                ]}
            />
        </>
    );
}
