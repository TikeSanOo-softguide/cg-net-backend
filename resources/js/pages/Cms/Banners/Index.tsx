import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/CmsIndexPage';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';

type BannerRow = {
    id: number;
    title: string;
    sort_order: number;
    is_active: boolean;
    lang: string;
    start_date: string | null;
    end_date: string | null;
    image_url: string | null;
};

type Props = {
    items: Paginated<BannerRow>;
    filters: CmsFilters;
};

export default function BannersIndex({ items, filters }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.cms_banners')} />
            <CmsIndexPage
                titleKey="menu.cms_banners"
                descriptionKey="cms.banners_description"
                createHref="/cms/banners/create"
                createLabelKey="cms.create_banner"
                indexHref="/cms/banners"
                destroyBase="/cms/banners"
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
                    { id: 'sort_order', header: t('cms.sort_order'), sortable: true, cell: (row) => row.sort_order },
                    { id: 'is_active', header: t('common.status'), sortable: true, mobile: 'badge', cell: (row) => <StatusBadge status={row.is_active ? 'active' : 'inactive'} /> },
                    { id: 'lang', header: t('common.language'), sortable: true, mobile: 'subtitle', cell: (row) => t(`language.${row.lang}`) },
                ]}
            />
        </>
    );
}
