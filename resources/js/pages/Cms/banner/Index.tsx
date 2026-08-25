import { useState } from 'react';
import { Head } from '@inertiajs/react';

import { BannerFormDialog, type BannerItem } from '@/components/cms/banner/BannerFormDialog';
import { CmsIndexPage, type CmsFilters } from '@/components/cms/shared/CmsIndexPage';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    items: Paginated<BannerItem>;
    filters: CmsFilters;
};

export default function BannersIndex({ items, filters }: Props) {
    const { t } = useTranslation();
    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<BannerItem | null>(null);

    return (
        <>
            <Head title={t('menu.cms_banners')} />
            <CmsIndexPage
                createLabelKey="cms.create_banner"
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
                    { id: 'sort_order', header: t('cms.sort_order'), sortable: true, cell: (row) => row.sort_order },
                    { id: 'is_active', header: t('common.status'), sortable: true, mobile: 'badge', cell: (row) => <StatusBadge status={row.is_active ? 'active' : 'inactive'} /> },
                ]}
            />
        </>
    );
}
