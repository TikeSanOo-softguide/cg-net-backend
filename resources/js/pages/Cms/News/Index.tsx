import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/CmsIndexPage';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';

type NewsRow = {
    id: number;
    title: string;
    category_name: string | null;
    status: string;
    lang: string;
    tag_names: string[];
    created_at: string | null;
};

type Props = {
    items: Paginated<NewsRow>;
    filters: CmsFilters;
};

export default function NewsIndex({ items, filters }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.cms_news')} />
            <CmsIndexPage
                titleKey="menu.cms_news"
                descriptionKey="cms.news_description"
                createHref="/cms/news/create"
                createLabelKey="cms.create_news"
                indexHref="/cms/news"
                destroyBase="/cms/news"
                items={items}
                filters={filters}
                statusFilter="news"
                columns={[
                    { id: 'title', header: t('cms.title'), mobile: 'title', sortable: true, className: 'font-medium', cell: (row) => row.title },
                    { id: 'category_name', header: t('cms.category'), mobile: 'subtitle', cell: (row) => row.category_name ?? '—' },
                    { id: 'status', header: t('common.status'), sortable: true, mobile: 'badge', cell: (row) => <StatusBadge status={row.status} /> },
                    { id: 'lang', header: t('common.language'), sortable: true, cell: (row) => t(`language.${row.lang}`) },
                    { id: 'created_at', header: t('customers.joined'), sortable: true, mobile: 'meta', className: 'text-muted-foreground', cell: (row) => row.created_at },
                ]}
            />
        </>
    );
}
