import { Head } from '@inertiajs/react';

import { CmsIndexPage, type CmsFilters } from '@/components/cms/CmsIndexPage';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';

type ContactRow = {
    id: number;
    contact_point: string;
    created_at: string | null;
};

type Props = {
    items: Paginated<ContactRow>;
    filters: CmsFilters;
};

export default function ContactsIndex({ items, filters }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.cms_contacts')} />
            <CmsIndexPage
                titleKey="menu.cms_contacts"
                descriptionKey="cms.contacts_description"
                createHref="/cms/contacts/create"
                createLabelKey="cms.create_contact"
                indexHref="/cms/contacts"
                destroyBase="/cms/contacts"
                items={items}
                filters={filters}
                langFilter={false}
                columns={[
                    { id: 'contact_point', header: t('cms.contact_point'), mobile: 'title', sortable: true, className: 'font-medium', cell: (row) => row.contact_point },
                    { id: 'created_at', header: t('customers.joined'), sortable: true, mobile: 'meta', className: 'text-muted-foreground', cell: (row) => row.created_at },
                ]}
            />
        </>
    );
}
