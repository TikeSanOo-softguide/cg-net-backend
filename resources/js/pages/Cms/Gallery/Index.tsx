import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/CmsIndexPage';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';

type GalleryRow = {
    id: number;
    label: string | null;
    lang: string;
    image_url: string | null;
    created_at: string | null;
};

type Props = {
    items: Paginated<GalleryRow>;
    filters: CmsFilters;
};

export default function GalleryIndex({ items, filters }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.cms_gallery')} />
            <CmsIndexPage
                titleKey="menu.cms_gallery"
                descriptionKey="cms.gallery_description"
                createHref="/cms/gallery/create"
                createLabelKey="cms.create_gallery"
                indexHref="/cms/gallery"
                destroyBase="/cms/gallery"
                items={items}
                filters={filters}
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
                                {row.label ?? '—'}
                            </span>
                        ),
                    },
                    { id: 'lang', header: t('common.language'), sortable: true, mobile: 'subtitle', cell: (row) => t(`language.${row.lang}`) },
                    { id: 'created_at', header: t('customers.joined'), sortable: true, mobile: 'meta', className: 'text-muted-foreground', cell: (row) => row.created_at },
                ]}
            />
        </>
    );
}
