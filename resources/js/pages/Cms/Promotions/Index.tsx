import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/CmsIndexPage';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';

type PromotionRow = {
    id: number;
    title: string;
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
    lang: string;
    image_url: string | null;
    created_at: string | null;
};

type Props = {
    items: Paginated<PromotionRow>;
    filters: CmsFilters;
};

export default function PromotionsIndex({ items, filters }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.cms_promotions')} />
            <CmsIndexPage
                titleKey="menu.cms_promotions"
                descriptionKey="cms.promotions_description"
                createHref="/cms/promotions/create"
                createLabelKey="cms.create_promotion"
                indexHref="/cms/promotions"
                destroyBase="/cms/promotions"
                items={items}
                filters={filters}
                statusFilter="active"
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
                    { id: 'start_date', header: t('cms.start_date'), sortable: true, mobile: 'meta', cell: (row) => row.start_date ?? '—' },
                    { id: 'end_date', header: t('cms.end_date'), sortable: true, cell: (row) => row.end_date ?? '—' },
                    { id: 'is_active', header: t('common.status'), sortable: true, mobile: 'badge', cell: (row) => <StatusBadge status={row.is_active ? 'active' : 'inactive'} /> },
                    { id: 'lang', header: t('common.language'), sortable: true, mobile: 'subtitle', cell: (row) => t(`language.${row.lang}`) },
                ]}
            />
        </>
    );
}
