import { useState } from 'react';
import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/shared/CmsIndexPage';
import { ContactFormDialog, type ContactItem } from '@/components/cms/contact/ContactFormDialog';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';
import { truncateText } from '@/lib/utils';

type Props = {
    items: Paginated<ContactItem & { created_at: string | null; updated_at: string | null }>;
    filters: CmsFilters;
};

export default function ContactsIndex({ items, filters }: Props) {
    const { t } = useTranslation();
    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<ContactItem | null>(null);

    return (
        <>
            <Head title={t('menu.cms_contacts')} />
            <CmsIndexPage
                createLabelKey="cms.contact.create"
                searchPlaceholderKey="cms.contact.search"
                indexHref="/cms/contacts"
                destroyBase="/cms/contacts"
                items={items}
                filters={filters}
                onCreate={() => {
                    setEditingItem(null);
                    setFormOpen(true);
                }}
                onEdit={(row) => {
                    setEditingItem({
                        id: row.id,
                        contact_point: row.contact_point,
                        created_at: row.created_at,
                        updated_at: row.updated_at,
                    });
                    setFormOpen(true);
                }}
                formDialog={
                    <ContactFormDialog
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
                        id: 'contact_point',
                        header: t('cms.contact_point'),
                        mobile: 'title',
                        className: 'font-medium',
                        cell: (row) => truncateText(row.contact_point, 60),
                    },
                ]}
            />
        </>
    );
}
