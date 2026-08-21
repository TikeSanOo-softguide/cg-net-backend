import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/CmsIndexPage';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';

type NameSlugRow = {
    id: number;
    name: string;
    slug: string;
    lang: string;
    created_at: string | null;
};

type Props = {
    items: Paginated<NameSlugRow>;
    filters: CmsFilters;
};

export default function TagsIndex({ items, filters }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.cms_tags')} />
            <CmsIndexPage
                titleKey="menu.cms_tags"
                descriptionKey="cms.tags_description"
                createHref="/cms/tags/create"
                createLabelKey="cms.create_tag"
                indexHref="/cms/tags"
                destroyBase="/cms/tags"
                items={items}
                filters={filters}
                columns={[
                    { id: 'name', header: t('cms.name'), mobile: 'title', sortable: true, className: 'font-medium', cell: (row) => row.name },
                    { id: 'slug', header: t('cms.slug'), mobile: 'subtitle', sortable: true, className: 'font-mono text-[12px]', cell: (row) => row.slug },
                    { id: 'lang', header: t('common.language'), sortable: true, mobile: 'meta', cell: (row) => t(`language.${row.lang}`) },
                ]}
            />
        </>
    );
}
