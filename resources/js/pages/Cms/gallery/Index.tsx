import { useState } from 'react';
import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/shared/CmsIndexPage';
import { GalleryFormDialog, type GalleryItem } from '@/components/cms/gallery/GalleryFormDialog';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDateTime } from '@/lib/utils';

type Props = {
    items: Paginated<GalleryItem & { created_at: string | null }>;
    filters: CmsFilters;
};

export default function GalleryIndex({ items, filters }: Props) {
    const { t, locale } = useTranslation();

    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<GalleryItem | null>(null);

    const getLabel = (row: GalleryItem): string => {
        switch (locale) {
            case 'my':
                return row.label_my ?? row.label_en ?? '';

            case 'zh':
                return row.label_zh ?? row.label_en ?? '';

            default:
                return row.label_en ?? '';
        }
    };

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
                    setEditingItem({
                        id: row.id,
                        label_en: row.label_en,
                        label_my: row.label_my,
                        label_zh: row.label_zh,
                        image_url: row.image_url,
                    });

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
                        id: 'label',
                        header: t('cms.label'),
                        mobile: 'title',
                        sortable: true,
                        className: 'font-medium',
                        cell: (row) => {
                            return getLabel(row);
                        },
                    },
                    {
                        id: 'created_at',
                        header: t('common.created_at'),
                        sortable: true,
                        mobile: 'meta',
                        className: 'text-muted-foreground',
                        cell: (row) => formatDateTime(row.created_at),
                    },
                ]}
            />
        </>
    );
}
