import { useState } from 'react';
import { Head } from '@inertiajs/react';

import { CategoryFormDialog, type CategoryItem } from '@/components/cms/category/CategoryFormDialog';
import { CmsIndexPage, type CmsFilters } from '@/components/cms/shared/CmsIndexPage';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    items: Paginated<CategoryItem & { news_count: number; created_at: string | null }>;
    filters: CmsFilters;
};

export default function CategoriesIndex({ items, filters }: Props) {
    const { t, locale } = useTranslation();
    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<CategoryItem | null>(null);

    return (
        <>
            <Head title={t('menu.cms_categories')} />
            <CmsIndexPage
                createLabelKey="cms.category.create"
                indexHref="/cms/categories"
                destroyBase="/cms/categories"
                items={items}
                filters={filters}
                onCreate={() => {
                    setEditingItem(null);
                    setFormOpen(true);
                }}
                onEdit={(row) => {
                    setEditingItem({
                        id: row.id,
                        name_en: row.name_en,
                        name_zh: row.name_zh,
                        name_my: row.name_my,
                        slug: row.slug,
                    });
                    setFormOpen(true);
                }}
                formDialog={
                    <CategoryFormDialog
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
                        id: 'name_en',
                        header: t('cms.category.label'),
                        mobile: 'title',
                        sortable: true,
                        className: 'font-medium',
                        cell: (row) =>
                            locale === 'zh'
                                ? row.name_zh || row.name_en
                                : locale === 'my'
                                  ? row.name_my || row.name_en
                                  : row.name_en,
                    },
                    { id: 'slug', header: t('cms.slug'), mobile: 'subtitle', sortable: true, cell: (row) => row.slug },
                    {
                        id: 'news_count',
                        header: t('cms.category.news_count'),
                        mobile: 'meta',
                        cell: (row) => row.news_count,
                    },
                ]}
            />
        </>
    );
}
