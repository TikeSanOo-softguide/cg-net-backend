import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/CmsIndexPage';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';

type BannerRow = {
    id: number;
    sort_order: number;
    is_active: boolean;
    start_date: string | null;
    end_date: string | null;
    image_url_en: string | null;
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
                        id: 'sort_order',
                        header: t('cms.sort_order'),
                        mobile: 'title',
                        sortable: true,
                        className: 'font-medium',
                        cell: (row) => (
                            <span className="inline-flex items-center gap-2">
                                {row.image_url_en ? <img src={row.image_url_en} alt="" className="size-8 rounded object-cover" /> : null}
                                {row.sort_order}
                            </span>
                        ),
                    },
                    { id: 'is_active', header: t('common.status'), sortable: true, mobile: 'badge', cell: (row) => <StatusBadge status={row.is_active ? 'active' : 'inactive'} /> },
                ]}
            />
        </>
    );
}
