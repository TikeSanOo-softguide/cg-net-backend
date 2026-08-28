import { useState } from "react";
import { Head } from "@inertiajs/react";

import {
    CmsIndexPage,
    type CmsFilters,
} from "@/components/cms/shared/CmsIndexPage";
import {
    ContactFormDialog,
    type ContactItem,
} from "@/components/cms/contact/ContactFormDialog";
import type { Paginated } from "@/components/Pagination";
import { useTranslation } from "@/hooks/useTranslation";

type Props = {
    items: Paginated<ContactItem & { created_at: string | null }>;
    filters: CmsFilters;
};

export default function ContactsIndex({ items, filters }: Props) {
    const { t } = useTranslation();
    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<ContactItem | null>(null);

    return (
        <>
            <Head title={t("menu.cms_contacts")} />
            <CmsIndexPage
                createLabelKey="cms.contact.create"
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
                        id: "contact_point",
                        header: t("cms.contact_point"),
                        mobile: "title",
                        sortable: true,
                        className: "font-medium",
                        cell: (row) => row.contact_point,
                    },
                    {
                        id: "created_at",
                        header: t("customers.joined"),
                        sortable: true,
                        mobile: "meta",
                        className: "text-muted-foreground",
                        cell: (row) => row.created_at,
                    },
                ]}
            />
        </>
    );
}
