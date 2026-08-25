import { useState } from 'react';
import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/shared/CmsIndexPage';
import { GalleryFormDialog, type GalleryItem } from '@/components/cms/gallery/GalleryFormDialog';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    items: Paginated<GalleryItem & { created_at: string | null }>;
    filters: CmsFilters;
};

export default function GalleryIndex({ items, filters }: Props) {
    const { t } = useTranslation();
    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<GalleryItem | null>(null);

    return (
        <>
            <Head title={t('menu.cms_gallery')} />
            <CmsIndexPage
                createLabelKey="cms.create_gallery"
                indexHref="/cms/gallery"
                destroyBase="/cms/gallery"
                items={items}
                filters={filters}
                onCreate={() => {
                    setEditingItem(null);
                    setFormOpen(true);
                }}
                onEdit={(row) => {
                    setEditingItem({ id: row.id, label: row.label, image_url: row.image_url });
                    setFormOpen(true);
                }}
                formDialog={
                    <GalleryFormDialog
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
                        id: 'label',
                        header: t('cms.label'),
                        mobile: 'title',
                        sortable: true,
                        className: 'font-medium',
                        cell: (row) => (
                            <span className="inline-flex items-center gap-2">
                                {row.image_url ? <img src={row.image_url} alt="" className="size-8 rounded object-cover" /> : null}
                                {row.label}
                            </span>
                        ),
                    },
                    {
                        id: 'created_at',
                        header: t('customers.joined'),
                        sortable: true,
                        mobile: 'meta',
                        className: 'text-muted-foreground',
                        cell: (row) => row.created_at,
                    },
                ]}
            />
        </>
    );
}
