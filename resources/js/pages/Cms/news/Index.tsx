import { useState } from 'react';
import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/shared/CmsIndexPage';
import { NewsFormDialog, type NewsItem } from '@/components/cms/news/NewsFormDialog';
import type { NewsOption } from '@/components/cms/news/NewsForm';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDateTime } from '@/lib/utils';

type Props = {
    items: Paginated<
        NewsItem & {
            category_name_en: string;
            category_name_zh: string;
            category_name_my: string;
            created_at: string | null;
            updated_at: string | null;
        }
    >;
    filters: CmsFilters;
    categories: NewsOption[];
};

export default function NewsIndex({ items, filters, categories }: Props) {
    const { t, locale } = useTranslation();
    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<NewsItem | null>(null);

    return (
        <>
            <Head title={t('menu.cms_news')} />
            <CmsIndexPage
                createLabelKey="cms.news.create"
                indexHref="/cms/news"
                destroyBase="/cms/news"
                items={items}
                filters={filters}
                statusFilter="news"
                onCreate={() => {
                    setEditingItem(null);
                    setFormOpen(true);
                }}
                onEdit={(row) => {
                    setEditingItem({
                        id: row.id,
                        category_id: row.category_id,
                        title_en: row.title_en,
                        title_zh: row.title_zh,
                        title_my: row.title_my,
                        description_en: row.description_en,
                        description_zh: row.description_zh,
                        description_my: row.description_my,
                        slug: row.slug,
                        status: row.status,
                        image_url: row.image_url,
                        created_at: row.created_at,
                        updated_at: row.updated_at,
                    });
                    setFormOpen(true);
                }}
                formDialog={
                    <NewsFormDialog
                        open={formOpen}
                        onOpenChange={(open) => {
                            setFormOpen(open);

                            if (!open) {
                                setEditingItem(null);
                            }
                        }}
                        item={editingItem}
                        categories={categories}
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
                        header: t('cms.news.label'),
                        mobile: 'title',
                        className: 'font-medium',
                        cell: (row) =>
                            locale === 'zh'
                                ? row.title_zh || row.title_en
                                : locale === 'my'
                                    ? row.title_my || row.title_en
                                    : row.title_en,
                    },
                    {
                        id: 'category_name',
                        header: t('cms.category.label'),
                        mobile: 'subtitle',
                        cell: (row) =>
                            locale === 'zh'
                                ? row.category_name_zh || row.category_name_en
                                : locale === 'my'
                                    ? row.category_name_my || row.category_name_en
                                    : row.category_name_en,
                    },
                    {
                        id: 'status',
                        header: t('common.status'),
                        mobile: 'badge',
                        cell: (row) => <StatusBadge status={row.status} />,
                    },
                    {
                        id: 'created_at',
                        header: t('common.created_at'),
                        mobile: 'meta',
                        className: 'text-muted-foreground',
                        cell: (row) => formatDateTime(row.created_at),
                    },
                    {
                        id: 'updated_at',
                        header: t('common.updated_at'),
                        mobile: 'meta',
                        className: 'text-muted-foreground',
                        cell: (row) => formatDateTime(row.updated_at),
                    },
                ]}
            />
        </>
    );
}
