import { useState } from 'react';
import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/shared/CmsIndexPage';
import { NewsFormDialog, type NewsItem } from '@/components/cms/news/NewsFormDialog';
import type { NewsOption } from '@/components/cms/news/NewsForm';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    items: Paginated<NewsItem & { category_name: string | null; created_at: string | null }>;
    filters: CmsFilters;
    categories: NewsOption[];
};

export default function NewsIndex({ items, filters, categories }: Props) {
    const { t } = useTranslation();
    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<NewsItem | null>(null);

    return (
        <>
            <Head title={t('menu.cms_news')} />
            <CmsIndexPage
                createLabelKey="cms.create_news"
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
                        title: row.title,
                        slug: row.slug,
                        content: row.content,
                        status: row.status,
                        image_url: row.image_url,
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
                    { id: 'title', header: t('cms.title'), mobile: 'title', sortable: true, className: 'font-medium', cell: (row) => row.title },
                    { id: 'category_name', header: t('cms.category'), mobile: 'subtitle', cell: (row) => row.category_name ?? '—' },
                    { id: 'status', header: t('common.status'), sortable: true, mobile: 'badge', cell: (row) => <StatusBadge status={row.status} /> },
                    { id: 'created_at', header: t('customers.joined'), sortable: true, mobile: 'meta', className: 'text-muted-foreground', cell: (row) => row.created_at },
                ]}
            />
        </>
    );
}
