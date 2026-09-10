import { useState } from 'react';
import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/shared/CmsIndexPage';
import { ServiceFormDialog, type ServiceItem } from '@/components/cms/service/ServiceFormDialog';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDateTime, truncateText } from '@/lib/utils';

type Props = {
    items: Paginated<
        ServiceItem & {
            created_at: string | null;
            updated_at: string | null;
        }
    >;
    filters: CmsFilters;
};

export default function ServiceIndex({ items, filters }: Props) {
    const { t, locale } = useTranslation();
    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<ServiceItem | null>(null);

    return (
        <>
            <Head title={t('menu.cms_services')} />
            <CmsIndexPage
                createLabelKey="cms.service.create"
                indexHref="/cms/services"
                destroyBase="/cms/services"
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
                    <ServiceFormDialog
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
                        id: 'image',
                        header: t('cms.image'),
                        mobile: 'image',
                        className: 'font-medium',
                        cell: (row) => {
                            const imageUrl = row.image_url;
                            return (
                                <span className="inline-flex items-center justify-center">
                                    {imageUrl ? (
                                        <img
                                            src={imageUrl}
                                            alt=""
                                            width={220}
                                            height={90}
                                            className="size-8 h-[90px] w-[220px] rounded object-cover"
                                        />
                                    ) : null}
                                </span>
                            );
                        },
                    },
                    {
                        id: 'title',
                        header: t('cms.service.label'),
                        mobile: 'title',
                        className: 'font-medium',
                        cell: (row) => {
                            const title =
                                    locale === 'zh'
                                        ? row.title_zh || row.title_en
                                        : locale === 'my'
                                          ? row.title_my || row.title_en
                                          : row.title_en,
                                displayTitle = truncateText(title, 65);

                            return (
                                <span className="block max-w-full truncate" title={title}>
                                    {displayTitle}
                                </span>
                            );
                        },
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
