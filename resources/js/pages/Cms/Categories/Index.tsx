import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/CmsIndexPage';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';

type CategoryRow = {
    id: number;
    name: string;
    slug: string;
    news_count: number;
    created_at: string | null;
};

type Props = {
    items: Paginated<CategoryRow>;
    filters: CmsFilters;
};

export default function CategoriesIndex({ items, filters }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.cms_categories')} />
            <CmsIndexPage
                titleKey="menu.cms_categories"
                descriptionKey="cms.categories_description"
                createHref="/cms/categories/create"
                createLabelKey="cms.create_category"
                indexHref="/cms/categories"
                destroyBase="/cms/categories"
                items={items}
                filters={filters}
                columns={[
                    { id: 'name', header: t('cms.name'), mobile: 'title', sortable: true, className: 'font-medium', cell: (row) => row.name },
                    { id: 'slug', header: t('cms.slug'), mobile: 'subtitle', sortable: true, className: 'font-mono text-[12px]', cell: (row) => row.slug },
                ]}
            />
        </>
    );
}
